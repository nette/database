<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette;
use function array_flip, count, hash, is_array, reset, strlen, strtolower, uksort, usort;


/**
 * Provides database structure metadata with caching.
 * @internal
 */
class Structure
{
	/** @var array{tables: array, columns: array, primary: array, aliases: array, hasMany: array, belongsTo: array} */
	protected array $structure;
	protected bool $isRebuilt = false;


	public function __construct(
		protected readonly Drivers\Engine $engine,
		protected readonly Nette\Caching\Cache $cache,
	) {
	}


	public function getTables(): array
	{
		$this->needStructure();
		return $this->structure['tables'];
	}


	public function getColumns(string $table): array
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);

		return $this->structure['columns'][$table];
	}


	/**
	 * @return string|string[]|null
	 */
	public function getPrimaryKey(string $table): string|array|null
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);
		return $this->structure['primary'][$table] ?? null;
	}


	public function getPrimaryAutoincrementKey(string $table): ?string
	{
		$primaryKey = $this->getPrimaryKey($table);
		if (!$primaryKey) {
			return null;
		}

		// Search for autoIncrement key from multi primary key
		if (is_array($primaryKey)) {
			$keys = array_flip($primaryKey);
			foreach ($this->getColumns($table) as $column) {
				if (isset($keys[$column['name']]) && $column['autoIncrement']) {
					return $column['name'];
				}
			}

			return null;
		}

		// Search for auto-increment key from simple primary key
		foreach ($this->getColumns($table) as $column) {
			if ($column['name'] === $primaryKey) {
				return $column['autoIncrement'] ? $column['name'] : null;
			}
		}

		return null;
	}


	public function getPrimaryKeySequence(string $table): ?string
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);

		if (!$this->engine->isSupported(Drivers\Engine::SupportSequence)) {
			return null;
		}

		$autoIncrementPrimaryKeyName = $this->getPrimaryAutoincrementKey($table);
		if (!$autoIncrementPrimaryKeyName) {
			return null;
		}

		// Search for sequence from simple primary key
		foreach ($this->structure['columns'][$table] as $columnMeta) {
			if ($columnMeta['name'] === $autoIncrementPrimaryKeyName) {
				return $columnMeta['vendor']['sequence'] ?? null;
			}
		}

		return null;
	}


	public function getHasManyReference(string $table): array
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);
		return $this->structure['hasMany'][$table] ?? [];
	}


	public function getBelongsToReference(string $table): array
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);
		return $this->structure['belongsTo'][$table] ?? [];
	}


	/**
	 * Rebuilds structure cache.
	 */
	public function rebuild(): void
	{
		$this->structure = $this->loadStructure();
		$this->cache->save('structure', $this->structure);
	}


	public function isRebuilt(): bool
	{
		return $this->isRebuilt;
	}


	protected function needStructure(): void
	{
		if (isset($this->structure)) {
			return;
		}

		$this->structure = $this->cache->load('structure', $this->loadStructure(...));
	}


	/**
	 * Loads complete structure from database.
	 */
	protected function loadStructure(): array
	{
		$structure = [];
		$structure['tables'] = $this->engine->getTables();

		foreach ($structure['tables'] as $tablePair) {
			if (isset($tablePair['fullName'])) {
				$table = $tablePair['fullName'];
				$structure['aliases'][strtolower($tablePair['name'])] = strtolower($table);
			} else {
				$table = $tablePair['name'];
			}

			$structure['columns'][strtolower($table)] = $columns = $this->engine->getColumns($table);

			if (!$tablePair['view']) {
				$structure['primary'][strtolower($table)] = $this->analyzePrimaryKey($columns);
				$this->analyzeForeignKeys($structure, $table);
			}
		}

		if (isset($structure['hasMany'])) {
			foreach ($structure['hasMany'] as &$table) {
				uksort($table, fn($a, $b): int => strlen($a) <=> strlen($b));
			}
		}

		$this->isRebuilt = true;

		return $structure;
	}


	protected function analyzePrimaryKey(array $columns): string|array|null
	{
		$primary = [];
		foreach ($columns as $column) {
			if ($column['primary']) {
				$primary[] = $column['name'];
			}
		}

		if (count($primary) === 0) {
			return null;
		} elseif (count($primary) === 1) {
			return reset($primary);
		} else {
			return $primary;
		}
	}


	protected function analyzeForeignKeys(array &$structure, string $table): void
	{
		$lowerTable = strtolower($table);

		$foreignKeys = $this->engine->getForeignKeys($table);

		usort($foreignKeys, fn($a, $b): int => count($b['local']) <=> count($a['local']));

		foreach ($foreignKeys as $row) {
			$structure['belongsTo'][$lowerTable][$row['local'][0]] = $row['table'];
			$structure['hasMany'][strtolower($row['table'])][$table][] = $row['local'][0];
		}

		if (isset($structure['belongsTo'][$lowerTable])) {
			uksort($structure['belongsTo'][$lowerTable], fn($a, $b): int => strlen($a) <=> strlen($b));
		}
	}


	/**
	 * Returns normalized table name.
	 */
	protected function resolveFQTableName(string $table): string
	{
		$name = strtolower($table);
		if (isset($this->structure['columns'][$name])) {
			return $name;
		}

		if (isset($this->structure['aliases'][$name])) {
			return $this->structure['aliases'][$name];
		}

		if (!$this->isRebuilt()) {
			$this->rebuild();
			return $this->resolveFQTableName($table);
		}

		throw new Nette\InvalidArgumentException("Table '$name' does not exist.");
	}
}
