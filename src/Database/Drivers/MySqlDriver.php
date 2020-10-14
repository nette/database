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
class MySqlDriver implements Nette\Database\ISupplementalDriver
{
	use Nette\SmartObject;

	public const
		ERROR_ACCESS_DENIED = 1045,
		ERROR_DUPLICATE_ENTRY = 1062,
		ERROR_DATA_TRUNCATED = 1265;

	/** @var Nette\Database\Connection */
	private $connection;

	/** @var string */
	private $version;


	/**
	 * Driver options:
	 *   - charset => character encoding to set (default is utf8 or utf8mb4 since MySQL 5.5.3)
	 *   - sqlmode => see http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
	 */
	public function initialize(Nette\Database\Connection $connection, array $options): void
	{
		$this->connection = $connection;
		$this->version = $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
		$charset = $options['charset'] ?? (version_compare($this->version, '5.5.3', '>=') ? 'utf8mb4' : 'utf8');
		if ($charset) {
			$connection->query('SET NAMES ?', $charset);
		}
		if (isset($options['sqlmode'])) {
			$connection->query('SET sql_mode=?', $options['sqlmode']);
		}
	}


	public function convertException(\PDOException $e): Nette\Database\DriverException
	{
		$code = $e->errorInfo[1] ?? null;
		if (in_array($code, [1216, 1217, 1451, 1452, 1701], true)) {
			return Nette\Database\ForeignKeyConstraintViolationException::from($e);

		} elseif (in_array($code, [1062, 1557, 1569, 1586], true)) {
			return Nette\Database\UniqueConstraintViolationException::from($e);

		} elseif ($code >= 2001 && $code <= 2028) {
			return Nette\Database\ConnectionException::from($e);

		} elseif (in_array($code, [1048, 1121, 1138, 1171, 1252, 1263, 1566], true)) {
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
			$type = explode('(', $row['type']);
			$columns[] = [
				'name' => $row['field'],
				'table' => $table,
				'nativetype' => strtoupper($type[0]),
				'size' => isset($type[1]) ? (int) $type[1] : null,
				'nullable' => $row['null'] === 'YES',
				'default' => $row['default'],
				'autoincrement' => $row['extra'] === 'auto_increment',
				'primary' => $row['key'] === 'PRI',
				'vendor' => (array) $row,
			];
		}
		return $columns;
	}


	public function getIndexes(string $table): array
	{
		$indexes = [];
		foreach ($this->connection->query('SHOW INDEX FROM ' . $this->delimite($table)) as $row) {
			$row = array_change_key_case((array) $row, CASE_LOWER);
			$indexes[$row['key_name']]['name'] = $row['key_name'];
			$indexes[$row['key_name']]['unique'] = !$row['non_unique'];
			$indexes[$row['key_name']]['primary'] = $row['key_name'] === 'PRIMARY';
			$indexes[$row['key_name']]['columns'][$row['seq_in_index'] - 1] = $row['column_name'];
		}
		return array_values($indexes);
	}


	public function getForeignKeys(string $table): array
	{
		$keys = [];
		$query = 'SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE '
			. 'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_NAME = ' . $this->connection->quote($table);

		foreach ($this->connection->query($query) as $id => $row) {
			$row = array_change_key_case((array) $row, CASE_LOWER);
			$keys[$id]['name'] = $row['constraint_name']; // foreign key name
			$keys[$id]['local'] = $row['column_name']; // local columns
			$keys[$id]['table'] = $row['referenced_table_name']; // referenced table
			$keys[$id]['foreign'] = $row['referenced_column_name']; // referenced columns
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
		// SUPPORT_SUBSELECT is slow before 5.7
		// - http://mysqlserverteam.com/derived-tables-in-mysql-5-7/
		return $item === self::SUPPORT_SELECT_UNGROUPED_COLUMNS
			|| $item === self::SUPPORT_MULTI_COLUMN_AS_OR_COND
			|| ($item === self::SUPPORT_SUBSELECT && version_compare($this->version, '5.7', '>='));
	}
}
