<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

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
	/** @var Selection referenced table */
	protected $refTable;

	/** @var  mixed current assigned referencing array */
	protected $refCacheCurrent;

	/** @var string grouping column name */
	protected $column;

	/** @var int primary key */
	protected $active;


	/**
	 * Creates filtered and grouped table representation.
	 * @param  Context
	 * @param  IConventions
	 * @param  string  database table name
	 * @param  string  joining column
	 * @param  Selection
	 * @param  Nette\Caching\IStorage|NULL
	 */
	public function __construct(Context $context, IConventions $conventions, $tableName, $column, Selection $refTable, Nette\Caching\IStorage $cacheStorage = NULL)
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
	public function setActive($active)
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
	public function order($columns, ...$params)
	{
		if (!$this->sqlBuilder->getOrder()) {
			// improve index utilization
			$this->sqlBuilder->addOrder("$this->name.$this->column" . (preg_match('~\bDESC\z~i', $columns) ? ' DESC' : ''));
		}

		return parent::order($columns, ...$params);
	}


	/********************* aggregations ****************d*g**/


	public function aggregation($function)
	{
		$aggregation = &$this->getRefTable($refPath)->aggregation[$refPath . $function . $this->sqlBuilder->getSelectQueryHash($this->getPreviousAccessedColumns())];

		if ($aggregation === NULL) {
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
	}


	/**
	 * @return int
	 */
	public function count($column = NULL)
	{
		$return = parent::count($column);
		return isset($return) ? $return : 0;
	}


	/********************* internal ****************d*g**/


	protected function execute()
	{
		if ($this->rows !== NULL) {
			$this->observeCache = $this;
			return;
		}

		$accessedColumns = $this->accessedColumns;
		$this->loadRefCache();

		if (!isset($this->refCacheCurrent['data'])) {
			// we have not fetched any data yet => init accessedColumns by cached accessedColumns
			$this->accessedColumns = $accessedColumns;

			$limit = $this->sqlBuilder->getLimit();
			$rows = count($this->refTable->rows);
			if ($limit && $rows > 1) {
				$this->sqlBuilder->setLimit(NULL, NULL);
			}
			parent::execute();
			$this->sqlBuilder->setLimit($limit, NULL);
			$data = [];
			$offset = [];
			$this->accessColumn($this->column);
			foreach ((array) $this->rows as $key => $row) {
				$ref = &$data[$row[$this->column]];
				$skip = &$offset[$row[$this->column]];
				if ($limit === NULL || $rows <= 1 || (count($ref) < $limit && $skip >= $this->sqlBuilder->getOffset())) {
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

		$this->observeCache = $this;
		if ($this->data === NULL) {
			$this->data = [];
		} else {
			foreach ($this->data as $row) {
				$row->setTable($this); // injects correct parent GroupedSelection
			}
			reset($this->data);
		}
	}


	/**
	 * @return Selection
	 */
	protected function getRefTable(&$refPath)
	{
		$refObj = $this->refTable;
		$refPath = $this->name . '.';
		while ($refObj instanceof self) {
			$refPath .= $refObj->name . '.';
			$refObj = $refObj->refTable;
		}

		return $refObj;
	}


	protected function loadRefCache()
	{
		$hash = $this->getSpecificCacheKey();
		$referencing = &$this->refCache['referencing'][$this->getGeneralCacheKey()];
		$this->observeCache = &$referencing['observeCache'];
		$this->refCacheCurrent = &$referencing[$hash];
		$this->accessedColumns = &$referencing[$hash]['accessed'];
		$this->specificCacheKey = &$referencing[$hash]['specificCacheKey'];
		$this->rows = &$referencing[$hash]['rows'];

		if (isset($referencing[$hash]['data'][$this->active])) {
			$this->data = &$referencing[$hash]['data'][$this->active];
		}
	}


	protected function emptyResultSet($saveCache = TRUE, $deleteRererencedCache = TRUE)
	{
		parent::emptyResultSet($saveCache, FALSE);
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


	public function update($data)
	{
		$builder = $this->sqlBuilder;

		$this->sqlBuilder = clone $this->sqlBuilder;
		$this->where($this->column, $this->active);
		$return = parent::update($data);

		$this->sqlBuilder = $builder;
		return $return;
	}


	public function delete()
	{
		$builder = $this->sqlBuilder;

		$this->sqlBuilder = clone $this->sqlBuilder;
		$this->where($this->column, $this->active);
		$return = parent::delete();

		$this->sqlBuilder = $builder;
		return $return;
	}

}
