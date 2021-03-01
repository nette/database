<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

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

	/** @var \PDOStatement|null */
	private $pdoStatement;

	/** @var Row|false */
	private $result;

	/** @var int */
	private $resultKey = -1;

	/** @var Row[] */
	private $results;

	/** @var float */
	private $time;

	/** @var string */
	private $queryString;

	/** @var array */
	private $params;

	/** @var array */
	private $types;


	public function __construct(Connection $connection, string $queryString, array $params)
	{
		$time = microtime(true);
		$this->connection = $connection;
		$this->queryString = $queryString;
		$this->params = $params;

		try {
			if (substr($queryString, 0, 2) === '::') {
				$connection->getPdo()->{substr($queryString, 2)}();
			} elseif ($queryString !== null) {
				static $types = ['boolean' => PHP_VERSION < 70307 ? PDO::PARAM_INT : PDO::PARAM_BOOL, 'integer' => PDO::PARAM_INT,
					'resource' => PDO::PARAM_LOB, 'NULL' => PDO::PARAM_NULL, ];
				$this->pdoStatement = $connection->getPdo()->prepare($queryString);
				foreach ($params as $key => $value) {
					$type = gettype($value);
					$this->pdoStatement->bindValue(is_int($key) ? $key + 1 : $key, $value, $types[$type] ?? PDO::PARAM_STR);
				}
				$this->pdoStatement->setFetchMode(PDO::FETCH_ASSOC);
				@$this->pdoStatement->execute(); // @ PHP generates warning when ATTR_ERRMODE = ERRMODE_EXCEPTION bug #73878
			}
		} catch (\PDOException $e) {
			$e = $connection->getDriver()->convertException($e);
			$e->queryString = $queryString;
			$e->params = $params;
			throw $e;
		}
		$this->time = microtime(true) - $time;
	}


	public function getConnection(): Connection
	{
		return $this->connection;
	}


	/**
	 * @internal
	 */
	public function getPdoStatement(): ?\PDOStatement
	{
		return $this->pdoStatement;
	}


	public function getQueryString(): string
	{
		return $this->queryString;
	}


	public function getParameters(): array
	{
		return $this->params;
	}


	public function getColumnCount(): ?int
	{
		return $this->pdoStatement ? $this->pdoStatement->columnCount() : null;
	}


	public function getRowCount(): ?int
	{
		return $this->pdoStatement ? $this->pdoStatement->rowCount() : null;
	}


	public function getTime(): float
	{
		return $this->time;
	}


	/**
	 * Normalizes result row.
	 */
	public function normalizeRow(array $row): array
	{
		if ($this->types === null) {
			$this->types = $this->connection->getDriver()->getColumnTypes($this->pdoStatement);
		}

		foreach ($this->types as $key => $type) {
			$value = $row[$key];
			if ($value === null || $value === false || $type === IStructure::FIELD_TEXT) {
				// do nothing
			} elseif ($type === IStructure::FIELD_INTEGER) {
				$row[$key] = is_float($tmp = $value * 1) ? $value : $tmp;

			} elseif ($type === IStructure::FIELD_FLOAT) {
				if (is_string($value) && ($pos = strpos($value, '.')) !== false) {
					$value = rtrim(rtrim($pos === 0 ? "0$value" : $value, '0'), '.');
				}
				$float = (float) $value;
				$row[$key] = (string) $float === $value ? $float : $value;

			} elseif ($type === IStructure::FIELD_BOOL) {
				$row[$key] = ((bool) $value) && $value !== 'f' && $value !== 'F';

			} elseif (
				$type === IStructure::FIELD_DATETIME
				|| $type === IStructure::FIELD_DATE
				|| $type === IStructure::FIELD_TIME
			) {
				$row[$key] = new Nette\Utils\DateTime($value);

			} elseif ($type === IStructure::FIELD_TIME_INTERVAL) {
				preg_match('#^(-?)(\d+)\D(\d+)\D(\d+)(\.\d+)?$#D', $value, $m);
				$row[$key] = new \DateInterval("PT$m[2]H$m[3]M$m[4]S");
				$row[$key]->f = isset($m[5]) ? (float) $m[5] : 0.0;
				$row[$key]->invert = (int) (bool) $m[1];

			} elseif ($type === IStructure::FIELD_UNIX_TIMESTAMP) {
				$row[$key] = Nette\Utils\DateTime::from($value);
			}
		}

		return $row;
	}


	/********************* misc tools ****************d*g**/


	/**
	 * Displays complete result set as HTML table for debug purposes.
	 */
	public function dump(): void
	{
		Helpers::dumpResult($this);
	}


	/********************* interface Iterator ****************d*g**/


	public function rewind(): void
	{
		if ($this->result === false) {
			throw new Nette\InvalidStateException(self::class . ' implements only one way iterator.');
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


	public function next(): void
	{
		$this->result = false;
	}


	public function valid(): bool
	{
		if ($this->result) {
			return true;
		}

		return $this->fetch() !== null;
	}


	/**
	 * Fetches single row object.
	 */
	public function fetch(): ?Row
	{
		$data = $this->pdoStatement ? $this->pdoStatement->fetch() : null;
		if (!$data) {
			$this->pdoStatement->closeCursor();
			return null;

		} elseif ($this->result === null && count($data) !== $this->pdoStatement->columnCount()) {
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
	 * @return mixed
	 */
	public function fetchField($column = 0)
	{
		if (func_num_args()) {
			trigger_error(__METHOD__ . '() argument is deprecated.', E_USER_DEPRECATED);
		}
		$row = $this->fetch();
		return $row ? $row[$column] : null;
	}


	/**
	 * Fetches array of fields.
	 */
	public function fetchFields(): ?array
	{
		$row = $this->fetch();
		return $row ? array_values((array) $row) : null;
	}


	/**
	 * Fetches all rows as associative array.
	 * @param  string|int  $key  column name used for an array key or null for numeric index
	 * @param  string|int  $value  column name used for an array value or null for the whole row
	 */
	public function fetchPairs($key = null, $value = null): array
	{
		return Helpers::toPairs($this->fetchAll(), $key, $value);
	}


	/**
	 * Fetches all rows.
	 * @return Row[]
	 */
	public function fetchAll(): array
	{
		if ($this->results === null) {
			$this->results = iterator_to_array($this);
		}
		return $this->results;
	}


	/**
	 * Fetches all rows and returns associative tree.
	 * @param  string  $path  associative descriptor
	 */
	public function fetchAssoc(string $path): array
	{
		return Nette\Utils\Arrays::associate($this->fetchAll(), $path);
	}
}
