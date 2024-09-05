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
class Result implements \IteratorAggregate
{
	private bool $fetched = false;

	/** @var Row[] */
	private array $rows;
	private array $meta;


	public function __construct(
		private readonly Explorer $explorer,
		private readonly SqlLiteral $query,
		private readonly ?Drivers\Result $result,
		private float $time,
	) {
	}


	/** @deprecated */
	public function getConnection(): Explorer
	{
		trigger_error(__METHOD__ . '() is deprecated.', E_USER_DEPRECATED);
		return $this->explorer;
	}


	public function getQuery(): SqlLiteral
	{
		return $this->query;
	}


	/** @deprecated use getQuery()->getSql() */
	public function getQueryString(): string
	{
		return $this->query->getSql();
	}


	/** @deprecated use getQuery()->getParameters() */
	public function getParameters(): array
	{
		return $this->query->getParameters();
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


	/********************* interface IteratorAggregate ****************d*g**/


	/** @return \Generator<Row> */
	public function getIterator(): \Generator
	{
		if ($this->fetched) {
			throw new Nette\InvalidStateException(self::class . ' implements only one way iterator.');
		}

		$counter = 0;
		while (($row = $this->fetch()) !== null) {
			yield $counter++ => $row;
		}
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
			$this->fetched = true;
			return null;

		} elseif (!$this->fetched && count($data) !== $this->result->getColumnCount()) {
			$duplicates = array_filter(array_count_values(array_column($this->result->getColumnsInfo(), 'name')), fn($val) => $val > 1);
			trigger_error("Found duplicate columns in database result set: '" . implode("', '", array_keys($duplicates)) . "'.");
		}

		$this->fetched = true;
		return $this->normalizeRow($data);
	}


	/**
	 * Returns the next row as an object Row or null if there are no more rows.
	 */
	public function fetch(): ?Row
	{
		$data = $this->fetchAssoc();
		return $data === null ? null : Arrays::toObject($data, new Row);
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
		return $this->rows ??= iterator_to_array($this);
	}


	private function normalizeRow(array $row): array
	{
		$engine = $this->explorer->getDatabaseEngine();
		$converter = $this->explorer->getTypeConverter();
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
