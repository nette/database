<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

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


	public function getTables()
	{
		$this->needStructure();
		return $this->structure['tables'];
	}


	public function getColumns($table)
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);

		return $this->structure['columns'][$table];
	}


	public function getPrimaryKey($table)
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);

		if (!isset($this->structure['primary'][$table])) {
			return null;
		}

		return $this->structure['primary'][$table];
	}


	public function getPrimaryAutoincrementKey($table)
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


	public function getPrimaryKeySequence($table)
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
				return isset($columnMeta['vendor']['sequence']) ? $columnMeta['vendor']['sequence'] : null;
			}
		}

		return null;
	}


	public function getHasManyReference($table, $targetTable = null)
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
			if (!isset($this->structure['hasMany'][$table])) {
				return [];
			}
			return $this->structure['hasMany'][$table];
		}
	}


	public function getBelongsToReference($table, $column = null)
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);

		if ($column) {
			$column = strtolower($column);
			if (!isset($this->structure['belongsTo'][$table][$column])) {
				return null;
			}
			return $this->structure['belongsTo'][$table][$column];

		} else {
			if (!isset($this->structure['belongsTo'][$table])) {
				return [];
			}
			return $this->structure['belongsTo'][$table];
		}
	}


	public function rebuild()
	{
		$this->structure = $this->loadStructure();
		$this->cache->save('structure', $this->structure);
	}


	public function isRebuilt()
	{
		return $this->isRebuilt;
	}


	protected function needStructure()
	{
		if ($this->structure !== null) {
			return;
		}

		$this->structure = $this->cache->load('structure', [$this, 'loadStructure']);
	}


	/**
	 * @internal
	 */
	public function loadStructure()
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
				uksort($table, function ($a, $b) {
					return strlen($a) - strlen($b);
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


	protected function analyzeForeignKeys(&$structure, $table)
	{
		$lowerTable = strtolower($table);
		foreach ($this->connection->getSupplementalDriver()->getForeignKeys($table) as $row) {
			$structure['belongsTo'][$lowerTable][$row['local']] = $row['table'];
			$structure['hasMany'][strtolower($row['table'])][$table][] = $row['local'];
		}

		if (isset($structure['belongsTo'][$lowerTable])) {
			uksort($structure['belongsTo'][$lowerTable], function ($a, $b) {
				return strlen($a) - strlen($b);
			});
		}
	}


	protected function resolveFQTableName($table)
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
