<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Reflection;

use Nette\Database\Reflection;
use function array_filter, array_map, array_values, is_string;


/**
 * Database table structure.
 */
final class Table
{
	/** @var array<string, Column> */
	public readonly array $columns;

	/** @var list<Index> */
	public readonly array $indexes;
	public readonly ?Index $primaryKey;

	/** @var list<ForeignKey> */
	public readonly array $foreignKeys;


	/** @internal */
	public function __construct(
		private readonly Reflection $reflection,
		public readonly string $name,
		public readonly bool $view = false,
		public readonly ?string $fullName = null,
		public readonly ?string $comment = null,
	) {
		unset($this->columns, $this->indexes, $this->primaryKey, $this->foreignKeys);
	}


	/**
	 * Returns column object or throws exception if column doesn't exist.
	 * @throws \InvalidArgumentException
	 */
	public function getColumn(string $name): Column
	{
		return $this->columns[$name] ?? throw new \InvalidArgumentException("Column '$name' not found in table '$this->name'.");
	}


	private function initColumns(): void
	{
		$res = [];
		foreach ($this->reflection->getDatabaseEngine()->getColumns($this->name) as $row) {
			$row['table'] = $this;
			$res[$row['name']] = new Column(...$row);
		}
		$this->columns = $res;
	}


	private function initIndexes(): void
	{
		$this->indexes = array_map(
			fn($row) => new Index(
				array_map(fn($name) => $this->getColumn($name), $row['columns']),
				$row['unique'],
				$row['primary'],
				is_string($row['name']) ? $row['name'] : null,
			),
			$this->reflection->getDatabaseEngine()->getIndexes($this->name),
		);
	}


	private function initPrimaryKey(): void
	{
		$res = array_filter(
			$this->columns,
			fn($row) => $row->primary,
		);
		$this->primaryKey = $res ? new Index(array_values($res), true, true) : null;
	}


	private function initForeignKeys(): void
	{
		$tmp = [];
		foreach ($this->reflection->getDatabaseEngine()->getForeignKeys($this->name) as $row) {
			$id = $row['name'];
			$foreignTable = $this->reflection->getTable($row['table']);
			$tmp[$id][0] = $foreignTable;
			$tmp[$id][1] = array_map(fn($name) => $this->getColumn($name), $row['local']);
			$tmp[$id][2] = array_map(fn($name) => $foreignTable->getColumn($name), $row['foreign']);
			$tmp[$id][3] = is_string($id) ? $id : null;
		}
		$this->foreignKeys = array_map(fn($row) => new ForeignKey(...$row), array_values($tmp));
	}


	public function __get($name): mixed
	{
		match ($name) {
			'columns' => $this->initColumns(),
			'indexes' => $this->initIndexes(),
			'primaryKey' => $this->initPrimaryKey(),
			'foreignKeys' => $this->initForeignKeys(),
			default => throw new \LogicException("Undefined property '$name'."),
		};
		return $this->$name;
	}


	public function __toString(): string
	{
		return $this->name;
	}
}
