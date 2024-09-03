<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\Engines;

use Nette;
use Nette\Database\Drivers\Connection;
use Nette\Database\Drivers\Engine;
use Nette\Database\TypeConverter;
use function array_change_key_case, array_values, in_array, str_replace, strtoupper;


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
				'name' => $row['TABLE_NAME'],
				'view' => $row['TABLE_TYPE'] === 'VIEW',
				'comment' => $row['TABLE_COMMENT'],
			];
		}

		return $tables;
	}


	public function getColumns(string $table): array
	{
		$columns = [];
		$rows = $this->connection->query('SHOW FULL COLUMNS FROM ' . $this->delimite($table));
		while ($row = $rows->fetch()) {
			$row = array_change_key_case($row);
			$typeInfo = Nette\Database\Helpers::parseColumnType($row['type']);
			$columns[] = [
				'name' => $row['field'],
				'table' => $table,
				'nativeType' => strtoupper($typeInfo['type']),
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
		$rows = $this->connection->query('SHOW INDEX FROM ' . $this->delimite($table));
		while ($row = $rows->fetch()) {
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
		$rows = $this->connection->query(<<<'X'
			SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = DATABASE()
			  AND REFERENCED_TABLE_NAME IS NOT NULL
			  AND TABLE_NAME = ?
			X, [$table]);

		$id = 0;
		while ($row = $rows->fetch()) {
			$keys[$id]['name'] = $row['CONSTRAINT_NAME'];
			$keys[$id]['local'] = $row['COLUMN_NAME'];
			$keys[$id]['table'] = $row['REFERENCED_TABLE_NAME'];
			$keys[$id++]['foreign'] = $row['REFERENCED_COLUMN_NAME'];
		}

		return array_values($keys);
	}


	public function convertToPhp(mixed $value, array $meta, TypeConverter $converter): mixed
	{
		return match ($meta['nativeType']) {
			'NEWDECIMAL' => $meta['scale'] === 0
				? $converter->toInt($value)
				: $converter->toFloat($value),
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
