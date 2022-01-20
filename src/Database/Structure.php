<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette;


/**
 * Cached reflection of database structure.
 */
class Structure implements IStructure
{
	use Nette\SmartObject;

	protected Connection $connection;

	protected Nette\Caching\Cache $cache;

	protected array $structure;

	protected bool $isRebuilt = false;


	public function __construct(Connection $connection, Nette\Caching\Storage $cacheStorage)
	{
		$this->connection = $connection;
		$this->cache = new Nette\Caching\Cache($cacheStorage, 'Nette.Database.Structure4.' . md5($this->connection->getDsn()));
	}


	/** @return Reflection\Table[] */
	public function getTables(): array
	{
		$this->needStructure();
		return $this->structure['tables'];
	}


	/** @return Reflection\Column[] */
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

		// Search for autoincrement key from multi primary key
		if (is_array($primaryKey)) {
			$keys = array_flip($primaryKey);
			foreach ($this->getColumns($table) as $column) {
				if (isset($keys[$column->name]) && $column->autoIncrement) {
					return $column->name;
				}
			}

			return null;
		}

		// Search for autoincrement key from simple primary key
		foreach ($this->getColumns($table) as $column) {
			if ($column->name === $primaryKey) {
				return $column->autoIncrement ? $column->name : null;
			}
		}

		return null;
	}


	public function getPrimaryKeySequence(string $table): ?string
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);

		if (!$this->connection->getDriver()->isSupported(Driver::SupportSequence)) {
			return null;
		}

		$autoincrementPrimaryKeyName = $this->getPrimaryAutoincrementKey($table);
		if (!$autoincrementPrimaryKeyName) {
			return null;
		}

		// Search for sequence from simple primary key
		foreach ($this->structure['columns'][$table] as $columnMeta) {
			if ($columnMeta->name === $autoincrementPrimaryKeyName) {
				return $columnMeta->vendor['sequence'] ?? null;
			}
		}

		return null;
	}


	public function getHasManyReference(string $table, ?string $targetTable = null): ?array
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);

		if ($targetTable) {
			$targetTable = $this->resolveFQTableName($targetTable);
			foreach ($this->structure['hasMany'][$table] as $key => $value) {
				if (strtolower($key) === $targetTable) {
					return $this->structure['hasMany'][$table][$key];
				}
			}

			return null;

		} else {
			return $this->structure['hasMany'][$table] ?? [];
		}
	}


	public function getBelongsToReference(string $table, ?string $column = null): ?array
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);

		if ($column) {
			$column = strtolower($column);
			return isset($this->structure['belongsTo'][$table][$column])
				? [$this->structure['belongsTo'][$table][$column], $column]
				: null;

		} else {
			return $this->structure['belongsTo'][$table] ?? [];
		}
	}


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

		$this->structure = $this->cache->load('structure', \Closure::fromCallable([$this, 'loadStructure']));
	}


	protected function loadStructure(): array
	{
		$driver = $this->connection->getDriver();

		$structure = [];
		$structure['tables'] = $driver->getTables();

		foreach ($structure['tables'] as $table) {
			if (isset($table->fullName)) {
				$tableName = $table->fullName;
				$structure['aliases'][strtolower($table->name)] = strtolower($tableName);
			} else {
				$tableName = $table->name;
			}

			$structure['columns'][strtolower($tableName)] = $columns = $driver->getColumns($tableName);

			if (!$table->view) {
				$structure['primary'][strtolower($tableName)] = $this->analyzePrimaryKey($columns);
				$this->analyzeForeignKeys($structure, $tableName);
			}
		}

		if (isset($structure['hasMany'])) {
			foreach ($structure['hasMany'] as &$tableName) {
				uksort($tableName, fn($a, $b): int => strlen($a) <=> strlen($b));
			}
		}

		$this->isRebuilt = true;

		return $structure;
	}


	/** @param  Reflection\Column[]  $columns */
	protected function analyzePrimaryKey(array $columns)
	{
		$primary = [];
		foreach ($columns as $column) {
			if ($column->primary) {
				$primary[] = $column->name;
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

		$foreignKeys = $this->connection->getDriver()->getForeignKeys($table);

		usort($foreignKeys, fn($a, $b): int => count($b->columns) <=> count($a->columns));

		foreach ($foreignKeys as $key) {
			$structure['belongsTo'][$lowerTable][$key->columns[0]] = $key->targetTable;
			$structure['hasMany'][strtolower($key->targetTable)][$table][] = $key->columns[0];
		}

		if (isset($structure['belongsTo'][$lowerTable])) {
			uksort($structure['belongsTo'][$lowerTable], fn($a, $b): int => strlen($a) <=> strlen($b));
		}
	}


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
