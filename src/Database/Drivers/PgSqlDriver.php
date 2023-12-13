<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette;


/**
 * Supplemental PostgreSQL database driver.
 */
class PgSqlDriver implements Nette\Database\Driver
{
	private Nette\Database\Connection $connection;


	public function initialize(Nette\Database\Connection $connection, array $options): void
	{
		$this->connection = $connection;
	}


	public function convertException(\PDOException $e): Nette\Database\DriverException
	{
		$code = $e->errorInfo[0] ?? null;
		if ($code === '0A000' && str_contains($e->getMessage(), 'truncate')) {
			return Nette\Database\ForeignKeyConstraintViolationException::from($e);

		} elseif ($code === '23502') {
			return Nette\Database\NotNullConstraintViolationException::from($e);

		} elseif ($code === '23503') {
			return Nette\Database\ForeignKeyConstraintViolationException::from($e);

		} elseif ($code === '23505') {
			return Nette\Database\UniqueConstraintViolationException::from($e);

		} elseif ($code === '08006') {
			return Nette\Database\ConnectionException::from($e);

		} else {
			return Nette\Database\DriverException::from($e);
		}
	}


	/********************* SQL ****************d*g**/


	public function delimite(string $name): string
	{
		// @see http://www.postgresql.org/docs/8.2/static/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
		return '"' . str_replace('"', '""', $name) . '"';
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
		$bs = substr($this->connection->quote('\\'), 1, -1); // standard_conforming_strings = on/off
		$value = substr($this->connection->quote($value), 1, -1);
		$value = strtr($value, ['%' => $bs . '%', '_' => $bs . '_', '\\' => '\\\\']);
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}


	public function applyLimit(string &$sql, ?int $limit, ?int $offset): void
	{
		if ($limit < 0 || $offset < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');
		}

		if ($limit !== null) {
			$sql .= ' LIMIT ' . $limit;
		}

		if ($offset) {
			$sql .= ' OFFSET ' . $offset;
		}
	}


	/********************* reflection ****************d*g**/


	public function getTables(): array
	{
		$tables = [];
		foreach ($this->connection->query(<<<'X'
			SELECT DISTINCT ON (c.relname)
				c.relname::varchar AS name,
				c.relkind IN ('v', 'm') AS view,
				n.nspname::varchar || '.' || c.relname::varchar AS "fullName"
			FROM
				pg_catalog.pg_class AS c
				JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
			WHERE
				c.relkind IN ('r', 'v', 'm', 'p')
				AND n.nspname = ANY (pg_catalog.current_schemas(FALSE))
			ORDER BY
				c.relname
			X) as $row) {
			$tables[] = (array) $row;
		}

		return $tables;
	}


	public function getColumns(string $table): array
	{
		$columns = [];
		foreach ($this->connection->query(<<<X
			SELECT
				a.attname::varchar AS name,
				c.relname::varchar AS table,
				upper(t.typname) AS nativetype,
				CASE WHEN a.atttypmod = -1 THEN NULL ELSE a.atttypmod -4 END AS size,
				NOT (a.attnotnull OR t.typtype = 'd' AND t.typnotnull) AS nullable,
				pg_catalog.pg_get_expr(ad.adbin, 'pg_catalog.pg_attrdef'::regclass)::varchar AS default,
				coalesce(co.contype = 'p' AND (seq.relname IS NOT NULL OR strpos(pg_catalog.pg_get_expr(ad.adbin, ad.adrelid), 'nextval') = 1), FALSE) AS autoincrement,
				coalesce(co.contype = 'p', FALSE) AS primary,
				coalesce(seq.relname, substring(pg_catalog.pg_get_expr(ad.adbin, 'pg_catalog.pg_attrdef'::regclass) from 'nextval[(]''"?([^''"]+)')) AS sequence
			FROM
				pg_catalog.pg_attribute AS a
				JOIN pg_catalog.pg_class AS c ON a.attrelid = c.oid
				JOIN pg_catalog.pg_type AS t ON a.atttypid = t.oid
				LEFT JOIN pg_catalog.pg_depend AS d ON d.refobjid = c.oid AND d.refobjsubid = a.attnum AND d.deptype = 'i'
				LEFT JOIN pg_catalog.pg_class AS seq ON seq.oid = d.objid AND seq.relkind = 'S'
				LEFT JOIN pg_catalog.pg_attrdef AS ad ON ad.adrelid = c.oid AND ad.adnum = a.attnum
				LEFT JOIN pg_catalog.pg_constraint AS co ON co.connamespace = c.relnamespace AND contype = 'p' AND co.conrelid = c.oid AND a.attnum = ANY(co.conkey)
			WHERE
				c.relkind IN ('r', 'v', 'm', 'p')
				AND c.oid = {$this->connection->quote($this->delimiteFQN($table))}::regclass
				AND a.attnum > 0
				AND NOT a.attisdropped
			ORDER BY
				a.attnum
			X) as $row) {
			$column = (array) $row;
			$column['vendor'] = $column;
			unset($column['sequence']);

			$columns[] = $column;
		}

		return $columns;
	}


	public function getIndexes(string $table): array
	{
		$indexes = [];
		foreach ($this->connection->query(<<<X
			SELECT
				c2.relname::varchar AS name,
				i.indisunique AS unique,
				i.indisprimary AS primary,
				a.attname::varchar AS column
			FROM
				pg_catalog.pg_class AS c1
				JOIN pg_catalog.pg_index AS i ON c1.oid = i.indrelid
				JOIN pg_catalog.pg_class AS c2 ON i.indexrelid = c2.oid
				LEFT JOIN pg_catalog.pg_attribute AS a ON c1.oid = a.attrelid AND a.attnum = ANY(i.indkey)
			WHERE
				c1.relkind IN ('r', 'p')
				AND c1.oid = {$this->connection->quote($this->delimiteFQN($table))}::regclass
			X) as $row) {
			$id = $row['name'];
			$indexes[$id]['name'] = $id;
			$indexes[$id]['unique'] = $row['unique'];
			$indexes[$id]['primary'] = $row['primary'];
			$indexes[$id]['columns'][] = $row['column'];
		}

		return array_values($indexes);
	}


	public function getForeignKeys(string $table): array
	{
		/* Doesn't work with multi-column foreign keys */
		return $this->connection->query(<<<X
			SELECT
				co.conname::varchar AS name,
				al.attname::varchar AS local,
				nf.nspname || '.' || cf.relname::varchar AS table,
				af.attname::varchar AS foreign
			FROM
				pg_catalog.pg_constraint AS co
				JOIN pg_catalog.pg_class AS cl ON co.conrelid = cl.oid
				JOIN pg_catalog.pg_class AS cf ON co.confrelid = cf.oid
				JOIN pg_catalog.pg_namespace AS nf ON nf.oid = cf.relnamespace
				JOIN pg_catalog.pg_attribute AS al ON al.attrelid = cl.oid AND al.attnum = co.conkey[1]
				JOIN pg_catalog.pg_attribute AS af ON af.attrelid = cf.oid AND af.attnum = co.confkey[1]
			WHERE
				co.contype = 'f'
				AND cl.oid = {$this->connection->quote($this->delimiteFQN($table))}::regclass
				AND nf.nspname = ANY (pg_catalog.current_schemas(FALSE))
			X)->fetchAll();
	}


	public function getColumnTypes(\PDOStatement $statement): array
	{
		static $cache;
		$item = &$cache[$statement->queryString];
		$item ??= Nette\Database\Helpers::detectTypes($statement);
		return $item;
	}


	public function isSupported(string $item): bool
	{
		return $item === self::SUPPORT_SEQUENCE || $item === self::SUPPORT_SUBSELECT || $item === self::SUPPORT_SCHEMA;
	}


	/**
	 * Converts: schema.name => "schema"."name"
	 */
	private function delimiteFQN(string $name): string
	{
		return implode('.', array_map([$this, 'delimite'], explode('.', $name)));
	}
}
