<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette;


/**
 * Supplemental MS SQL database driver.
 */
class MsSqlDriver implements Nette\Database\Driver
{
	private Nette\Database\Connection $connection;


	public function initialize(Nette\Database\Connection $connection, array $options): void
	{
		$this->connection = $connection;
	}


	public function isSupported(string $feature): bool
	{
		return false;
	}


	public function convertException(\PDOException $e): Nette\Database\DriverException
	{
		return Nette\Database\DriverException::from($e);
	}


	/********************* SQL ****************d*g**/


	public function delimite(string $name): string
	{
		// @see https://msdn.microsoft.com/en-us/library/ms176027.aspx
		return '[' . str_replace(['[', ']'], ['[[', ']]'], $name) . ']';
	}


	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format("'Y-m-d H:i:s'");
	}


	public function formatDateInterval(\DateInterval $value): string
	{
		throw new Nette\NotSupportedException;
	}


	public function formatLike(string $value, int $pos): string
	{
		$value = strtr($value, ["'" => "''", '%' => '[%]', '_' => '[_]', '[' => '[[]']);
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}


	public function applyLimit(string &$sql, ?int $limit, ?int $offset): void
	{
		if ($offset) {
			throw new Nette\NotSupportedException('Offset is not supported by this database.');

		} elseif ($limit < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');

		} elseif ($limit !== null) {
			$sql = preg_replace('#^\s*(SELECT(\s+DISTINCT|\s+ALL)?|UPDATE|DELETE)#i', '$0 TOP ' . $limit, $sql, 1, $count);
			if (!$count) {
				throw new Nette\InvalidArgumentException('SQL query must begin with SELECT, UPDATE or DELETE command.');
			}
		}
	}


	/********************* reflection ****************d*g**/


	public function getTables(): array
	{
		$tables = [];
		$rows = $this->connection->query(<<<'X'
			SELECT
				TABLE_SCHEMA,
				TABLE_NAME,
				TABLE_TYPE,
				CAST(ISNULL(p.value, '') AS VARCHAR(255)) AS comment
			FROM
				INFORMATION_SCHEMA.TABLES t
			LEFT JOIN
				sys.extended_properties p ON p.major_id = OBJECT_ID(TABLE_SCHEMA + '.' + TABLE_NAME)
				AND p.minor_id = 0
				AND p.name = 'MS_Description'
			X);

		while ($row = $rows->fetch()) {
			$tables[] = [
				'name' => $row['TABLE_SCHEMA'] . '.' . $row['TABLE_NAME'],
				'view' => ($row['TABLE_TYPE'] ?? null) === 'VIEW',
				'comment' => $row['comment'] ?? '',
			];
		}

		return $tables;
	}


	public function getColumns(string $table): array
	{
		[$table_schema, $table_name] = explode('.', $table);
		$columns = [];

		$rows = $this->connection->query(<<<'X'
			SELECT
				c.COLUMN_NAME,
				c.DATA_TYPE,
				c.CHARACTER_MAXIMUM_LENGTH,
				c.NUMERIC_PRECISION,
				c.IS_NULLABLE,
				c.COLUMN_DEFAULT,
				c.DOMAIN_NAME,
				CAST(p.value AS NVARCHAR(4000)) AS comment
			FROM
				INFORMATION_SCHEMA.COLUMNS c
				LEFT JOIN sys.extended_properties p ON
					p.major_id = OBJECT_ID(c.TABLE_SCHEMA + '.' + c.TABLE_NAME) AND
					p.minor_id = COLUMNPROPERTY(OBJECT_ID(c.TABLE_SCHEMA + '.' + c.TABLE_NAME), c.COLUMN_NAME, 'ColumnId') AND
					p.name = 'MS_Description'
			WHERE
				c.TABLE_SCHEMA = ?
				AND c.TABLE_NAME = ?
			X, $table_schema, $table_name);

		while ($row = $rows->fetch()) {
			$columns[] = [
				'name' => $row['COLUMN_NAME'],
				'table' => $table,
				'nativetype' => strtoupper($row['DATA_TYPE']),
				'size' => $row['CHARACTER_MAXIMUM_LENGTH'] ?? $row['NUMERIC_PRECISION'],
				'unsigned' => false,
				'nullable' => $row['IS_NULLABLE'] === 'YES',
				'default' => $row['COLUMN_DEFAULT'],
				'autoincrement' => $row['DOMAIN_NAME'] === 'COUNTER',
				'primary' => $row['COLUMN_NAME'] === 'ID',
				'comment' => $row['comment'] ?? '',
				'vendor' => (array) $row,
			];
		}

		return $columns;
	}


	public function getIndexes(string $table): array
	{
		[, $table_name] = explode('.', $table);
		$indexes = [];

		$rows = $this->connection->query(<<<'X'
			SELECT
				 name_index = ind.name,
				 id_column = ic.index_column_id,
				 name_column = col.name,
				 ind.is_unique,
				 ind.is_primary_key
			FROM
				sys.indexes ind
				INNER JOIN sys.index_columns ic ON  ind.object_id = ic.object_id and ind.index_id = ic.index_id
				INNER JOIN sys.columns col ON ic.object_id = col.object_id and ic.column_id = col.column_id
				INNER JOIN sys.tables t ON ind.object_id = t.object_id
			WHERE
				 t.name = ?
			ORDER BY
				 t.name, ind.name, ind.index_id, ic.index_column_id
			X, $table_name);

		while ($row = $rows->fetch()) {
			$id = $row['name_index'];
			$indexes[$id]['name'] = $id;
			$indexes[$id]['unique'] = $row['is_unique'] !== 'False';
			$indexes[$id]['primary'] = $row['is_primary_key'] !== 'False';
			$indexes[$id]['columns'][$row['id_column'] - 1] = $row['name_column'];
		}

		return array_values($indexes);
	}


	public function getForeignKeys(string $table): array
	{
		[$table_schema, $table_name] = explode('.', $table);
		$keys = [];

		$rows = $this->connection->query(<<<'X'
			SELECT
				obj.name AS [fk_name],
				col1.name AS [column],
				tab2.name AS [referenced_table],
				col2.name AS [referenced_column]
			FROM
				sys.foreign_key_columns fkc
				INNER JOIN sys.objects obj
					ON obj.object_id = fkc.constraint_object_id
				INNER JOIN sys.tables tab1
					ON tab1.object_id = fkc.parent_object_id
				INNER JOIN sys.schemas sch
					ON tab1.schema_id = sch.schema_id
				INNER JOIN sys.columns col1
					ON col1.column_id = parent_column_id AND col1.object_id = tab1.object_id
				INNER JOIN sys.tables tab2
					ON tab2.object_id = fkc.referenced_object_id
				INNER JOIN sys.columns col2
				ON col2.column_id = referenced_column_id AND col2.object_id = tab2.object_id
			WHERE
				tab1.name = ?
			X, $table_name);

		$id = 0;
		while ($row = $rows->fetch()) {
			$keys[$id]['name'] = $row['fk_name'];
			$keys[$id]['local'] = $row['column'];
			$keys[$id]['table'] = $table_schema . '.' . $row['referenced_table'];
			$keys[$id++]['foreign'] = $row['referenced_column'];
		}

		return array_values($keys);
	}


	public function getColumnTypes(\PDOStatement $statement): array
	{
		return Nette\Database\Helpers::detectTypes($statement);
	}
}
