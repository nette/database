<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\Engines;

use Nette;
use Nette\Database\DateTime;
use Nette\Database\Drivers\Connection;
use Nette\Database\Drivers\Engine;
use Nette\Database\TypeConverter;
use function array_values, in_array, preg_match, str_contains, strtoupper, strtr;


/**
 * SQLite database platform.
 */
class SQLiteEngine implements Engine
{
	public string $formatDateTime = 'U';


	public function __construct(
		private readonly Connection $connection,
	) {
	}


	public function isSupported(string $feature): bool
	{
		return $feature === self::SupportMultiInsertAsSelect || $feature === self::SupportMultiColumnAsOrCondition;
	}


	public function classifyException(Nette\Database\DriverException $e): ?string
	{
		$message = $e->getMessage();
		if ($e->getDriverCode() !== 19) {
			return null;

		} elseif (
			str_contains($message, 'must be unique')
			|| str_contains($message, 'is not unique')
			|| str_contains($message, 'UNIQUE constraint failed')
		) {
			return Nette\Database\UniqueConstraintViolationException::class;

		} elseif (
			str_contains($message, 'may not be null')
			|| str_contains($message, 'NOT NULL constraint failed')
		) {
			return Nette\Database\NotNullConstraintViolationException::class;

		} elseif (
			str_contains($message, 'foreign key constraint failed')
			|| str_contains($message, 'FOREIGN KEY constraint failed')
		) {
			return Nette\Database\ForeignKeyConstraintViolationException::class;

		} else {
			return Nette\Database\ConstraintViolationException::class;
		}
	}


	/********************* SQL ****************d*g**/


	public function delimite(string $name): string
	{
		return '[' . strtr($name, '[]', '  ') . ']';
	}


	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format($this->formatDateTime);
	}


	public function formatDateInterval(\DateInterval $value): string
	{
		throw new Nette\NotSupportedException;
	}


	public function applyLimit(string &$sql, ?int $limit, ?int $offset): void
	{
		if ($limit < 0 || $offset < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');

		} elseif ($limit !== null || $offset) {
			$sql .= ' LIMIT ' . ($limit ?? '-1')
				. ($offset ? ' OFFSET ' . $offset : '');
		}
	}


	/********************* reflection ****************d*g**/


	public function getTables(): array
	{
		$tables = [];
		$rows = $this->connection->query(<<<'X'
			SELECT name, type = 'view' as view
			FROM sqlite_master
			WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%'
			UNION ALL
			SELECT name, type = 'view' as view
			FROM sqlite_temp_master
			WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%'
			ORDER BY name
			X);

		while ($row = $rows->fetch()) {
			$tables[] = [
				'name' => $row['name'],
				'view' => (bool) $row['view'],
				'comment' => null,
			];
		}

		return $tables;
	}


	public function getColumns(string $table): array
	{
		$createSql = $this->connection->query(<<<'X'
			SELECT sql
			FROM sqlite_master
			WHERE type = 'table' AND name = ?
			UNION ALL
			SELECT sql
			FROM sqlite_temp_master
			WHERE type = 'table' AND name = ?
			X, [$table, $table])->fetch();

		$columns = [];
		$rows = $this->connection->query("PRAGMA table_info({$this->delimite($table)})");
		while ($row = $rows->fetch()) {
			$column = $row['name'];
			$pattern = "/(\"$column\"|`$column`|\\[$column\\]|$column)\\s+[^,]+\\s+PRIMARY\\s+KEY\\s+AUTOINCREMENT/Ui";
			$typeInfo = Nette\Database\Helpers::parseColumnType($row['type']);
			$columns[] = [
				'name' => $column,
				'table' => $table,
				'nativeType' => strtoupper($typeInfo['type'] ?? 'BLOB'),
				'size' => $typeInfo['size'],
				'scale' => $typeInfo['scale'],
				'nullable' => $row['notnull'] == 0,
				'default' => $row['dflt_value'],
				'autoIncrement' => $createSql && preg_match($pattern, $createSql['sql']),
				'primary' => $row['pk'] > 0,
				'comment' => null,
				'vendor' => $row,
			];
		}

		return $columns;
	}


	public function getIndexes(string $table): array
	{
		$indexes = [];
		$rows = $this->connection->query("PRAGMA index_list({$this->delimite($table)})");
		while ($row = $rows->fetch()) {
			$id = $row['name'];
			$indexes[$id]['name'] = $id;
			$indexes[$id]['unique'] = (bool) $row['unique'];
			$indexes[$id]['primary'] = false;
		}

		foreach ($indexes as $index => $values) {
			$res = $this->connection->query("PRAGMA index_info({$this->delimite($index)})");
			while ($row = $res->fetch()) {
				$indexes[$index]['columns'][] = $row['name'];
			}
		}

		$columns = $this->getColumns($table);
		foreach ($indexes as $index => $values) {
			$column = $values['columns'][0];
			foreach ($columns as $info) {
				if ($column === $info['name']) {
					$indexes[$index]['primary'] = (bool) $info['primary'];
					break;
				}
			}
		}

		if (!$indexes) { // @see http://www.sqlite.org/lang_createtable.html#rowid
			foreach ($columns as $column) {
				if ($column['vendor']['pk']) {
					$indexes[] = [
						'name' => 'ROWID',
						'unique' => true,
						'primary' => true,
						'columns' => [$column['name']],
					];
					break;
				}
			}
		}

		return array_values($indexes);
	}


	public function getForeignKeys(string $table): array
	{
		$keys = [];
		$rows = $this->connection->query("PRAGMA foreign_key_list({$this->delimite($table)})");
		while ($row = $rows->fetch()) {
			$id = $row['id'];
			$keys[$id]['name'] = $id;
			$keys[$id]['local'] = $row['from'];
			$keys[$id]['table'] = $row['table'];
			$keys[$id]['foreign'] = $row['to'];
		}

		return array_values($keys);
	}


	public function convertToPhp(mixed $value, array $meta, TypeConverter $converter): mixed
	{
		return $converter->convertDateTime && in_array($meta['nativeType'], ['DATE', 'DATETIME'], strict: true)
			? (is_int($value) ? (new DateTime)->setTimestamp($value) : new DateTime($value))
			: $converter->convertToPhp($value, $meta);
	}
}
