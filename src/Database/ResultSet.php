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

	private Connection $connection;

	private ?\PDOStatement $pdoStatement;

	/** @var callable(array, ResultSet): array */
	private $normalizer;

	private Row|false|null $result = null;

	private int $resultKey = -1;

	/** @var Row[] */
	private array $results;

	private float $time;

	private string $queryString;

	private array $params;

	private array $types;


	public function __construct(Connection $connection, string $queryString, array $params, callable $normalizer = null)
	{
		$time = microtime(true);
		$this->connection = $connection;
		$this->queryString = $queryString;
		$this->params = $params;
		$this->normalizer = $normalizer;

		try {
			if (substr($queryString, 0, 2) === '::') {
				$connection->getPdo()->{substr($queryString, 2)}();
			} elseif ($queryString !== null) {
				static $types = ['boolean' => PDO::PARAM_BOOL, 'integer' => PDO::PARAM_INT,
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


	public function getColumnTypes(): array
	{
		if (!isset($this->types)) {
			$this->types = $this->connection->getDriver()->getColumnTypes($this->pdoStatement);
		}
		return $this->types;
	}


	public function getTime(): float
	{
		return $this->time;
	}


	/** @internal */
	public function normalizeRow(array $row): array
	{
		return $this->normalizer
			? ($this->normalizer)($row, $this)
			: $row;
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

		} elseif (!isset($this->result) && count($data) !== $this->pdoStatement->columnCount()) {
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
	 */
	public function fetchField(): mixed
	{
		$row = $this->fetch();
		return $row ? $row[0] : null;
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
	public function fetchPairs(string|int $key = null, string|int $value = null): array
	{
		return Helpers::toPairs($this->fetchAll(), $key, $value);
	}


	/**
	 * Fetches all rows.
	 * @return Row[]
	 */
	public function fetchAll(): array
	{
		if (!isset($this->results)) {
			$this->results = iterator_to_array($this);
		}
		return $this->results;
	}


	/**
	 * Fetches all rows and returns associative tree.
	 */
	public function fetchAssoc(string $path): array
	{
		return Nette\Utils\Arrays::associate($this->fetchAll(), $path);
	}
}
