<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Drivers;

use Nette;


/**
 * Supplemental Oracle database driver.
 */
class OciDriver implements Nette\Database\ISupplementalDriver
{
	use Nette\SmartObject;

	/** @var Nette\Database\Connection */
	private $connection;

	/** @var string  Datetime format */
	private $fmtDateTime;


	public function __construct(Nette\Database\Connection $connection, array $options)
	{
		$this->connection = $connection;
		$this->fmtDateTime = isset($options['formatDateTime']) ? $options['formatDateTime'] : 'U';
	}


	public function convertException(\PDOException $e)
	{
		$code = isset($e->errorInfo[1]) ? $e->errorInfo[1] : null;
		if (in_array($code, [1, 2299, 38911], true)) {
			return Nette\Database\UniqueConstraintViolationException::from($e);

		} elseif (in_array($code, [1400], true)) {
			return Nette\Database\NotNullConstraintViolationException::from($e);

		} elseif (in_array($code, [2266, 2291, 2292], true)) {
			return Nette\Database\ForeignKeyConstraintViolationException::from($e);

		} else {
			return Nette\Database\DriverException::from($e);
		}
	}


	/********************* SQL ****************d*g**/


	public function delimite($name)
	{
		// @see http://download.oracle.com/docs/cd/B10500_01/server.920/a96540/sql_elements9a.htm
		return '"' . str_replace('"', '""', $name) . '"';
	}


	public function formatBool($value)
	{
		return $value ? '1' : '0';
	}


	public function formatDateTime(/*\DateTimeInterface*/ $value)
	{
		return $value->format($this->fmtDateTime);
	}


	public function formatDateInterval(\DateInterval $value)
	{
		throw new Nette\NotSupportedException;
	}


	public function formatLike($value, $pos)
	{
		throw new Nette\NotImplementedException;
	}


	public function applyLimit(&$sql, $limit, $offset)
	{
		if ($limit < 0 || $offset < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');

		} elseif ($offset) {
			// see http://www.oracle.com/technology/oramag/oracle/06-sep/o56asktom.html
			$sql = 'SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (' . $sql . ') t '
				. ($limit !== null ? 'WHERE ROWNUM <= ' . ((int) $offset + (int) $limit) : '')
				. ') WHERE "__rnum" > ' . (int) $offset;

		} elseif ($limit !== null) {
			$sql = 'SELECT * FROM (' . $sql . ') WHERE ROWNUM <= ' . (int) $limit;
		}
	}


	public function normalizeRow($row)
	{
		return $row;
	}


	/********************* reflection ****************d*g**/


	public function getTables()
	{
		$tables = [];
		foreach ($this->connection->query('SELECT * FROM cat') as $row) {
			if ($row[1] === 'TABLE' || $row[1] === 'VIEW') {
				$tables[] = [
					'name' => $row[0],
					'view' => $row[1] === 'VIEW',
				];
			}
		}
		return $tables;
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
		return [];
	}


	public function isSupported($item)
	{
		return $item === self::SUPPORT_SEQUENCE || $item === self::SUPPORT_SUBSELECT;
	}
}
