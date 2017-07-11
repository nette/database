<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Drivers;

use Nette;


/**
 * Supplemental MS SQL database driver.
 */
class MsSqlDriver implements Nette\Database\ISupplementalDriver
{
	use Nette\SmartObject;

	public function convertException(\PDOException $e)
	{
		return Nette\Database\DriverException::from($e);
	}


	/********************* SQL ****************d*g**/


	/**
	 * Delimites identifier for use in a SQL statement.
	 */
	public function delimite($name)
	{
		// @see https://msdn.microsoft.com/en-us/library/ms176027.aspx
		return '[' . str_replace(['[', ']'], ['[[', ']]'], $name) . ']';
	}


	/**
	 * Formats boolean for use in a SQL statement.
	 */
	public function formatBool($value)
	{
		return $value ? '1' : '0';
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
		$value = strtr($value, ["'" => "''", '%' => '[%]', '_' => '[_]', '[' => '[[]']);
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}


	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 */
	public function applyLimit(&$sql, $limit, $offset)
	{
		if ($offset) {
			throw new Nette\NotSupportedException('Offset is not supported by this database.');

		} elseif ($limit < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');

		} elseif ($limit !== null) {
			$sql = preg_replace('#^\s*(SELECT(\s+DISTINCT|\s+ALL)?|UPDATE|DELETE)#i', '$0 TOP ' . (int) $limit, $sql, 1, $count);
			if (!$count) {
				throw new Nette\InvalidArgumentException('SQL query must begin with SELECT, UPDATE or DELETE command.');
			}
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
		throw new Nette\NotImplementedException;
	}


	/**
	 * Returns metadata for all columns in a table.
	 */
	public function getColumns($table)
	{
		throw new Nette\NotImplementedException;
	}


	/**
	 * Returns metadata for all indexes in a table.
	 */
	public function getIndexes($table)
	{
		throw new Nette\NotImplementedException;
	}


	/**
	 * Returns metadata for all foreign keys in a table.
	 */
	public function getForeignKeys($table)
	{
		throw new Nette\NotImplementedException;
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
		return $item === self::SUPPORT_SUBSELECT;
	}
}
