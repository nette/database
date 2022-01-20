<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette;


/**
 * Supplemental MySQL database driver.
 */
class MySqlDriver extends PdoDriver
{
	public const
		ERROR_ACCESS_DENIED = 1045,
		ERROR_DUPLICATE_ENTRY = 1062,
		ERROR_DATA_TRUNCATED = 1265;


	/**
	 * Driver options:
	 *   - charset => character encoding to set (default is utf8 or utf8mb4 since MySQL 5.5.3)
	 *   - sqlmode => see http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
	 */
	public function connect(string $dsn, ?string $user = null, ?string $password = null, ?array $options = null): void
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
	}


	public function detectExceptionClass(\PDOException $e): ?string
	{
		$code = $e->errorInfo[1] ?? null;
		if (in_array($code, [1216, 1217, 1451, 1452, 1701], true)) {
			return Nette\Database\ForeignKeyConstraintViolationException::class;

		} elseif (in_array($code, [1062, 1557, 1569, 1586], true)) {
			return Nette\Database\UniqueConstraintViolationException::class;

		} elseif ($code >= 2001 && $code <= 2028) {
			return Nette\Database\ConnectionException::class;

		} elseif (in_array($code, [1048, 1121, 1138, 1171, 1252, 1263, 1566], true)) {
			return Nette\Database\NotNullConstraintViolationException::class;

		} else {
			return null;
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
		$value = addcslashes(substr($this->pdo->quote($value), 1, -1), '%_');
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
		return $this->pdo->query('SHOW FULL TABLES')->fetchAll(
			\PDO::FETCH_FUNC,
			fn($name, $type) => new Nette\Database\Reflection\Table($name, $type === 'VIEW'),
		);
	}


	public function getColumns(string $table): array
	{
		$columns = [];
		foreach ($this->pdo->query('SHOW FULL COLUMNS FROM ' . $this->delimite($table), \PDO::FETCH_ASSOC) as $row) {
			$type = explode('(', $row['Type']);
			$columns[] = new Nette\Database\Reflection\Column(
				name: $row['Field'],
				table: $table,
				nativeType: $type[0],
				size: isset($type[1]) ? (int) $type[1] : null,
				nullable: $row['Null'] === 'YES',
				default: $row['Default'],
				autoIncrement: $row['Extra'] === 'auto_increment',
				primary: $row['Key'] === 'PRI',
				vendor: $row,
			);
		}

		return $columns;
	}


	public function getIndexes(string $table): array
	{
		$indexes = [];
		foreach ($this->pdo->query('SHOW INDEX FROM ' . $this->delimite($table)) as $row) {
			$id = $row['Key_name'];
			$indexes[$id]['name'] = $id;
			$indexes[$id]['unique'] = !$row['Non_unique'];
			$indexes[$id]['primary'] = $row['Key_name'] === 'PRIMARY';
			$indexes[$id]['columns'][$row['Seq_in_index'] - 1] = $row['Column_name'];
		}

		return array_map(fn($data) => new Nette\Database\Reflection\Index(...$data), array_values($indexes));
	}


	public function getForeignKeys(string $table): array
	{
		$keys = [];
		foreach ($this->pdo->query(<<<X
			SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = DATABASE()
			  AND REFERENCED_TABLE_NAME IS NOT NULL
			  AND TABLE_NAME = {$this->pdo->quote($table)}
			X) as $row) {
			$id = $row['CONSTRAINT_NAME'];
			$keys[$id]['name'] = $id;
			$keys[$id]['columns'][] = $row['COLUMN_NAME'];
			$keys[$id]['targetTable'] = $row['REFERENCED_TABLE_NAME'];
			$keys[$id]['targetColumns'][] = $row['REFERENCED_COLUMN_NAME'];
		}

		return array_map(fn($data) => new Nette\Database\Reflection\ForeignKey(...$data), array_values($keys));
	}


	public function getColumnTypes(\PDOStatement $statement): array
	{
		$types = [];
		$count = $statement->columnCount();
		for ($col = 0; $col < $count; $col++) {
			$meta = $statement->getColumnMeta($col);
			if (isset($meta['native_type'])) {
				$types[$meta['name']] = $type = Nette\Database\Helpers::detectType($meta['native_type']);
				if ($type === Nette\Database\IStructure::FIELD_TIME) {
					$types[$meta['name']] = Nette\Database\IStructure::FIELD_TIME_INTERVAL;
				}
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
