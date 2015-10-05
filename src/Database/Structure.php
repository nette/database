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
class Structure extends Nette\Object implements IStructure
{
	/** @var Connection */
	protected $connection;

	/** @var Nette\Caching\Cache */
	protected $cache;

	/** @var array */
	protected $structure;

	/** @var bool */
	protected $isRebuilt = FALSE;


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
			return NULL;
		}

		return $this->structure['primary'][$table];
	}


	public function getPrimaryKeySequence($table)
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);

		if (!$this->connection->getSupplementalDriver()->isSupported(ISupplementalDriver::SUPPORT_SEQUENCE)) {
			return NULL;
		}

		$primary = $this->getPrimaryKey($table);
		if (!$primary || is_array($primary)) {
			return NULL;
		}

		foreach ($this->structure['columns'][$table] as $columnMeta) {
			if ($columnMeta['name'] === $primary) {
				return isset($columnMeta['vendor']['sequence']) ? $columnMeta['vendor']['sequence'] : NULL;
			}
		}

		return NULL;
	}


	public function getHasManyReference($table, $targetTable = NULL)
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

			return NULL;

		} else {
			if (!isset($this->structure['hasMany'][$table])) {
				return array();
			}
			return $this->structure['hasMany'][$table];
		}
	}


	public function getBelongsToReference($table, $column = NULL)
	{
		$this->needStructure();
		$table = $this->resolveFQTableName($table);

		if ($column) {
			$column = strtolower($column);
			if (!isset($this->structure['belongsTo'][$table][$column])) {
				return NULL;
			}
			return $this->structure['belongsTo'][$table][$column];

		} else {
			if (!isset($this->structure['belongsTo'][$table])) {
				return array();
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
		if ($this->structure !== NULL) {
			return;
		}

		$this->structure = $this->cache->load('structure', array($this, 'loadStructure'));
	}


	/**
	 * @internal
	 */
	public function loadStructure()
	{
		$driver = $this->connection->getSupplementalDriver();

		$structure = array();
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
			foreach ($structure['hasMany'] as & $table) {
				uksort($table, function ($a, $b) {
					return strlen($a) - strlen($b);
				});
			}
		}

		$this->isRebuilt = TRUE;

		return $structure;
	}


	protected function analyzePrimaryKey(array $columns)
	{
		$primary = array();
		foreach ($columns as $column) {
			if ($column['primary']) {
				$primary[] = $column['name'];
			}
		}

		if (count($primary) === 0) {
			return NULL;
		} elseif (count($primary) === 1) {
			return reset($primary);
		} else {
			return $primary;
		}
	}


	protected function analyzeForeignKeys(& $structure, $table)
	{
		foreach ($this->connection->getSupplementalDriver()->getForeignKeys($table) as $row) {
			$structure['belongsTo'][strtolower($table)][$row['local']] = $row['table'];
			$structure['hasMany'][strtolower($row['table'])][$table][] = $row['local'];
		}

		if (isset($structure['belongsTo'][$table])) {
			uksort($structure['belongsTo'][$table], function ($a, $b) {
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
