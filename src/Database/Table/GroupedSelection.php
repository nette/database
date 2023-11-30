<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Table;

use Nette;
use Nette\Database\Conventions;
use Nette\Database\Explorer;


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
	 */
	public function __construct(
		Explorer $explorer,
		Conventions $conventions,
		string $tableName,
		string $column,
		Selection $refTable,
		?Nette\Caching\Storage $cacheStorage = null,
	) {
		$this->refTable = $refTable;
		$this->column = $column;
		parent::__construct($explorer, $conventions, $tableName, $cacheStorage);
	}


	/**
	 * Sets active group.
	 * @internal
	 * @param  int|string  $active  primary key of grouped rows
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
	public function select(string $columns, ...$params)
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
	public function aggregation(string $function, ?string $groupFunction = null)
	{
		$aggregation = &$this->getRefTable($refPath)->aggregation[$refPath . $function . $this->sqlBuilder->getSelectQueryHash($this->getPreviousAccessedColumns())];

		if ($aggregation === null) {
			$aggregation = [];

			$selection = $this->createSelectionInstance();
			$selection->getSqlBuilder()->importConditions($this->getSqlBuilder());

			if ($groupFunction && $selection->getSqlBuilder()->importGroupConditions($this->getSqlBuilder())) {
				$selection->select("$function AS aggregate, $this->name.$this->column AS groupname");
				$selection->group($selection->getSqlBuilder()->getGroup() . ", $this->name.$this->column");
				$query = "SELECT $groupFunction(aggregate) AS groupaggregate, groupname FROM (" . $selection->getSql() . ') AS aggregates GROUP BY groupname';
				foreach ($this->explorer->query($query, ...$selection->getSqlBuilder()->getParameters()) as $row) {
					$aggregation[$row->groupname] = $row;
				}
			} else {
				$selection->select($function);
				$selection->select("$this->name.$this->column");
				$selection->group("$this->name.$this->column");
				foreach ($selection as $row) {
					$aggregation[$row[$this->column]] = $row;
				}
			}
		}

		if (isset($aggregation[$this->active])) {
			foreach ($aggregation[$this->active] as $val) {
				return $val;
			}
		}

		return 0;
	}


	public function count(?string $column = null): int
	{
		return parent::count($column);
	}


	/********************* internal ****************d*g**/


	protected function execute(): void
	{
		if ($this->rows !== null) {
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
				if (
					$limit === null
					|| $rows <= 1
					|| (count($ref ?? []) < $limit
						&& $skip >= $this->sqlBuilder->getOffset())
				) {
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


	protected function emptyResultSet(bool $clearCache = true, bool $deleteReferencedCache = true): void
	{
		parent::emptyResultSet($clearCache, deleteReferencedCache: false);
	}


	/********************* manipulation ****************d*g**/


	public function insert(iterable $data)
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
