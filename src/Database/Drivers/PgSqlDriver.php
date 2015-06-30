<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Database\Drivers;

use Nette;


/**
 * Supplemental PostgreSQL database driver.
 */
class PgSqlDriver extends Nette\Object implements Nette\Database\ISupplementalDriver
{
	/** @var Nette\Database\Connection */
	private $connection;


	public function __construct(Nette\Database\Connection $connection, array $options)
	{
		$this->connection = $connection;
	}


	public function convertException(\PDOException $e)
	{
		$code = isset($e->errorInfo[0]) ? $e->errorInfo[0] : NULL;
		if ($code === '0A000' && strpos($e->getMessage(), 'truncate') !== FALSE) {
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


	/**
	 * Delimites identifier for use in a SQL statement.
	 */
	public function delimite($name)
	{
		// @see http://www.postgresql.org/docs/8.2/static/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
		return '"' . str_replace('"', '""', $name) . '"';
	}


	/**
	 * Formats boolean for use in a SQL statement.
	 */
	public function formatBool($value)
	{
		return $value ? 'TRUE' : 'FALSE';
	}


	/**
	 * Formats date-time for use in a SQL statement.
	 */
	public function formatDateTime(/*\DateTimeInterface*/ $value)
	{
		return $value->format("'Y-m-d H:i:s'");
	}


	/**
	 * Formats date-time interval for use in a SQL statement.
	 */
	public function formatDateInterval(\DateInterval $value)
	{
		throw new Nette\NotSupportedException;
	}


	/**
	 * Encodes string for use in a LIKE statement.
	 */
	public function formatLike($value, $pos)
	{
		$bs = substr($this->connection->quote('\\', \PDO::PARAM_STR), 1, -1); // standard_conforming_strings = on/off
		$value = substr($this->connection->quote($value, \PDO::PARAM_STR), 1, -1);
		$value = strtr($value, array('%' => $bs . '%', '_' => $bs . '_', '\\' => '\\\\'));
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}


	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 */
	public function applyLimit(& $sql, $limit, $offset)
	{
		if ($limit >= 0) {
			$sql .= ' LIMIT ' . (int) $limit;
		}
		if ($offset > 0) {
			$sql .= ' OFFSET ' . (int) $offset;
		}
	}


	/**
	 * Normalizes result row.
	 */
	public function normalizeRow($row)
	{
		return $row;
	}


	/********************* reflection ****************d*g**/


	/**
	 * Returns list of tables.
	 */
	public function getTables()
	{
		$tables = array();
		foreach ($this->connection->query("
			SELECT DISTINCT ON (c.relname)
				c.relname::varchar AS name,
				c.relkind = 'v' AS view,
				n.nspname::varchar || '.' || c.relname::varchar AS \"fullName\"
			FROM
				pg_catalog.pg_class AS c
				JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
			WHERE
				c.relkind IN ('r', 'v')
				AND n.nspname = ANY (pg_catalog.current_schemas(FALSE))
			ORDER BY
				c.relname
		") as $row) {
			$tables[] = (array) $row;
		}

		return $tables;
	}


	/**
	 * Returns metadata for all columns in a table.
	 */
	public function getColumns($table)
	{
		$columns = array();
		foreach ($this->connection->query("
			SELECT
				a.attname::varchar AS name,
				c.relname::varchar AS table,
				upper(t.typname) AS nativetype,
				CASE WHEN a.atttypmod = -1 THEN NULL ELSE a.atttypmod -4 END AS size,
				FALSE AS unsigned,
				NOT (a.attnotnull OR t.typtype = 'd' AND t.typnotnull) AS nullable,
				pg_catalog.pg_get_expr(ad.adbin, 'pg_catalog.pg_attrdef'::regclass)::varchar AS default,
				coalesce(co.contype = 'p' AND strpos(ad.adsrc, 'nextval') = 1, FALSE) AS autoincrement,
				coalesce(co.contype = 'p', FALSE) AS primary,
				substring(pg_catalog.pg_get_expr(ad.adbin, 'pg_catalog.pg_attrdef'::regclass) from 'nextval[(]''\"?([^''\"]+)') AS sequence
			FROM
				pg_catalog.pg_attribute AS a
				JOIN pg_catalog.pg_class AS c ON a.attrelid = c.oid
				JOIN pg_catalog.pg_type AS t ON a.atttypid = t.oid
				LEFT JOIN pg_catalog.pg_attrdef AS ad ON ad.adrelid = c.oid AND ad.adnum = a.attnum
				LEFT JOIN pg_catalog.pg_constraint AS co ON co.connamespace = c.relnamespace AND contype = 'p' AND co.conrelid = c.oid AND a.attnum = ANY(co.conkey)
			WHERE
				c.relkind IN ('r', 'v')
				AND c.oid = {$this->connection->quote($this->delimiteFQN($table))}::regclass
				AND a.attnum > 0
				AND NOT a.attisdropped
			ORDER BY
				a.attnum
		") as $row) {
			$column = (array) $row;
			$column['vendor'] = $column;
			unset($column['sequence']);

			$columns[] = $column;
		}

		return $columns;
	}


	/**
	 * Returns metadata for all indexes in a table.
	 */
	public function getIndexes($table)
	{
		$indexes = array();
		foreach ($this->connection->query("
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
				c1.relkind = 'r'
				AND c1.oid = {$this->connection->quote($this->delimiteFQN($table))}::regclass
		") as $row) {
			$indexes[$row['name']]['name'] = $row['name'];
			$indexes[$row['name']]['unique'] = $row['unique'];
			$indexes[$row['name']]['primary'] = $row['primary'];
			$indexes[$row['name']]['columns'][] = $row['column'];
		}

		return array_values($indexes);
	}


	/**
	 * Returns metadata for all foreign keys in a table.
	 */
	public function getForeignKeys($table)
	{
		/* Does't work with multicolumn foreign keys */
		return $this->connection->query("
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
		")->fetchAll();
	}


	/**
	 * Returns associative array of detected types (IReflection::FIELD_*) in result set.
	 */
	public function getColumnTypes(\PDOStatement $statement)
	{
		return Nette\Database\Helpers::detectTypes($statement);
	}


	/**
	 * @param  string
	 * @return bool
	 */
	public function isSupported($item)
	{
		return $item === self::SUPPORT_SEQUENCE || $item === self::SUPPORT_SUBSELECT || $item === self::SUPPORT_SCHEMA;
	}


	/**
	 * Converts: schema.name => "schema"."name"
	 * @param  string
	 * @return string
	 */
	private function delimiteFQN($name)
	{
		return implode('.', array_map(array($this, 'delimite'), explode('.', $name)));
	}

}
