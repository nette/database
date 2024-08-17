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
class ResultSet implements \Iterator
{
	private Row|false|null $lastRow = null;
	private int $lastRowKey = -1;

	/** @var Row[] */
	private array $rows;
	private array $converters;


	public function __construct(
		private readonly Connection $connection,
		private readonly Drivers\Result $result,
		private readonly ?SqlLiteral $query = null,
		private ?float $time = null,
	) {
	}


	/** @deprecated */
	public function getConnection(): Connection
	{
		trigger_error(__METHOD__ . '() is deprecated.', E_USER_DEPRECATED);
		return $this->connection;
	}


	public function getQueryString(): ?string
	{
		return $this->query?->getSql();
	}


	public function getParameters(): array
	{
		return $this->query?->getParameters();
	}


	public function getColumnCount(): ?int
	{
		return $this->result->getColumnCount();
	}


	public function getRowCount(): ?int
	{
		return $this->result->getRowCount();
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
	 * Fetches single row object.
	 */
	public function fetch(): mixed
	{
		$row = $this->result->fetch();
		if ($row === null) {
			return null;

		} elseif ($this->lastRow === null && count($row) !== $this->result->getColumnCount()) {
			$duplicates = array_filter(array_count_values(array_column($this->result->getColumnsInfo(), 'name')), fn($val) => $val > 1);
			trigger_error("Found duplicate columns in database result set: '" . implode("', '", array_keys($duplicates)) . "'.");
		}

		$row = $this->convertTypes($row);
		$row = $this->connection->getMapper()?->mapRow($row, $this) ?? $this->mapRow($row);

		$this->lastRowKey++;
		return $this->lastRow = $row;
	}


	/** @internal */
	public function fetchAssociative(): ?array
	{
		return $this->result->fetch();
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


	/** @internal */
	public function convertTypes(array $row): array
	{
		$converters = $this->converters ??= $this->resolveColumnConverters();
		foreach ($row as $key => $value) {
			$converter = $converters[$key];
			$row[$key] = isset($value, $converter)
				? $converter($value)
				: $value;
		}

		return $row;
	}


	private function resolveColumnConverters(): array
	{
		$res = [];
		$engine = $this->connection->getDatabaseEngine();
		$converter = $this->connection->getTypeConverter();
		foreach ($this->result->getColumnsInfo() as $meta) {
			$res[$meta['name']] = isset($meta['nativeType'])
				? $engine->resolveColumnConverter($meta, $converter)
				: null;
		}
		return $res;
	}


	private function mapRow(?array $row): Row
	{
		$res = new Row;
		foreach ($row as $key => $value) {
			$res->$key = $value;
		}
		return $res;
	}
}
