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

	/** @var Connection */
	protected $connection;

	/** @var Nette\Caching\Cache */
	protected $cache;

	/** @var array */
	protected $structure;

	/** @var bool */
	protected $isRebuilt = false;


	public function __construct(Connection $connection, Nette\Caching\IStorage $cacheStorage)
	{
		$this->connection = $connection;
		$this->cache = new Nette\Caching\Cache($cacheStorage, 'Nette.Database.Structure.' . md5($this->connection->getDsn()));
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
	public function getPrimaryKey(string $table)
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
				if (isset($keys[$column['name']]) && $column['autoincrement']) {
					return $column['name'];
				}
			}
			return null;
		}

		// Search for autoincrement key from simple primary key
		foreach ($this->getColumns($table) as $column) {
			if ($column['name'] == $primaryKey) {
				return $column['autoincrement'] ? $column['name'] : null;
			}
		}

		return null;
	}


	public function getPrimaryKeySequence(string $table): ?string
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);

		if (!$this->connection->getSupplementalDriver()->isSupported(ISupplementalDriver::SUPPORT_SEQUENCE)) {
			return null;
		}

		$autoincrementPrimaryKeyName = $this->getPrimaryAutoincrementKey($table);
		if (!$autoincrementPrimaryKeyName) {
			return null;
		}

		// Search for sequence from simple primary key
		foreach ($this->structure['columns'][$table] as $columnMeta) {
			if ($columnMeta['name'] === $autoincrementPrimaryKeyName) {
				return $columnMeta['vendor']['sequence'] ?? null;
			}
		}

		return null;
	}


	public function getHasManyReference(string $table, string $targetTable = null): ?array
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


	public function getBelongsToReference(string $table, string $column = null): ?array
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
		if ($this->structure !== null) {
			return;
		}

		$this->structure = $this->cache->load('structure', [$this, 'loadStructure']);
	}


	/**
	 * @internal
	 */
	public function loadStructure(): array
	{
		$driver = $this->connection->getSupplementalDriver();

		$structure = [];
		$structure['tables'] = $driver->getTables();

		foreach ($structure['tables'] as $tablePair) {
			if (isset($tablePair['fullName'])) {
				$table = $tablePair['fullName'];
				$structure['aliases'][strtolower($tablePair['name'])] = strtolower($table);
			} else {
				$table = $tablePair['name'];
			}

			$structure['columns'][strtolower($table)] = $columns = $driver->getColumns($table);

			if (!$tablePair['view']) {
				$structure['primary'][strtolower($table)] = $this->analyzePrimaryKey($columns);
				$this->analyzeForeignKeys($structure, $table);
			}
		}

		if (isset($structure['hasMany'])) {
			foreach ($structure['hasMany'] as &$table) {
				uksort($table, function ($a, $b): int {
					return strlen($a) <=> strlen($b);
				});
			}
		}

		$this->isRebuilt = true;

		return $structure;
	}


	protected function analyzePrimaryKey(array $columns)
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

		$foreignKeys = $this->connection->getSupplementalDriver()->getForeignKeys($table);

		$fksColumnsCounts = [];
		foreach ($foreignKeys as $foreignKey) {
			$tmp = &$fksColumnsCounts[$foreignKey['name']];
			$tmp++;
		}
		usort($foreignKeys, function ($a, $b) use ($fksColumnsCounts): int {
			return $fksColumnsCounts[$b['name']] <=> $fksColumnsCounts[$a['name']];
		});

		foreach ($foreignKeys as $row) {
			$structure['belongsTo'][$lowerTable][$row['local']] = $row['table'];
			$structure['hasMany'][strtolower($row['table'])][$table][] = $row['local'];
		}

		if (isset($structure['belongsTo'][$lowerTable])) {
			uksort($structure['belongsTo'][$lowerTable], function ($a, $b): int {
				return strlen($a) <=> strlen($b);
			});
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
