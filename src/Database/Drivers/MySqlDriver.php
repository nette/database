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

	const ERROR_ACCESS_DENIED = 1045;
	const ERROR_DUPLICATE_ENTRY = 1062;
	const ERROR_DATA_TRUNCATED = 1265;

	/** @var Nette\Database\Connection */
	private $connection;


	/**
	 * Driver options:
	 *   - charset => character encoding to set (default is utf8 or utf8mb4 since MySQL 5.5.3)
	 *   - sqlmode => see http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
	 */
	public function initialize(Nette\Database\Connection $connection, array $options)
	{
		$this->connection = $connection;
		$charset = $options['charset']
			?? (version_compare($connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.5.3', '>=') ? 'utf8mb4' : 'utf8');
		if ($charset) {
			$connection->query("SET NAMES '$charset'");
		}
		if (isset($options['sqlmode'])) {
			$connection->query("SET sql_mode='$options[sqlmode]'");
		}
	}


	public function convertException(\PDOException $e): Nette\Database\DriverException
	{
		$code = $e->errorInfo[1] ?? NULL;
		if (in_array($code, [1216, 1217, 1451, 1452, 1701], TRUE)) {
			return Nette\Database\ForeignKeyConstraintViolationException::from($e);

		} elseif (in_array($code, [1062, 1557, 1569, 1586], TRUE)) {
			return Nette\Database\UniqueConstraintViolationException::from($e);

		} elseif ($code >= 2001 && $code <= 2028) {
			return Nette\Database\ConnectionException::from($e);

		} elseif (in_array($code, [1048, 1121, 1138, 1171, 1252, 1263, 1566], TRUE)) {
			return Nette\Database\NotNullConstraintViolationException::from($e);

		} else {
			return Nette\Database\DriverException::from($e);
		}
	}


	/********************* SQL ****************d*g**/


	/**
	 * Delimites identifier for use in a SQL statement.
	 */
	public function delimite(string $name): string
	{
		// @see http://dev.mysql.com/doc/refman/5.0/en/identifiers.html
		return '`' . str_replace('`', '``', $name) . '`';
	}


	/**
	 * Undelimites identifier
	 */
	public function undelimite($name)
	{
		$last = substr($name, -1);
		return str_replace($last . $last, $last, substr($name, 1, -1));
	}


	/**
	 * Formats boolean for use in a SQL statement.
	 */
	public function formatBool(bool $value): string
	{
		return $value ? '1' : '0';
	}


	/**
	 * Formats date-time for use in a SQL statement.
	 */
	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format("'Y-m-d H:i:s'");
	}


	/**
	 * Formats date-time interval for use in a SQL statement.
	 */
	public function formatDateInterval(\DateInterval $value): string
	{
		return $value->format("'%r%h:%I:%S'");
	}


	/**
	 * Encodes string for use in a LIKE statement.
	 */
	public function formatLike(string $value, int $pos): string
	{
		$value = str_replace('\\', '\\\\', $value);
		$value = addcslashes(substr($this->connection->quote($value), 1, -1), '%_');
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}


	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 */
	public function applyLimit(string &$sql, ?int $limit, ?int $offset)
	{
		if ($limit < 0 || $offset < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');

		} elseif ($limit !== NULL || $offset) {
			// see http://dev.mysql.com/doc/refman/5.0/en/select.html
			$sql .= ' LIMIT ' . ($limit === NULL ? '18446744073709551615' : $limit)
				. ($offset ? ' OFFSET ' . $offset : '');
		}
	}


	/**
	 * Normalizes result row.
	 */
	public function normalizeRow(array $row): array
	{
		return $row;
	}


	/********************* reflection ****************d*g**/


	/**
	 * Returns list of tables.
	 */
	public function getTables(): array
	{
		$tables = [];
		foreach ($this->connection->query('SHOW FULL TABLES') as $row) {
			$tables[] = [
				'name' => $row[0],
				'view' => ($row[1] ?? NULL) === 'VIEW',
			];
		}
		return $tables;
	}


	/**
	 * Returns metadata for all columns in a table.
	 */
	public function getColumns(string $table): array
	{
		$columns = [];
		foreach ($this->connection->query('SHOW FULL COLUMNS FROM ' . $this->delimite($table)) as $row) {
			$type = explode('(', $row['Type']);
			$columns[] = [
				'name' => $row['Field'],
				'table' => $table,
				'nativetype' => strtoupper($type[0]),
				'size' => isset($type[1]) ? (int) $type[1] : NULL,
				'unsigned' => (bool) strstr($row['Type'], 'unsigned'),
				'nullable' => $row['Null'] === 'YES',
				'default' => $row['Default'],
				'autoincrement' => $row['Extra'] === 'auto_increment',
				'primary' => $row['Key'] === 'PRI',
				'vendor' => (array) $row,
			];
		}
		return $columns;
	}


	/**
	 * Returns metadata for all indexes in a table.
	 */
	public function getIndexes(string $table): array
	{
		$indexes = [];
		foreach ($this->connection->query('SHOW INDEX FROM ' . $this->delimite($table)) as $row) {
			$indexes[$row['Key_name']]['name'] = $row['Key_name'];
			$indexes[$row['Key_name']]['unique'] = !$row['Non_unique'];
			$indexes[$row['Key_name']]['primary'] = $row['Key_name'] === 'PRIMARY';
			$indexes[$row['Key_name']]['columns'][$row['Seq_in_index'] - 1] = $row['Column_name'];
		}
		return array_values($indexes);
	}


	/**
	 * Returns metadata for all foreign keys in a table.
	 */
	public function getForeignKeys(string $table): array
	{
		$keys = [];
		$query = 'SHOW CREATE TABLE ' . $this->delimite($table);
		$tableReflection = $this->connection->query($query)->fetch();
		$columnPattern = '`(?:[^`]|``)+`';
		preg_match_all("~CONSTRAINT ($columnPattern) "
				. "FOREIGN KEY ?\\(((?:$columnPattern,? ?)+)\\) "
				. "REFERENCES ($columnPattern)(?:\\.($columnPattern))? \\(((?:$columnPattern,? ?)+)\\)"
				. "(?: ON DELETE (RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT))?"
				. "(?: ON UPDATE (RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT))?~", $tableReflection['Create Table'], $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			preg_match_all("~$columnPattern~", $match[2], $source);
			$locals = array_map([$this, 'undelimite'], $source[0]);
			preg_match_all("~$columnPattern~", $match[5], $target);
			$foreigns = array_map([$this, 'undelimite'], $target[0]);

			$keys[] = [
				"name" => $this->undelimite($match[1]),
				"table" => $this->undelimite($match[4] != "" ? $match[4] : $match[3]),
				"local" => reset($locals),
				"foreign" => reset($foreigns),
				"onDelete" => (isset($match[6]) ? $match[6] : "RESTRICT"),
				"onUpdate" => (isset($match[7]) ? $match[7] : "RESTRICT"),
			];
		}
		return $keys;
	}


	/**
	 * Returns associative array of detected types (IReflection::FIELD_*) in result set.
	 */
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
		return $item === self::SUPPORT_SELECT_UNGROUPED_COLUMNS || $item === self::SUPPORT_MULTI_COLUMN_AS_OR_COND;
	}

}
