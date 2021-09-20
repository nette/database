<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette;


/**
 * Represents a result set.
 */
class ResultSet implements \Iterator, IRowContainer
{
	private ?ResultDriver $result = null;

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

		$driver = $connection->getDriver();
		if (str_starts_with($queryString, '::')) {
			$driver->{substr($queryString, 2)}();
		} else {
			$this->result = $driver->query($queryString, $params);
		}

		$this->time = microtime(true) - $time;
	}


	/** @deprecated */
	public function getConnection(): Connection
	{
		throw new Nette\DeprecatedException(__METHOD__ . '() is deprecated.');
	}


	/**
	 * @internal
	 */
	public function getPdoStatement(): ?\PDOStatement
	{
		return $this->result->getPDOStatement();
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
		return $this->result?->getColumnCount();
	}


	public function getRowCount(): ?int
	{
		return $this->result?->getRowCount();
	}


	public function getColumnTypes(): array
	{
		$this->types ??= $this->result->getColumnTypes();
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
		$data = $this->result?->fetch();
		if ($data === null) {
			return null;

		} elseif ($this->lastRow === null && count($data) !== $this->result->getColumnCount()) {
			$duplicates = Helpers::findDuplicates($this->result);
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


	/** @internal */
	public function fetchArray(): ?array
	{
		return $this->result?->fetch();
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
	public function fetchPairs(string|int|null $key = null, string|int|null $value = null): array
	{
		return Helpers::toPairs($this->fetchAll(), $key, $value);
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
