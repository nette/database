<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;


/**
 * Supplemental PDO database driver.
 */
interface ISupplementalDriver
{
	const SUPPORT_SEQUENCE = 'sequence',
		SUPPORT_SELECT_UNGROUPED_COLUMNS = 'ungrouped_cols',
		SUPPORT_MULTI_INSERT_AS_SELECT = 'insert_as_select',
		SUPPORT_MULTI_COLUMN_AS_OR_COND = 'multi_column_as_or',
		SUPPORT_SUBSELECT = 'subselect',
		SUPPORT_SCHEMA = 'schema';

	/**
	 * @return DriverException
	 */
	function convertException(\PDOException $e);

	/**
	 * Delimites identifier for use in a SQL statement.
	 * @param  string
	 * @return string
	 */
	function delimite($name);

	/**
	 * Formats boolean for use in a SQL statement.
	 * @param  bool
	 * @return string
	 */
	function formatBool($value);

	/**
	 * Formats date-time for use in a SQL statement.
	 * @return string
	 */
	function formatDateTime(/*\DateTimeInterface*/ $value);

	/**
	 * Formats date-time interval for use in a SQL statement.
	 * @return string
	 */
	//function formatDateInterval(\DateInterval $value);

	/**
	 * Encodes string for use in a LIKE statement.
	 * @param  string
	 * @param  int
	 * @return string
	 */
	function formatLike($value, $pos);

	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 * @param  string  SQL query that will be modified.
	 * @param  int|NULL
	 * @param  int|NULL
	 * @return void
	 */
	function applyLimit(&$sql, $limit, $offset);

	/**
	 * Normalizes result row.
	 * @param  array
	 * @return array
	 */
	function normalizeRow($row);


	/********************* reflection ****************d*g**/


	/**
	 * Returns list of tables.
	 * @return array of [name [, (bool) view]]
	 */
	function getTables();

	/**
	 * Returns metadata for all columns in a table.
	 * @param  string
	 * @return array of [name, nativetype, primary [, table, fullname, (int) size, (bool) nullable, (mixed) default, (bool) autoincrement, (array) vendor]]
	 */
	function getColumns($table);

	/**
	 * Returns metadata for all indexes in a table.
	 * @param  string
	 * @return array of [name, (array of names) columns [, (bool) unique, (bool) primary]]
	 */
	function getIndexes($table);

	/**
	 * Returns metadata for all foreign keys in a table.
	 * @param  string
	 * @return array
	 */
	function getForeignKeys($table);

	/**
	 * Returns associative array of detected types (IStructure::FIELD_*) in result set.
	 * @param  \PDOStatement
	 * @return array
	 */
	function getColumnTypes(\PDOStatement $statement);

	/**
	 * Cheks if driver supports specific property
	 * @param  string self::SUPPORT_* property
	 * @return bool
	 */
	function isSupported($item);

}
