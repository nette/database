<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette\Database\Reflection\Table;


final class Reflection
{
	/** @var array<string, Table> */
	public readonly array $tables;
	private ?string $schema;


	public function __construct(
		private readonly Driver $driver,
	) {
		$this->schema = $this->driver->isSupported(Driver::SupportSchema) ? 'public' : null;
		unset($this->tables);
	}


	/** @return Table[] */
	public function getTables(): array
	{
		return array_values($this->tables);
	}


	public function getTable(string $name): Table
	{
		$name = $this->getFullName($name);
		return $this->tables[$name] ?? throw new \InvalidArgumentException("Table '$name' not found.");
	}


	public function hasTable(string $name): bool
	{
		$name = $this->getFullName($name);
		return isset($this->tables[$name]);
	}


	private function getFullName(string $name): string
	{
		return $this->schema !== null && !str_contains($name, '.')
			? $this->schema . '.' . $name
			: $name;
	}


	/** @internal */
	public function getDriver(): Driver
	{
		return $this->driver;
	}


	private function initTables(): void
	{
		$res = [];
		foreach ($this->driver->getTables() as $row) {
			$res[$row['fullName'] ?? $row['name']] = new Table($this, $row['name'], $row['view'], $row['fullName'] ?? null);
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
