<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Table;

use Nette;
use Nette\Database\Context;
use Nette\Database\IConventions;


/**
 * Representation of filtered table grouped by some column.
 * GroupedSelection is based on the great library NotORM http://www.notorm.com written by Jakub Vrana.
 */
class GroupedSelection extends Selection
{

	/** @var mixed current assigned referencing array */
	public $refCacheCurrent;

	/** @var Selection referenced table */
	protected $refTable;

	/** @var string grouping column name */
	protected $column;

	/** @var int primary key */
	protected $active;


	/**
	 * Creates filtered and grouped table representation.
	 * @param  string $tableName  database table name
	 * @param  string $column  joining column
	 */
	public function __construct(Context $context, IConventions $conventions, string $tableName, string $column, Selection $refTable, Nette\Caching\IStorage $cacheStorage = null)
	{
		$this->refTable = $refTable;
		$this->column = $column;
		parent::__construct($context, $conventions, $tableName, $cacheStorage);
	}


	/**
	 * Sets active group.
	 * @internal
	 * @param  int  primary key of grouped rows
	 * @return static
	 */
	public function setActive(int $active)
	{
		$this->active = $active;
		return $this;
	}


	/**
	 * @return static
	 */
	public function select($columns, ...$params)
	{
		if (!$this->sqlBuilder->getSelect()) {
			$this->sqlBuilder->addSelect("$this->name.$this->column");
		}

		return parent::select($columns, ...$params);
	}


	/**
	 * @return static
	 */
	public function order(string $columns, ...$params)
	{
		if (!$this->sqlBuilder->getOrder()) {
			// improve index utilization
			$this->sqlBuilder->addOrder("$this->name.$this->column" . (preg_match('~\bDESC\z~i', $columns) ? ' DESC' : ''));
		}

		return parent::order($columns, ...$params);
	}


	/********************* aggregations ****************d*g**/


	/**
	 * @return mixed
	 */
	public function aggregation(string $function)
	{
		$selectQueryHash = $this->sqlBuilder->getSelectQueryHash($this->cache->getPreviousAccessedColumns());
		$aggregation = &$this->getRefTable($refPath)->aggregation[$refPath . $function . $selectQueryHash];

		if ($aggregation === null) {
			$aggregation = [];

			$selection = $this->createSelectionInstance();
			$selection->getSqlBuilder()->importConditions($this->getSqlBuilder());
			$selection->select($function);
			$selection->select("$this->name.$this->column");
			$selection->group("$this->name.$this->column");

			foreach ($selection as $row) {
				$aggregation[$row[$this->column]] = $row;
			}
		}

		if (isset($aggregation[$this->active])) {
			foreach ($aggregation[$this->active] as $val) {
				return $val;
			}
		}
		return 0;
	}


	public function count(string $column = null): int
	{
		$return = parent::count($column);
		return $return ?? 0;
	}


	/********************* internal ****************d*g**/


	protected function execute(): void
	{
		if ($this->rows !== null) {
			$this->cache->setObserveCache($this);
			return;
		}

		$accessedColumns = $this->cache->getAccessedColumns();
		$this->loadRefCache();

		if (!isset($this->refCacheCurrent['data'])) {
			// we have not fetched any data yet => init accessedColumns by cached accessedColumns
			$this->cache->setAccessedColumns($accessedColumns);

			$limit = $this->sqlBuilder->getLimit();
			$rows = count($this->refTable->rows);
			if ($limit && $rows > 1) {
				$this->sqlBuilder->setLimit(null, null);
			}
			parent::execute();
			$this->sqlBuilder->setLimit($limit, null);
			$data = [];
			$offset = [];
			$this->accessColumn($this->column);
			foreach ((array) $this->rows as $key => $row) {
				$ref = &$data[$row[$this->column]];
				$skip = &$offset[$row[$this->column]];
				if ($limit === null || $rows <= 1 || (count($ref ?? []) < $limit && $skip >= $this->sqlBuilder->getOffset())) {
					$ref[$key] = $row;
				} else {
					unset($this->rows[$key]);
				}
				$skip++;
				unset($ref, $skip);
			}

			$this->refCacheCurrent['data'] = $data;
			$this->data = &$this->refCacheCurrent['data'][$this->active];
		}

		$this->cache->setObserveCache($this);
		if ($this->data === null) {
			$this->data = [];
		} else {
			foreach ($this->data as $row) {
				$row->setTable($this); // injects correct parent GroupedSelection
			}
			reset($this->data);
		}
	}


	protected function getRefTable(&$refPath): Selection
	{
		$refObj = $this->refTable;
		$refPath = $this->name . '.';
		while ($refObj instanceof self) {
			$refPath .= $refObj->name . '.';
			$refObj = $refObj->refTable;
		}

		return $refObj;
	}


	protected function loadRefCache(): void
	{
		$referencing = &$this->refCache->getReferencing($this->cache->getGeneralCacheKey());
		$hash = $this->cache->loadFromRefCache($referencing);
		$this->refCacheCurrent = &$referencing[$hash];
		$this->rows = &$referencing[$hash]['rows'];

		if (isset($referencing[$hash]['data'][$this->active])) {
			$this->data = &$referencing[$hash]['data'][$this->active];
		}
	}


	protected function emptyResultSet(bool $saveCache = true, bool $deleteRererencedCache = true): void
	{
		parent::emptyResultSet($saveCache, false);
	}


	/********************* manipulation ****************d*g**/


	public function insert($data)
	{
		if ($data instanceof \Traversable && !$data instanceof Selection) {
			$data = iterator_to_array($data);
		}

		if (Nette\Utils\Arrays::isList($data)) {
			foreach (array_keys($data) as $key) {
				$data[$key][$this->column] = $this->active;
			}
		} else {
			$data[$this->column] = $this->active;
		}

		return parent::insert($data);
	}


	public function update(iterable $data): int
	{
		$builder = $this->sqlBuilder;

		$this->sqlBuilder = clone $this->sqlBuilder;
		$this->where($this->column, $this->active);
		$return = parent::update($data);

		$this->sqlBuilder = $builder;
		return $return;
	}


	public function delete(): int
	{
		$builder = $this->sqlBuilder;

		$this->sqlBuilder = clone $this->sqlBuilder;
		$this->where($this->column, $this->active);
		$return = parent::delete();

		$this->sqlBuilder = $builder;
		return $return;
	}
}
