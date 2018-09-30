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


	public function delimite($name)
	{
		// @see https://msdn.microsoft.com/en-us/library/ms176027.aspx
		return '[' . str_replace(['[', ']'], ['[[', ']]'], $name) . ']';
	}


	public function formatBool($value)
	{
		return $value ? '1' : '0';
	}


	public function formatDateTime(/*\DateTimeInterface*/ $value)
	{
		return $value->format("'Y-m-d H:i:s'");
	}


	public function formatDateInterval(\DateInterval $value)
	{
		throw new Nette\NotSupportedException;
	}


	public function formatLike($value, $pos)
	{
		$value = strtr($value, ["'" => "''", '%' => '[%]', '_' => '[_]', '[' => '[[]']);
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}


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


	public function normalizeRow($row)
	{
		return $row;
	}


	/********************* reflection ****************d*g**/


	public function getTables()
	{
		throw new Nette\NotImplementedException;
	}


	public function getColumns($table)
	{
		throw new Nette\NotImplementedException;
	}


	public function getIndexes($table)
	{
		throw new Nette\NotImplementedException;
	}


	public function getForeignKeys($table)
	{
		throw new Nette\NotImplementedException;
	}


	public function getColumnTypes(\PDOStatement $statement)
	{
		return Nette\Database\Helpers::detectTypes($statement);
	}


	public function isSupported($item)
	{
		return $item === self::SUPPORT_SUBSELECT;
	}
}
