<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Drivers\Engines;

use Nette;
use Nette\Database\Drivers\{Connection, Engine};
use Nette\Database\TypeConverter;


/**
 * PostgreSQL database platform.
 */
class PostgreSQLEngine implements Engine
{
	public function __construct(
		private readonly Connection $connection,
	) {
	}


	public function isSupported(string $feature): bool
	{
		return $feature === self::SupportSequence || $feature === self::SupportSchema || $feature === self::SupportDefaultValues;
	}


	public function classifyException(Nette\Database\DriverException $e): ?string
	{
		return match ($e->getSqlState()) {
			'0A000' => str_contains($e->getMessage(), 'truncate') ? Nette\Database\ForeignKeyConstraintViolationException::class : null,
			'23502' => Nette\Database\NotNullConstraintViolationException::class,
			'23503' => Nette\Database\ForeignKeyConstraintViolationException::class,
			'23505' => Nette\Database\UniqueConstraintViolationException::class,
			'23514' => Nette\Database\CheckConstraintViolationException::class,
			'40001', '40P01' => Nette\Database\DeadlockException::class,
			'55P03' => Nette\Database\LockTimeoutException::class,
			'08003', '08006',
			'57P01', // admin_shutdown, e.g. pg_terminate_backend() or server restart
			'57P02', // crash_shutdown
			'57P03' /* cannot_connect_now */ => Nette\Database\ConnectionLostException::class,
			'HY000' => str_contains($e->getMessage(), 'server closed the connection unexpectedly')
				? Nette\Database\ConnectionLostException::class
				: null,
			default => null,
		};
	}


	/********************* SQL ****************d*g**/


	public function delimit(string $name): string
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


	public function applyLimit(string $sql, ?int $limit, ?int $offset): string
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

		return $sql;
	}


	/********************* reflection ****************d*g**/


	public function getTables(): array
	{
		$tables = [];
		$rows = $this->connection->query(<<<'X'
			SELECT DISTINCT ON (c.relname)
				c.relname::varchar AS name,
				c.relkind IN ('v', 'm') AS view,
				n.nspname::varchar || '.' || c.relname::varchar AS "fullName",
				coalesce(d.description, '') AS comment
			FROM
				pg_catalog.pg_class AS c
				JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
				LEFT JOIN pg_catalog.pg_description d ON d.objoid = c.oid AND d.objsubid = 0
			WHERE
				c.relkind IN ('r', 'v', 'm', 'p')
				AND n.nspname = ANY (pg_catalog.current_schemas(FALSE))
			ORDER BY
				c.relname,
				array_position(pg_catalog.current_schemas(FALSE), n.nspname) -- shadowed names resolve in search_path order
			X);

		while ($row = $rows->fetch()) {
			$tables[] = $row;
		}

		return $tables;
	}


	public function getColumns(string $table): array
	{
		$columns = [];
		$rows = $this->connection->query(<<<'X'
			SELECT
				a.attname::varchar AS name,
				c.relname::varchar AS table,
				upper(t.typname) AS "nativeType",
				CASE
					WHEN a.atttypid IN (1700, 1231) THEN ((a.atttypmod - 4) >> 16) & 65535  -- precision for numeric/decimal
					WHEN a.atttypmod > 0 THEN a.atttypmod - 4  -- length for varchar etc.
					WHEN t.typlen > 0 THEN t.typlen  -- length for fixed-length types
					ELSE NULL
				END AS size,
				CASE
					WHEN a.atttypid IN (1700, 1231) THEN (a.atttypmod - 4) & 65535
					ELSE null
				END AS scale,
				NOT (a.attnotnull OR t.typtype = 'd' AND t.typnotnull) AS nullable,
				pg_catalog.pg_get_expr(ad.adbin, ad.adrelid)::varchar AS default,
				coalesce(co.contype = 'p' AND (seq.relname IS NOT NULL OR strpos(pg_catalog.pg_get_expr(ad.adbin, ad.adrelid), 'nextval') = 1), FALSE) AS "autoIncrement",
				coalesce(co.contype = 'p', FALSE) AS primary,
				coalesce(col_description(c.oid, a.attnum)::varchar, '') AS comment,
				coalesce(seq.relname, substring(pg_catalog.pg_get_expr(ad.adbin, ad.adrelid) from 'nextval[(]''"?([^''"]+)')) AS sequence
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
				AND c.oid = ?::regclass
				AND a.attnum > 0
				AND NOT a.attisdropped
			ORDER BY
				a.attnum
			X, [$this->delimitFQN($table)]);

		while ($row = $rows->fetch()) {
			$column = $row;
			$column['vendor'] = $column;
			unset($column['sequence']);

			$columns[] = $column;
		}

		return $columns;
	}


	public function getIndexes(string $table): array
	{
		$indexes = [];
		$rows = $this->connection->query(<<<'X'
			SELECT
				c2.relname::varchar AS name,
				i.indisunique AS unique,
				i.indisprimary AS primary,
				coalesce(a.attname, pg_catalog.pg_get_indexdef(i.indexrelid, k.ord::int, TRUE))::varchar AS column -- expression part when attnum = 0
			FROM
				pg_catalog.pg_class AS c1
				JOIN pg_catalog.pg_index AS i ON c1.oid = i.indrelid
				JOIN pg_catalog.pg_class AS c2 ON i.indexrelid = c2.oid
				CROSS JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS k(attnum, ord)
				LEFT JOIN pg_catalog.pg_attribute AS a ON c1.oid = a.attrelid AND a.attnum = k.attnum
			WHERE
				c1.relkind IN ('r', 'p')
				AND c1.oid = ?::regclass
			ORDER BY c2.relname, k.ord
			X, [$this->delimitFQN($table)]);

		while ($row = $rows->fetch()) {
			$id = (string) $row['name'];
			$indexes[$id] ??= [
				'name' => $id,
				'unique' => (bool) $row['unique'],
				'primary' => (bool) $row['primary'],
				'columns' => [],
			];
			$indexes[$id]['columns'][] = (string) $row['column'];
		}

		return array_values($indexes);
	}


	public function getForeignKeys(string $table): array
	{
		/* Doesn't work with multi-column foreign keys */
		$keys = [];
		$rows = $this->connection->query(<<<'X'
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
				AND cl.oid = ?::regclass
				AND nf.nspname = ANY (pg_catalog.current_schemas(FALSE))
			X, [$this->delimitFQN($table)]);

		while ($row = $rows->fetch()) {
			$id = $row['name'];
			$keys[$id]['name'] = $id;
			$keys[$id]['local'][] = $row['local'];
			$keys[$id]['table'] = $row['table'];
			$keys[$id]['foreign'][] = $row['foreign'];
		}

		return array_values($keys);
	}


	public function convertToPhp(mixed $value, array $meta, TypeConverter $converter): mixed
	{
		return $meta['nativeType'] === 'bool'
			? ($value && $value !== 'f' && $value !== 'F')
			: $converter->convertToPhp($value, $meta);
	}


	/**
	 * Converts: schema.name => "schema"."name"
	 */
	private function delimitFQN(string $name): string
	{
		return implode('.', array_map($this->delimit(...), explode('.', $name)));
	}
}
