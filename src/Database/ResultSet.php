<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;
use PDO;


/**
 * Represents a result set.
 */
class ResultSet implements \Iterator, IRowContainer
{
	use Nette\SmartObject;

	/** @var Connection */
	private $connection;

	/** @var ISupplementalDriver */
	private $supplementalDriver;

	/** @var \PDOStatement|NULL */
	private $pdoStatement;

	/** @var IRow */
	private $result;

	/** @var int */
	private $resultKey = -1;

	/** @var IRow[] */
	private $results;

	/** @var float */
	private $time;

	/** @var string */
	private $queryString;

	/** @var array */
	private $params;

	/** @var array */
	private $types;


	public function __construct(Connection $connection, $queryString, array $params)
	{
		$time = microtime(TRUE);
		$this->connection = $connection;
		$this->supplementalDriver = $connection->getSupplementalDriver();
		$this->queryString = $queryString;
		$this->params = $params;

		try {
			if (substr($queryString, 0, 2) === '::') {
				$connection->getPdo()->{substr($queryString, 2)}();
			} elseif ($queryString !== NULL) {
				static $types = ['boolean' => PDO::PARAM_BOOL, 'integer' => PDO::PARAM_INT,
					'resource' => PDO::PARAM_LOB, 'NULL' => PDO::PARAM_NULL];
				$this->pdoStatement = $connection->getPdo()->prepare($queryString);
				foreach ($params as $key => $value) {
					$type = gettype($value);
					$this->pdoStatement->bindValue(is_int($key) ? $key + 1 : $key, $value, isset($types[$type]) ? $types[$type] : PDO::PARAM_STR);
				}
				$this->pdoStatement->setFetchMode(PDO::FETCH_ASSOC);
				$this->pdoStatement->execute();
			}
		} catch (\PDOException $e) {
			$e = $this->supplementalDriver->convertException($e);
			$e->queryString = $queryString;
			throw $e;
		}
		$this->time = microtime(TRUE) - $time;
	}


	/**
	 * @return Connection
	 */
	public function getConnection()
	{
		return $this->connection;
	}


	/**
	 * @internal
	 * @return \PDOStatement
	 */
	public function getPdoStatement()
	{
		return $this->pdoStatement;
	}


	/**
	 * @return string
	 */
	public function getQueryString()
	{
		return $this->queryString;
	}


	/**
	 * @return array
	 */
	public function getParameters()
	{
		return $this->params;
	}


	/**
	 * @return int
	 */
	public function getColumnCount()
	{
		return $this->pdoStatement ? $this->pdoStatement->columnCount() : NULL;
	}


	/**
	 * @return int
	 */
	public function getRowCount()
	{
		return $this->pdoStatement ? $this->pdoStatement->rowCount() : NULL;
	}


	/**
	 * @return float
	 */
	public function getTime()
	{
		return $this->time;
	}


	/**
	 * Normalizes result row.
	 * @param  array
	 * @return array
	 */
	public function normalizeRow($row)
	{
		if ($this->types === NULL) {
			$this->types = (array) $this->supplementalDriver->getColumnTypes($this->pdoStatement);
		}

		foreach ($this->types as $key => $type) {
			$value = $row[$key];
			if ($value === NULL || $value === FALSE || $type === IStructure::FIELD_TEXT) {

			} elseif ($type === IStructure::FIELD_INTEGER) {
				$row[$key] = is_float($tmp = $value * 1) ? $value : $tmp;

			} elseif ($type === IStructure::FIELD_FLOAT) {
				if (($pos = strpos($value, '.')) !== FALSE) {
					$value = rtrim(rtrim($pos === 0 ? "0$value" : $value, '0'), '.');
				}
				$float = (float) $value;
				$row[$key] = (string) $float === $value ? $float : $value;

			} elseif ($type === IStructure::FIELD_BOOL) {
				$row[$key] = ((bool) $value) && $value !== 'f' && $value !== 'F';

			} elseif ($type === IStructure::FIELD_DATETIME || $type === IStructure::FIELD_DATE || $type === IStructure::FIELD_TIME) {
				$row[$key] = new Nette\Utils\DateTime($value);

			} elseif ($type === IStructure::FIELD_TIME_INTERVAL) {
				preg_match('#^(-?)(\d+)\D(\d+)\D(\d+)\z#', $value, $m);
				$row[$key] = new \DateInterval("PT$m[2]H$m[3]M$m[4]S");
				$row[$key]->invert = (int) (bool) $m[1];

			} elseif ($type === IStructure::FIELD_UNIX_TIMESTAMP) {
				$row[$key] = Nette\Utils\DateTime::from($value);
			}
		}

		return $this->supplementalDriver->normalizeRow($row);
	}


	/********************* misc tools ****************d*g**/


	/**
	 * Displays complete result set as HTML table for debug purposes.
	 * @return void
	 */
	public function dump()
	{
		Helpers::dumpResult($this);
	}


	/********************* interface Iterator ****************d*g**/


	public function rewind()
	{
		if ($this->result === FALSE) {
			throw new Nette\InvalidStateException('Nette\\Database\\ResultSet implements only one way iterator.');
		}
	}


	public function current()
	{
		return $this->result;
	}


	public function key()
	{
		return $this->resultKey;
	}


	public function next()
	{
		$this->result = FALSE;
	}


	public function valid()
	{
		if ($this->result) {
			return TRUE;
		}

		return $this->fetch() !== FALSE;
	}


	/********************* interface IRowContainer ****************d*g**/


	/**
	 * @inheritDoc
	 */
	public function fetch()
	{
		$data = $this->pdoStatement ? $this->pdoStatement->fetch() : NULL;
		if (!$data) {
			$this->pdoStatement->closeCursor();
			return FALSE;

		} elseif ($this->result === NULL && count($data) !== $this->pdoStatement->columnCount()) {
			$duplicates = Helpers::findDuplicates($this->pdoStatement);
			trigger_error("Found duplicate columns in database result set: $duplicates.", E_USER_NOTICE);
		}

		$row = new Row;
		foreach ($this->normalizeRow($data) as $key => $value) {
			if ($key !== '') {
				$row->$key = $value;
			}
		}

		$this->resultKey++;
		return $this->result = $row;
	}


	/**
	 * Fetches single field.
	 * @param  int
	 * @return mixed|FALSE
	 */
	public function fetchField($column = 0)
	{
		$row = $this->fetch();
		return $row ? $row[$column] : FALSE;
	}


	/**
	 * @inheritDoc
	 */
	public function fetchPairs($key = NULL, $value = NULL)
	{
		return Helpers::toPairs($this->fetchAll(), $key, $value);
	}


	/**
	 * @inheritDoc
	 */
	public function fetchAll()
	{
		if ($this->results === NULL) {
			$this->results = iterator_to_array($this);
		}
		return $this->results;
	}


	/**
	 * @inheritDoc
	 */
	public function fetchAssoc($path)
	{
		return Nette\Utils\Arrays::associate($this->fetchAll(), $path);
	}

}
