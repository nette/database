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
	private ?\PDOStatement $pdoStatement = null;

	/** @var callable(array, Result): array */
	private readonly mixed $normalizer;
	private Row|false|null $lastRow = null;
	private int $lastRowKey = -1;

	/** @var Row[] */
	private array $rows;
	private float $time;
	private array $converters;


	public function __construct(
		private readonly Connection $connection,
		private readonly string $queryString,
		private readonly array $params,
		?callable $normalizer = null,
	) {
		$time = microtime(true);
		$this->normalizer = $normalizer;

		try {
			if (str_starts_with($queryString, '::')) {
				$connection->getConnection()->{substr($queryString, 2)}();
			} else {
				$this->pdoStatement = $connection->getConnection()->query($queryString, $params);
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


	/********************* fetch ****************d*g**/


	/**
	 * Returns the next row as an associative array or null if there are no more rows.
	 */
	public function fetchAssoc(?string $path = null): ?array
	{
		if ($path !== null) {
			return Arrays::associate($this->fetchAll(), $path);
		}

		$data = $this->pdoStatement ? $this->pdoStatement->fetch() : null;
		if (!$data) {
			$this->pdoStatement->closeCursor();
			return null;

		} elseif ($this->lastRow === null && count($data) !== $this->pdoStatement->columnCount()) {
			$duplicates = Helpers::findDuplicates($this->pdoStatement);
			trigger_error("Found duplicate columns in database result set: $duplicates.");
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


	public function resolveColumnConverters(): array
	{
		if (isset($this->converters)) {
			return $this->converters;
		}

		$res = [];
		$engine = $this->connection->getDatabaseEngine();
		$converter = $this->connection->getTypeConverter();
		$metaTypeKey = $this->connection->getConnection()->metaTypeKey;
		$count = $this->pdoStatement->columnCount();
		for ($i = 0; $i < $count; $i++) {
			$meta = $this->pdoStatement->getColumnMeta($i);
			if (isset($meta[$metaTypeKey])) {
				$res[$meta['name']] = $engine->resolveColumnConverter([
					'nativeType' => $meta[$metaTypeKey],
					'size' => $meta['len'],
					'scale' => $meta['precision'],
				], $converter);
			}
		}
		return $this->converters = $res;
	}
}


class_exists(ResultSet::class);
