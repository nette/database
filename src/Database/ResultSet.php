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

	/** @var IRowNormalizer */
	private $rowNormalizer;

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

	/** @var callable|NULL */
	private $rowFactory;


	public function __construct(Connection $connection, $queryString, array $params, IRowNormalizer $normalizer)
	{
		$time = microtime(TRUE);
		$this->connection = $connection;
		$this->supplementalDriver = $connection->getSupplementalDriver();
		$this->queryString = $queryString;
		$this->params = $params;
		$this->rowNormalizer = $normalizer;

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
	 * @return array
	 */
	public function getColumnTypes()
	{
		if ($this->types === NULL) {
			$this->types = (array) $this->supplementalDriver->getColumnTypes($this->pdoStatement);
		}
		return $this->types;
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
	 * @param IRowNormalizer|NULL
	 * @return static
	 */
	public function setRowNormalizer($normalizer)
	{
		$this->rowNormalizer = $normalizer;
		return $this;
	}


	/**
	 * Set a factory to create fetched object instances. These should implements the IRow interface.
	 * @return self
	 */
	public function setRowFactory(callable $callback)
	{
		$this->rowFactory = $callback;
		return $this;
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
		}

		if ($this->rowNormalizer !== NULL) {
			$data = $this->rowNormalizer->normalizeRow($data, $this);
		}

		if ($this->rowFactory) {
			$row = call_user_func($this->rowFactory, $data);
		}
		else {
			$row = new Row;
			foreach ($data as $key => $value) {
				if ($key !== '') {
					$row->$key = $value;
				}
			}
		}

		if ($this->result === NULL && count($data) !== $this->pdoStatement->columnCount()) {
			$duplicates = Helpers::findDuplicates($this->pdoStatement);
			trigger_error("Found duplicate columns in database result set: $duplicates.", E_USER_NOTICE);
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
