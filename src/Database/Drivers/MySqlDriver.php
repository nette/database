<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette;
use Nette\Database\Type;


/**
 * Supplemental MySQL database driver.
 */
class MySqlDriver extends PdoDriver
{
	public const ErrorAccessDenied = 1045;
	public const ErrorDuplicateEntry = 1062;
	public const ErrorDataTruncated = 1265;

	/** @deprecated use MySqlDriver::ErrorAccessDenied */
	public const ERROR_ACCESS_DENIED = self::ErrorAccessDenied;

	/** @deprecated use MySqlDriver::ErrorDuplicateEntry */
	public const ERROR_DUPLICATE_ENTRY = self::ErrorDuplicateEntry;

	/** @deprecated use MySqlDriver::ErrorDataTruncated */
	public const ERROR_DATA_TRUNCATED = self::ErrorDataTruncated;


	private bool $supportBooleans;


	/**
	 * Driver options:
	 *   - charset => character encoding to set (default is utf8 or utf8mb4 since MySQL 5.5.3)
	 *   - sqlmode => see http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
	 *   - supportBooleans => converts INT(1) to boolean
	 */
	public function connect(
		string $dsn,
		?string $user = null,
		#[\SensitiveParameter]
		?string $password = null,
		?array $options = null,
	): void
	{
		parent::connect($dsn, $user, $password, $options);
		$charset = $options['charset']
			?? (version_compare($this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.5.3', '>=') ? 'utf8mb4' : 'utf8');
		if ($charset) {
			$this->pdo->query('SET NAMES ' . $this->pdo->quote($charset));
		}

		if (isset($options['sqlmode'])) {
			$this->pdo->query('SET sql_mode=' . $this->pdo->quote($options['sqlmode']));
		}

		$this->supportBooleans = (bool) ($options['supportBooleans'] ?? false);
	}


	public function convertException(\PDOException $e): Nette\Database\DriverException
	{
		$code = $e->errorInfo[1] ?? null;
		if (in_array($code, [1216, 1217, 1451, 1452, 1701], strict: true)) {
			return Nette\Database\ForeignKeyConstraintViolationException::from($e);

		} elseif (in_array($code, [1062, 1557, 1569, 1586], strict: true)) {
			return Nette\Database\UniqueConstraintViolationException::from($e);

		} elseif ($code >= 2001 && $code <= 2028) {
			return Nette\Database\ConnectionException::from($e);

		} elseif (in_array($code, [1048, 1121, 1138, 1171, 1252, 1263, 1566], strict: true)) {
			return Nette\Database\NotNullConstraintViolationException::from($e);

		} else {
			return Nette\Database\DriverException::from($e);
		}
	}


	/********************* SQL ****************d*g**/


	public function delimite(string $name): string
	{
		// @see http://dev.mysql.com/doc/refman/5.0/en/identifiers.html
		return '`' . str_replace('`', '``', $name) . '`';
	}


	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format("'Y-m-d H:i:s'");
	}


	public function formatDateInterval(\DateInterval $value): string
	{
		return $value->format("'%r%h:%I:%S'");
	}


	public function formatLike(string $value, int $pos): string
	{
		$value = str_replace('\\', '\\\\', $value);
		$value = addcslashes(substr($this->connection->quote($value), 1, -1), '%_');
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}


	public function applyLimit(string &$sql, ?int $limit, ?int $offset): void
	{
		if ($limit < 0 || $offset < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');

		} elseif ($limit !== null || $offset) {
			// see http://dev.mysql.com/doc/refman/5.0/en/select.html
			$sql .= ' LIMIT ' . ($limit ?? '18446744073709551615')
				. ($offset ? ' OFFSET ' . $offset : '');
		}
	}


	/********************* reflection ****************d*g**/


	public function getTables(): array
	{
		$tables = [];
		foreach ($this->connection->query('SHOW FULL TABLES') as $row) {
			$tables[] = [
				'name' => $row[0],
				'view' => ($row[1] ?? null) === 'VIEW',
			];
		}

		return $tables;
	}


	public function getColumns(string $table): array
	{
		$columns = [];
		foreach ($this->connection->query('SHOW FULL COLUMNS FROM ' . $this->delimite($table)) as $row) {
			$row = array_change_key_case((array) $row, CASE_LOWER);
			$pair = explode('(', $row['type']);
			$type = match (true) {
				$pair[0] === 'decimal' && str_ends_with($pair[1], ',0)') => Type::Integer,
				$row['type'] === 'tinyint(1)' && $this->supportBooleans => Type::Bool,
				$row['type'] === 'time' => Type::TimeInterval,
				default => Nette\Database\Helpers::detectType($pair[0]),
			};
			$columns[] = [
				'name' => $row['field'],
				'table' => $table,
				'type' => $type,
				'nativetype' => strtoupper($pair[0]),
				'size' => isset($pair[1]) ? (int) $pair[1] : null,
				'nullable' => $row['null'] === 'YES',
				'default' => $row['default'],
				'autoincrement' => $row['extra'] === 'auto_increment',
				'primary' => $row['key'] === 'PRI',
				'vendor' => $row,
			];
		}

		return $columns;
	}


	public function getIndexes(string $table): array
	{
		$indexes = [];
		foreach ($this->connection->query('SHOW INDEX FROM ' . $this->delimite($table)) as $row) {
			$id = $row['Key_name'];
			$indexes[$id]['name'] = $id;
			$indexes[$id]['unique'] = !$row['Non_unique'];
			$indexes[$id]['primary'] = $row['Key_name'] === 'PRIMARY';
			$indexes[$id]['columns'][$row['Seq_in_index'] - 1] = $row['Column_name'];
		}

		return array_values($indexes);
	}


	public function getForeignKeys(string $table): array
	{
		$keys = [];
		foreach ($this->connection->query(<<<X
			SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = DATABASE()
			  AND REFERENCED_TABLE_NAME IS NOT NULL
			  AND TABLE_NAME = {$this->connection->quote($table)}
			X) as $id => $row) {
			$keys[$id]['name'] = $row['CONSTRAINT_NAME'];
			$keys[$id]['local'] = $row['COLUMN_NAME'];
			$keys[$id]['table'] = $row['REFERENCED_TABLE_NAME'];
			$keys[$id]['foreign'] = $row['REFERENCED_COLUMN_NAME'];
		}

		return array_values($keys);
	}


	public function getColumnTypes(\PDOStatement $statement): array
	{
		$types = [];
		$count = $statement->columnCount();
		for ($col = 0; $col < $count; $col++) {
			$meta = $statement->getColumnMeta($col);
			if (isset($meta['native_type'])) {
				$types[$meta['name']] = match (true) {
					$meta['native_type'] === 'NEWDECIMAL' && $meta['precision'] === 0 => Type::Integer,
					$meta['native_type'] === 'TINY' && $meta['len'] === 1 && $this->supportBooleans => Type::Bool,
					$meta['native_type'] === 'TIME' => Type::TimeInterval,
					default => Nette\Database\RowNormalizer::detectType($meta['native_type']),
				};
			}
		}

		return $types;
	}


	public function isSupported(string $item): bool
	{
		// MULTI_COLUMN_AS_OR_COND due to mysql bugs:
		// - http://bugs.mysql.com/bug.php?id=31188
		// - http://bugs.mysql.com/bug.php?id=35819
		// and more.
		return $item === self::SupportSelectUngroupedColumns || $item === self::SupportMultiColumnAsOrCond;
	}
}
