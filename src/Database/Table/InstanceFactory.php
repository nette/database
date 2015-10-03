<?php

namespace Nette\Database\Table;

use Nette\Database\Context;
use Nette\Database\IConventions;
use Nette\Caching\IStorage;

class InstanceFactory implements IInstanceFactory
{
	/** @var array */
	protected $map = [
		'default' => [
			'activeRow' => '\Nette\Database\Table\ActiveRow',
			'selection' => '\Nette\Database\Table\Selection',
			'groupedSelection' => '\Nette\Database\Table\GroupedSelection'
		],
		'activeRow' => [],
		'selection' => [],
		'groupedSelection' => []
	];

	/**
	 * @param array $map
	 */
	public function __construct(array $map)
	{
		$this->registerMapType('default', $map);
		$this->registerMapType('activeRow', $map);
		$this->registerMapType('selection', $map);
		$this->registerMapType('groupedSelection', $map);
	}

	/**
	 * @param string $type
	 * @param $array
	 */
	protected function registerMapType($type, $array)
	{
		isset($array[$type]) ? $this->map[$type] = $array[$type] : null;
	}

	/**
	 * @param string $tableName
	 * @param array $data
	 * @param Selection $table
	 * @return mixed
	 */
	public function createActiveRow(
		$tableName,
		array $data,
		Selection $table
	) {
		$fixedTableName = $this->getTableName($tableName);

		if (isset($this->map['activeRow'][$fixedTableName])) {
			$className = $this->map['activeRow'][$fixedTableName];
			return new $className($data, $table);
		}

		$className = $this->map['default']['activeRow'];
		return new $className($data, $table);
	}

	/**
	 * @param Context $context
	 * @param IConventions $conventions
	 * @param string $tableName
	 * @param IStorage|null $cacheStorage
	 * @return mixed
	 */
	public function createSelection(
		Context $context,
		IConventions $conventions,
		$tableName,
		IStorage $cacheStorage = NULL
	) {
		$fixedTableName = $this->getTableName($tableName);

		if (isset($this->map['selection'][$fixedTableName])) {
			$className = $this->map['selection'][$fixedTableName];
			$instance = new $className($context, $conventions, $tableName, $cacheStorage);
		} else {
			$className = $this->map['default']['selection'];
			$instance = new $className($context, $conventions, $tableName, $cacheStorage);
		}

		$instance->setInstanceFactory($this);
		return $instance;
	}

	/**
	 * @param Context $context
	 * @param IConventions $conventions
	 * @param string $tableName
	 * @param string $column
	 * @param Selection $refTable
	 * @param IStorage|null $cacheStorage
	 * @return mixed
	 */
	public function createGroupedSelection(
		Context $context,
		IConventions $conventions,
		$tableName,
		$column,
		Selection $refTable,
		IStorage $cacheStorage = NULL
	) {
		$fixedTableName = $this->getTableName($tableName);

		if (isset($this->map['groupedSelection'][$fixedTableName])) {
			$className = $this->map['groupedSelection'][$fixedTableName];
			$instance = new $className($context, $conventions, $tableName, $column, $refTable, $cacheStorage);
		} else {
			$className = $this->map['default']['groupedSelection'];
			$instance = new $className($context, $conventions, $tableName, $column, $refTable, $cacheStorage);
		}

		$instance->setInstanceFactory($this);
		return $instance;
	}

	/**
	 * Returns fixed table name
	 * PgSql driver use full table names with schema eg. public.author
	 * @param string $tableName
	 * @return string
	 */
	protected function getTableName($tableName)
	{
		return strpos($tableName, '.') !== FALSE
			? substr($tableName, strpos($tableName, '.') + 1, strlen($tableName) - strpos($tableName, '.'))
			: $tableName;
	}
}
