<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette;
use Nette\Utils\Arrays;
use function array_values, count, gettype, is_int, iterator_to_array, microtime, reset, str_starts_with, substr;


/**
 * Represents a database result set.
 */
class Result implements \Iterator
{
	private ?Drivers\Result $result = null;
	private Row|false|null $lastRow = null;
	private int $lastRowKey = -1;

	/** @var Row[] */
	private array $rows;
	private float $time;
	private array $meta;


	public function __construct(
		private readonly Connection $connection,
		private readonly string $queryString,
		private readonly array $params,
	) {
		$time = microtime(true);


		try {
			if (str_starts_with($queryString, '::')) {
				$connection->getConnection()->{substr($queryString, 2)}();
			} else {
				$this->result = $connection->getConnection()->query($queryString, $params);
			}
		} catch (\PDOException $e) {
			$e = $connection->getDatabaseEngine()->convertException($e);
			$e->queryString = $queryString;
			$e->params = $params;
			throw $e;
		}

		$this->time = microtime(true) - $time;
	}


	/** @deprecated */
	public function getConnection(): Connection
	{
		trigger_error(__METHOD__ . '() is deprecated.', E_USER_DEPRECATED);
		return $this->connection;
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


	public function getTime(): float
	{
		return $this->time;
	}


	/********************* misc tools ****************d*g**/


	/**
	 * Displays result set as HTML table.
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


	/********************* fetch ****************d*g**/


	/**
	 * Returns the next row as an associative array or null if there are no more rows.
	 */
	public function fetchAssoc(?string $path = null): ?array
	{
		if ($path !== null) {
			return Arrays::associate($this->fetchAll(), $path);
		}

		$data = $this->result?->fetch();
		if ($data === null) {
			return null;

		} elseif ($this->lastRow === null && count($data) !== $this->result->getColumnCount()) {
			$duplicates = array_filter(array_count_values(array_column($this->result->getColumnsInfo(), 'name')), fn($val) => $val > 1);
			trigger_error("Found duplicate columns in database result set: '" . implode("', '", array_keys($duplicates)) . "'.");
		}

		return $this->normalizeRow($data);
	}


	/**
	 * Returns the next row as a Row object or null if there are no more rows.
	 */
	public function fetch(): ?Row
	{
		$data = $this->fetchAssoc();
		if ($data === null) {
			return null;
		}

		$this->lastRowKey++;
		return $this->lastRow = Arrays::toObject($data, new Row);
	}


	/**
	 * Returns the first field of the next row or null if there are no more rows.
	 */
	public function fetchField(): mixed
	{
		$row = $this->fetchAssoc();
		return $row ? reset($row) : null;
	}


	/**
	 * Returns the next row as indexed array or null if there are no more rows.
	 */
	public function fetchList(): ?array
	{
		$row = $this->fetchAssoc();
		return $row ? array_values($row) : null;
	}


	/**
	 * Alias for fetchList().
	 */
	public function fetchFields(): ?array
	{
		return $this->fetchList();
	}


	/**
	 * Returns all rows as associative array, where first argument specifies key column and second value column.
	 * For duplicate keys, the last value is used. When using null as key, array is indexed from zero.
	 * Alternatively accepts callback returning value or key-value pairs.
	 */
	public function fetchPairs(string|int|\Closure|null $keyOrCallback = null, string|int|null $value = null): array
	{
		return Helpers::toPairs($this->fetchAll(), $keyOrCallback, $value);
	}


	/**
	 * Returns all remaining rows as array of Row objects.
	 * @return Row[]
	 */
	public function fetchAll(): array
	{
		$this->rows ??= iterator_to_array($this);
		return $this->rows;
	}


	private function normalizeRow(array $row): array
	{
		$engine = $this->connection->getDatabaseEngine();
		$converter = $this->connection->getTypeConverter();
		$this->meta ??= $this->getColumnsMeta();
		foreach ($row as $key => $value) {
			$row[$key] = isset($value, $this->meta[$key])
				? $engine->convertToPhp($value, $this->meta[$key], $converter)
				: $value;
		}
		return $row;
	}


	private function getColumnsMeta(): array
	{
		$res = [];
		foreach ($this->result->getColumnsInfo() as $meta) {
			$res[$meta['name']] = $meta;
		}
		return $res;
	}
}


class_exists(ResultSet::class);
