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
	private ?\PDOStatement $pdoStatement = null;

	/** @var callable(array, ResultSet): array */
	private $normalizer;
	private Row|false|null $lastRow = null;
	private int $lastRowKey = -1;

	/** @var Row[] */
	private array $rows;
	private float $time;
	private array $types;


	public function __construct(
		private readonly Connection $connection,
		private readonly string $queryString,
		private readonly array $params,
		?callable $normalizer = null,
	) {
		$time = microtime(true);
		$this->normalizer = $normalizer;
		$types = ['boolean' => PDO::PARAM_BOOL, 'integer' => PDO::PARAM_INT, 'resource' => PDO::PARAM_LOB, 'NULL' => PDO::PARAM_NULL];

		try {
			if (str_starts_with($queryString, '::')) {
				$connection->getPdo()->{substr($queryString, 2)}();
			} else {
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


	/** @deprecated */
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
		$this->types ??= $this->connection->getDriver()->getColumnTypes($this->pdoStatement);
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
		if ($this->lastRow === false) {
			throw new Nette\InvalidStateException(self::class . ' implements only one way iterator.');
		}
	}


	public function current(): Row|false|null
	{
		return $this->lastRow;
	}


	public function key(): int
	{
		return $this->lastRowKey;
	}


	public function next(): void
	{
		$this->lastRow = false;
	}


	public function valid(): bool
	{
		if ($this->lastRow) {
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

		} elseif ($this->lastRow === null && count($data) !== $this->pdoStatement->columnCount()) {
			$duplicates = Helpers::findDuplicates($this->pdoStatement);
			trigger_error("Found duplicate columns in database result set: $duplicates.");
		}

		$row = new Row;
		foreach ($this->normalizeRow($data) as $key => $value) {
			if ($key !== '') {
				$row->$key = $value;
			}
		}

		$this->lastRowKey++;
		return $this->lastRow = $row;
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
	 */
	public function fetchPairs(string|int|\Closure|null $keyOrCallback = null, string|int|null $value = null): array
	{
		return Helpers::toPairs($this->fetchAll(), $keyOrCallback, $value);
	}


	/**
	 * Fetches all rows.
	 * @return Row[]
	 */
	public function fetchAll(): array
	{
		$this->rows ??= iterator_to_array($this);
		return $this->rows;
	}


	/**
	 * Fetches all rows and returns associative tree.
	 */
	public function fetchAssoc(string $path): array
	{
		return Nette\Utils\Arrays::associate($this->fetchAll(), $path);
	}
}
