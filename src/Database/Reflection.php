<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette\Database\Reflection\Table;
use function array_values;


/**
 * Provides database schema reflection.
 */
final class Reflection
{
	/** @var array<string, Table> */
	public readonly array $tables;


	public function __construct(
		private readonly Drivers\Engine $engine,
	) {
		unset($this->tables);
	}


	/** @return Table[] */
	public function getTables(): array
	{
		return array_values($this->tables);
	}


	/**
	 * Returns table metadata.
	 * @throws \InvalidArgumentException if table does not exist
	 */
	public function getTable(string $name): Table
	{
		$name = $this->getFullName($name);
		return $this->tables[$name]
			?? $this->tryGetTable($name)
			?? throw new \InvalidArgumentException("Table '$name' not found.");
	}


	private function tryGetTable(string $name): ?Table
	{
		try {
			$table = new Table($this, $name);
			$table->columns;
			return $table;
		} catch (DriverException) {
		}
		return null;
	}


	public function hasTable(string $name): bool
	{
		$name = $this->getFullName($name);
		return isset($this->tables[$name]);
	}


	private function getFullName(string $name): string
	{
		return $name;
	}


	/** @internal */
	public function getDatabaseEngine(): Drivers\Engine
	{
		return $this->engine;
	}


	private function initTables(): void
	{
		$res = [];
		foreach ($this->engine->getTables() as $row) {
			$res[$row['fullName'] ?? $row['name']] = new Table($this, $row['name'], $row['view'], $row['fullName'] ?? null, $row['comment'] ?? null);
		}
		$this->tables = $res;
	}


	public function __get($name): mixed
	{
		match ($name) {
			'tables' => $this->initTables(),
			default => throw new \LogicException("Undefined property '$name'."),
		};
		return $this->$name;
	}
}
