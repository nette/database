<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette;
use Nette\Utils\Arrays;


/**
 * Represents a result set.
 */
class Result implements \Iterator
{
	private Row|false|null $lastRow = null;
	private int $lastRowKey = -1;

	/** @var Row[] */
	private array $rows;
	private array $meta;


	public function __construct(
		private readonly Connection $connection,
		private readonly string $queryString,
		private readonly array $params,
		private readonly ?Drivers\Result $result,
		private float $time,
	) {
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
	 * Returns the next row as an object Row or null if there are no more rows.
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
	 * Returns the next row as indexes array or null if there are no more rows.
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


	private function normalizeRow(array $row): array
	{
		$engine = $this->connection->getDatabaseEngine();
		$converter = $this->connection->getTypeConverter();
		$columnsMeta = $this->meta ??= $this->getColumnsMeta();
		foreach ($row as $key => $value) {
			$row[$key] = isset($value, $columnsMeta[$key])
				? $engine->convertToPhp($value, $columnsMeta[$key], $converter)
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
