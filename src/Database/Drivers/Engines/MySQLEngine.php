<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Drivers\Engines;

use Nette;
use Nette\Database\Drivers\{Connection, Engine};
use Nette\Database\TypeConverter;
use function in_array;


/**
 * MySQL-like database platform.
 */
class MySQLEngine implements Engine
{
	public function __construct(
		private readonly Connection $connection,
	) {
	}


	public function isSupported(string $feature): bool
	{
		// MULTI_COLUMN_AS_OR_COND due to mysql bugs:
		// - http://bugs.mysql.com/bug.php?id=31188
		// - http://bugs.mysql.com/bug.php?id=35819
		// and more.
		return $feature === self::SupportSelectUngroupedColumns || $feature === self::SupportMultiColumnAsOrCondition;
	}


	public function convertException(\PDOException $e): Nette\Database\DriverException
	{
		$code = $e->errorInfo[1] ?? null;
		if (in_array($code, [1216, 1217, 1451, 1452, 1701], true)) {
			return Nette\Database\ForeignKeyConstraintViolationException::from($e);

		} elseif (in_array($code, [1062, 1557, 1569, 1586], true)) {
			return Nette\Database\UniqueConstraintViolationException::from($e);

		} elseif ($code === 3819 || $code === 4025) { // 3819 = MySQL, 4025 = MariaDB
			return Nette\Database\CheckConstraintViolationException::from($e);

		} elseif ($code === 1213) {
			return Nette\Database\DeadlockException::from($e);

		} elseif ($code === 1205) {
			return Nette\Database\LockTimeoutException::from($e);

		} elseif (
			$code === 2006
			|| $code === 2013
			// ER_CLIENT_INTERACTION_TIMEOUT (MySQL 8.0.24+); on MariaDB 4031 is an unrelated trigger error
			|| ($code === 4031 && str_contains($e->getMessage(), 'disconnected'))
		) {
			return Nette\Database\ConnectionLostException::from($e);

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
		$query = $this->connection->query(<<<'XX'
			SELECT TABLE_NAME, TABLE_TYPE, TABLE_COMMENT
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			XX);

		while ($row = $query->fetch()) {
			$row = array_values($row);
			$tables[] = [
				'name' => (string) $row['TABLE_NAME'],
				'view' => $row['TABLE_TYPE'] === 'VIEW',
				'comment' => $row['TABLE_TYPE'] === 'VIEW' ? '' : (string) $row['TABLE_COMMENT'], // views have the literal comment 'VIEW'
			];
		}

		return $tables;
	}


	public function getColumns(string $table): array
	{
		$columns = [];
		$rows = $this->connection->query('SHOW FULL COLUMNS FROM ?name', $table);
		while ($row = $rows->fetch()) {
			$row = array_change_key_case($row);
			$typeInfo = Nette\Database\Helpers::parseColumnType($row['type']);
			$columns[] = [
				'name' => $row['field'],
				'table' => $table,
				'nativeType' => strtoupper($typeInfo['type'] ?? ''),
				'size' => $typeInfo['size'],
				'scale' => $typeInfo['scale'],
				'nullable' => $row['null'] === 'YES',
				'default' => $row['default'],
				'autoIncrement' => $row['extra'] === 'auto_increment',
				'primary' => $row['key'] === 'PRI',
				'comment' => $row['comment'],
				'vendor' => $row,
			];
		}

		return $columns;
	}


	public function getIndexes(string $table): array
	{
		$indexes = [];
		$rows = $this->connection->query('SHOW INDEX FROM ?name', $table);
		while ($row = $rows->fetch()) {
			$id = (string) $row['Key_name'];
			$indexes[$id] ??= [
				'name' => $id,
				'unique' => !$row['Non_unique'],
				'primary' => $id === 'PRIMARY',
				'columns' => [],
			];
			$indexes[$id]['columns'][(int) $row['Seq_in_index'] - 1] = $row['Column_name'] === null
				? (string) ($row['Expression'] ?? '') // functional index part
				: (string) $row['Column_name'];
		}

		foreach ($indexes as &$index) {
			ksort($index['columns']);
			$index['columns'] = array_values($index['columns']);
		}

		return array_values($indexes);
	}


	public function getForeignKeys(string $table): array
	{
		$keys = [];
		$rows = $this->connection->query(<<<'X'
			SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = DATABASE()
			  AND REFERENCED_TABLE_NAME IS NOT NULL
			  AND TABLE_NAME = ?
			ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
			X, [$table]);

		while ($row = $rows->fetch()) {
			$keys[] = [
				'name' => (string) $row['CONSTRAINT_NAME'],
				'local' => (string) $row['COLUMN_NAME'],
				'table' => (string) $row['REFERENCED_TABLE_NAME'],
				'foreign' => (string) $row['REFERENCED_COLUMN_NAME'],
			];
		}

		return $keys;
	}


	public function convertToPhp(mixed $value, array $meta, TypeConverter $converter): mixed
	{
		return match ($meta['nativeType']) {
			'TINY' => $meta['size'] === 1 && $converter->convertBoolean
				? $converter->toBool($value)
				: $converter->toInt($value),
			'TIME' => $converter->convertDateTime ? $converter->toInterval($value) : $value,
			'DATE', 'DATETIME', 'TIMESTAMP' => $converter->convertDateTime
				? (str_starts_with($value, '0000-00') ? null : $converter->toDateTime($value))
				: $value,
			default => $converter->convertToPhp($value, $meta),
		};
	}
}
