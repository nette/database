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
 * Filtered table representation.
 * Selection is based on the great library NotORM http://www.notorm.com written by Jakub Vrana.
 */
class Selection implements \Iterator, IRowContainer, \ArrayAccess, \Countable
{
	use Nette\SmartObject;

	/** @var Context */
	protected $context;

	/** @var IConventions */
	protected $conventions;

	/** @var Nette\Caching\Cache */
	protected $cache;

	/** @var SqlBuilder */
	protected $sqlBuilder;

	/** @var string table name */
	protected $name;

	/** @var string|array|null primary key field name */
	protected $primary;

	/** @var string|bool primary column sequence name, false for autodetection */
	protected $primarySequence = false;

	/** @var IRow[] data read from database in [primary key => IRow] format */
	protected $rows;

	/** @var IRow[] modifiable data in [primary key => IRow] format */
	protected $data;

	/** @var bool */
	protected $dataRefreshed = false;

	/** @var mixed cache array of Selection and GroupedSelection prototypes */
	protected $globalRefCache;

	/** @var mixed */
	protected $refCache;

	/** @var string */
	protected $generalCacheKey;

	/** @var string */
	protected $specificCacheKey;

	/** @var array of [conditions => [key => IRow]]; used by GroupedSelection */
	protected $aggregation = [];

	/** @var array of touched columns */
	protected $accessedColumns;

	/** @var array of earlier touched columns */
	protected $previousAccessedColumns;

	/** @var bool should instance observe accessed columns caching */
	protected $observeCache = false;

	/** @var array of primary key values */
	protected $keys = [];


	/**
	 * Creates filtered table representation.
	 * @param  Context
	 * @param  IConventions
	 * @param  string  table name
	 * @param  Nette\Caching\IStorage|null
	 */
	public function __construct(Context $context, IConventions $conventions, $tableName, Nette\Caching\IStorage $cacheStorage = null)
	{
		$this->context = $context;
		$this->conventions = $conventions;
		$this->name = $tableName;

		$this->cache = $cacheStorage ? new Nette\Caching\Cache($cacheStorage, 'Nette.Database.' . md5($context->getConnection()->getDsn())) : null;
		$this->primary = $conventions->getPrimary($tableName);
		$this->sqlBuilder = new SqlBuilder($tableName, $context);
		$this->refCache = &$this->getRefTable($refPath)->globalRefCache[$refPath];
	}


	public function __destruct()
	{
		$this->saveCacheState();
	}


	public function __clone()
	{
		$this->sqlBuilder = clone $this->sqlBuilder;
	}


	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}


	/**
	 * @param  bool
	 * @return string|array|null
	 */
	public function getPrimary($throw = true)
	{
		if ($this->primary === null && $throw) {
			throw new \LogicException("Table '{$this->name}' does not have a primary key.");
		}
		return $this->primary;
	}


	/**
	 * @return string|null
	 */
	public function getPrimarySequence()
	{
		if ($this->primarySequence === false) {
			$this->primarySequence = $this->context->getStructure()->getPrimaryKeySequence($this->name);
		}

		return $this->primarySequence;
	}


	/**
	 * @param  string
	 * @return static
	 */
	public function setPrimarySequence($sequence)
	{
		$this->primarySequence = $sequence;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getSql()
	{
		return $this->sqlBuilder->buildSelectQuery($this->getPreviousAccessedColumns());
	}


	/**
	 * Loads cache of previous accessed columns and returns it.
	 * @internal
	 * @return array|false
	 */
	public function getPreviousAccessedColumns()
	{
		if ($this->cache && $this->previousAccessedColumns === null) {
			$this->accessedColumns = $this->previousAccessedColumns = $this->cache->load($this->getGeneralCacheKey());
			if ($this->previousAccessedColumns === null) {
				$this->previousAccessedColumns = [];
			}
		}

		return array_keys(array_filter((array) $this->previousAccessedColumns));
	}


	/**
	 * @internal
	 * @return SqlBuilder
	 */
	public function getSqlBuilder()
	{
		return $this->sqlBuilder;
	}


	/********************* quick access ****************d*g**/


	/**
	 * Returns row specified by primary key.
	 * @param  mixed primary key
	 * @return IRow or false if there is no such row
	 */
	public function get($key)
	{
		$clone = clone $this;
		return $clone->wherePrimary($key)->fetch();
	}


	/**
	 * @inheritDoc
	 */
	public function fetch()
	{
		$this->execute();
		$return = current($this->data);
		next($this->data);
		return $return;
	}


	/**
	 * Fetches single field.
	 * @param  string|null
	 * @return mixed|false
	 */
	public function fetchField($column = null)
	{
		if ($column) {
			$this->select($column);
		}

		$row = $this->fetch();
		if ($row) {
			return $column ? $row[$column] : array_values($row->toArray())[0];
		}

		return false;
	}


	/**
	 * @inheritDoc
	 */
	public function fetchPairs($key = null, $value = null)
	{
		return Nette\Database\Helpers::toPairs($this->fetchAll(), $key, $value);
	}


	/**
	 * @inheritDoc
	 */
	public function fetchAll()
	{
		return iterator_to_array($this);
	}


	/**
	 * @inheritDoc
	 */
	public function fetchAssoc($path)
	{
		$rows = array_map('iterator_to_array', $this->fetchAll());
		return Nette\Utils\Arrays::associate($rows, $path);
	}


	/********************* sql selectors ****************d*g**/


	/**
	 * Adds select clause, more calls appends to the end.
	 * @param  string|string[] for example "column, MD5(column) AS column_md5"
	 * @return static
	 */
	public function select($columns, ...$params)
	{
		$this->emptyResultSet();
		$this->sqlBuilder->addSelect($columns, ...$params);
		return $this;
	}


	/**
	 * Adds condition for primary key.
	 * @param  mixed
	 * @return static
	 */
	public function wherePrimary($key)
	{
		if (is_array($this->primary) && Nette\Utils\Arrays::isList($key)) {
			if (isset($key[0]) && is_array($key[0])) {
				$this->where($this->primary, $key);
			} else {
				foreach ($this->primary as $i => $primary) {
					$this->where($this->name . '.' . $primary, $key[$i]);
				}
			}
		} elseif (is_array($key) && !Nette\Utils\Arrays::isList($key)) { // key contains column names
			$this->where($key);
		} else {
			$this->where($this->name . '.' . $this->getPrimary(), $key);
		}

		return $this;
	}


	/**
	 * Adds where condition, more calls appends with AND.
	 * @param  string|string[] condition possibly containing ?
	 * @param  mixed
	 * @return static
	 */
	public function where($condition, ...$params)
	{
		$this->condition($condition, $params);
		return $this;
	}


	/**
	 * Adds ON condition when joining specified table, more calls appends with AND.
	 * @param  string table chain or table alias for which you need additional left join condition
	 * @param  string condition possibly containing ?
	 * @param  mixed
	 * @return static
	 */
	public function joinWhere($tableChain, $condition, ...$params)
	{
		$this->condition($condition, $params, $tableChain);
		return $this;
	}


	/**
	 * Adds condition, more calls appends with AND.
	 * @param  string|string[] condition possibly containing ?
	 * @return void
	 */
	protected function condition($condition, array $params, $tableChain = null)
	{
		$this->emptyResultSet();
		if (is_array($condition) && $params === []) { // where(array('column1' => 1, 'column2 > ?' => 2))
			foreach ($condition as $key => $val) {
				if (is_int($key)) {
					$this->condition($val, [], $tableChain); // where('full condition')
				} else {
					$this->condition($key, [$val], $tableChain); // where('column', 1)
				}
			}
		} elseif ($tableChain) {
			$this->sqlBuilder->addJoinCondition($tableChain, $condition, ...$params);
		} else {
			$this->sqlBuilder->addWhere($condition, ...$params);
		}
	}


	/**
	 * Adds where condition using the OR operator between parameters.
	 * More calls appends with AND.
	 * @param  array ['column1' => 1, 'column2 > ?' => 2, 'full condition']
	 * @return static
	 * @throws \Nette\InvalidArgumentException
	 */
	public function whereOr(array $parameters)
	{
		if (count($parameters) < 2) {
			return $this->where($parameters);
		}
		$columns = [];
		$values = [];
		foreach ($parameters as $key => $val) {
			if (is_int($key)) { // whereOr(['full condition'])
				$columns[] = $val;
			} elseif (strpos($key, '?') === false) { // whereOr(['column1' => 1])
				$columns[] = $key . ' ?';
				$values[] = $val;
			} else { // whereOr(['column1 > ?' => 1])
				$qNumber = substr_count($key, '?');
				if ($qNumber > 1 && (!is_array($val) || $qNumber !== count($val))) {
					throw new Nette\InvalidArgumentException('Argument count does not match placeholder count.');
				}
				$columns[] = $key;
				$values = array_merge($values, $qNumber > 1 ? $val : [$val]);
			}
		}
		$columnsString = '(' . implode(') OR (', $columns) . ')';
		return $this->where($columnsString, $values);
	}


	/**
	 * Adds order clause, more calls appends to the end.
	 * @param  string for example 'column1, column2 DESC'
	 * @return static
	 */
	public function order($columns, ...$params)
	{
		$this->emptyResultSet();
		$this->sqlBuilder->addOrder($columns, ...$params);
		return $this;
	}


	/**
	 * Sets limit clause, more calls rewrite old values.
	 * @param  int
	 * @param  int
	 * @return static
	 */
	public function limit($limit, $offset = null)
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setLimit($limit, $offset);
		return $this;
	}


	/**
	 * Sets offset using page number, more calls rewrite old values.
	 * @param  int
	 * @param  int
	 * @return static
	 */
	public function page($page, $itemsPerPage, &$numOfPages = null)
	{
		if (func_num_args() > 2) {
			$numOfPages = (int) ceil($this->count('*') / $itemsPerPage);
		}
		if ($page < 1) {
			$itemsPerPage = 0;
		}
		return $this->limit($itemsPerPage, ($page - 1) * $itemsPerPage);
	}


	/**
	 * Sets group clause, more calls rewrite old value.
	 * @param  string
	 * @return static
	 */
	public function group($columns, ...$params)
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setGroup($columns, ...$params);
		return $this;
	}


	/**
	 * Sets having clause, more calls rewrite old value.
	 * @param  string
	 * @return static
	 */
	public function having($having, ...$params)
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setHaving($having, ...$params);
		return $this;
	}


	/**
	 * Aliases table. Example ':book:book_tag.tag', 'tg'
	 * @param  string
	 * @param  string
	 * @return static
	 */
	public function alias($tableChain, $alias)
	{
		$this->sqlBuilder->addAlias($tableChain, $alias);
		return $this;
	}


	/********************* aggregations ****************d*g**/


	/**
	 * Executes aggregation function.
	 * @param  string select call in "FUNCTION(column)" format
	 * @return int
	 */
	public function aggregation($function)
	{
		$selection = $this->createSelectionInstance();
		$selection->getSqlBuilder()->importConditions($this->getSqlBuilder());
		$selection->select($function);
		foreach ($selection->fetch() as $val) {
			return $val;
		}
	}


	/**
	 * Counts number of rows.
	 * @param  string  if it is not provided returns count of result rows, otherwise runs new sql counting query
	 * @return int
	 */
	public function count($column = null)
	{
		if (!$column) {
			$this->execute();
			return count($this->data);
		}
		return $this->aggregation("COUNT($column)");
	}


	/**
	 * Returns minimum value from a column.
	 * @param  string
	 * @return int
	 */
	public function min($column)
	{
		return $this->aggregation("MIN($column)");
	}


	/**
	 * Returns maximum value from a column.
	 * @param  string
	 * @return int
	 */
	public function max($column)
	{
		return $this->aggregation("MAX($column)");
	}


	/**
	 * Returns sum of values in a column.
	 * @param  string
	 * @return int
	 */
	public function sum($column)
	{
		return $this->aggregation("SUM($column)");
	}


	/********************* internal ****************d*g**/


	protected function execute()
	{
		if ($this->rows !== null) {
			return;
		}

		$this->observeCache = $this;

		if ($this->primary === null && $this->sqlBuilder->getSelect() === null) {
			throw new Nette\InvalidStateException('Table with no primary key requires an explicit select clause.');
		}

		try {
			$result = $this->query($this->getSql());

		} catch (Nette\Database\DriverException $exception) {
			if (!$this->sqlBuilder->getSelect() && $this->previousAccessedColumns) {
				$this->previousAccessedColumns = false;
				$this->accessedColumns = [];
				$result = $this->query($this->getSql());
			} else {
				throw $exception;
			}
		}

		$this->rows = [];
		$usedPrimary = true;
		foreach ($result->getPdoStatement() as $key => $row) {
			$row = $this->createRow($result->normalizeRow($row));
			$primary = $row->getSignature(false);
			$usedPrimary = $usedPrimary && (string) $primary !== '';
			$this->rows[$usedPrimary ? $primary : $key] = $row;
		}
		$this->data = $this->rows;

		if ($usedPrimary && $this->accessedColumns !== false) {
			foreach ((array) $this->primary as $primary) {
				$this->accessedColumns[$primary] = true;
			}
		}
	}


	/**
	 * @return ActiveRow
	 */
	protected function createRow(array $row)
	{
		return new ActiveRow($row, $this);
	}


	/**
	 * @return self
	 */
	public function createSelectionInstance($table = null)
	{
		return new self($this->context, $this->conventions, $table ?: $this->name, $this->cache ? $this->cache->getStorage() : null);
	}


	/**
	 * @return GroupedSelection
	 */
	protected function createGroupedSelectionInstance($table, $column)
	{
		return new GroupedSelection($this->context, $this->conventions, $table, $column, $this, $this->cache ? $this->cache->getStorage() : null);
	}


	/**
	 * @return Nette\Database\ResultSet
	 */
	protected function query($query)
	{
		return $this->context->queryArgs($query, $this->sqlBuilder->getParameters());
	}


	protected function emptyResultSet($clearCache = true, $deleteRererencedCache = true)
	{
		if ($this->rows !== null && $clearCache) {
			$this->saveCacheState();
		}

		if ($clearCache) {
			// NOT NULL in case of missing some column
			$this->previousAccessedColumns = null;
			$this->generalCacheKey = null;
		}

		$this->rows = null;
		$this->specificCacheKey = null;
		$this->refCache['referencingPrototype'] = [];
		if ($deleteRererencedCache) {
			$this->refCache['referenced'] = [];
		}
	}


	protected function saveCacheState()
	{
		if ($this->observeCache === $this && $this->cache && !$this->sqlBuilder->getSelect() && $this->accessedColumns !== $this->previousAccessedColumns) {
			$previousAccessed = $this->cache->load($this->getGeneralCacheKey());
			$accessed = $this->accessedColumns;
			$needSave = is_array($accessed) && is_array($previousAccessed)
				? array_intersect_key($accessed, $previousAccessed) !== $accessed
				: $accessed !== $previousAccessed;

			if ($needSave) {
				$save = is_array($accessed) && is_array($previousAccessed) ? $previousAccessed + $accessed : $accessed;
				$this->cache->save($this->getGeneralCacheKey(), $save);
				$this->previousAccessedColumns = null;
			}
		}
	}


	/**
	 * Returns Selection parent for caching.
	 * @return static
	 */
	protected function getRefTable(&$refPath)
	{
		return $this;
	}


	/**
	 * Loads refCache references
	 */
	protected function loadRefCache()
	{
	}


	/**
	 * Returns general cache key independent on query parameters or sql limit
	 * Used e.g. for previously accessed columns caching
	 * @return string
	 */
	protected function getGeneralCacheKey()
	{
		if ($this->generalCacheKey) {
			return $this->generalCacheKey;
		}

		$key = [__CLASS__, $this->name, $this->sqlBuilder->getConditions()];
		$trace = [];
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $item) {
			$trace[] = isset($item['file'], $item['line']) ? $item['file'] . $item['line'] : null;
		}

		$key[] = $trace;
		return $this->generalCacheKey = md5(serialize($key));
	}


	/**
	 * Returns object specific cache key dependent on query parameters
	 * Used e.g. for reference memory caching
	 * @return string
	 */
	protected function getSpecificCacheKey()
	{
		if ($this->specificCacheKey) {
			return $this->specificCacheKey;
		}

		return $this->specificCacheKey = $this->sqlBuilder->getSelectQueryHash($this->getPreviousAccessedColumns());
	}


	/**
	 * @internal
	 * @param  string|null column name or null to reload all columns
	 * @param  bool
	 * @return bool if selection requeried for more columns.
	 */
	public function accessColumn($key, $selectColumn = true)
	{
		if (!$this->cache) {
			return false;
		}

		if ($key === null) {
			$this->accessedColumns = false;
			$currentKey = key((array) $this->data);
		} elseif ($this->accessedColumns !== false) {
			$this->accessedColumns[$key] = $selectColumn;
		}

		if ($selectColumn && $this->previousAccessedColumns && ($key === null || !isset($this->previousAccessedColumns[$key])) && !$this->sqlBuilder->getSelect()) {
			if ($this->sqlBuilder->getLimit()) {
				$generalCacheKey = $this->generalCacheKey;
				$sqlBuilder = $this->sqlBuilder;

				$primaryValues = [];
				foreach ((array) $this->rows as $row) {
					$primary = $row->getPrimary();
					$primaryValues[] = is_array($primary) ? array_values($primary) : $primary;
				}

				$this->emptyResultSet(false);
				$this->sqlBuilder = clone $this->sqlBuilder;
				$this->sqlBuilder->setLimit(null, null);
				$this->wherePrimary($primaryValues);

				$this->generalCacheKey = $generalCacheKey;
				$this->previousAccessedColumns = [];
				$this->execute();
				$this->sqlBuilder = $sqlBuilder;
			} else {
				$this->emptyResultSet(false);
				$this->previousAccessedColumns = [];
				$this->execute();
			}

			$this->dataRefreshed = true;

			// move iterator to specific key
			if (isset($currentKey)) {
				while (key($this->data) !== null && key($this->data) !== $currentKey) {
					next($this->data);
				}
			}
		}
		return $this->dataRefreshed;
	}


	/**
	 * @internal
	 * @param  string
	 */
	public function removeAccessColumn($key)
	{
		if ($this->cache && is_array($this->accessedColumns)) {
			$this->accessedColumns[$key] = false;
		}
	}


	/**
	 * Returns if selection requeried for more columns.
	 * @return bool
	 */
	public function getDataRefreshed()
	{
		return $this->dataRefreshed;
	}


	/********************* manipulation ****************d*g**/


	/**
	 * Inserts row in a table.
	 * @param  array|\Traversable|Selection array($column => $value)|\Traversable|Selection for INSERT ... SELECT
	 * @return IRow|int|bool Returns IRow or number of affected rows for Selection or table without primary key
	 */
	public function insert($data)
	{
		if ($data instanceof self) {
			$return = $this->context->queryArgs($this->sqlBuilder->buildInsertQuery() . ' ' . $data->getSql(), $data->getSqlBuilder()->getParameters());

		} else {
			if ($data instanceof \Traversable) {
				$data = iterator_to_array($data);
			}
			$return = $this->context->query($this->sqlBuilder->buildInsertQuery() . ' ?values', $data);
		}

		$this->loadRefCache();

		if ($data instanceof self || $this->primary === null) {
			unset($this->refCache['referencing'][$this->getGeneralCacheKey()][$this->getSpecificCacheKey()]);
			return $return->getRowCount();
		}

		$primarySequenceName = $this->getPrimarySequence();
		$primaryAutoincrementKey = $this->context->getStructure()->getPrimaryAutoincrementKey($this->name);

		$primaryKey = [];
		foreach ((array) $this->primary as $key) {
			if (isset($data[$key])) {
				$primaryKey[$key] = $data[$key];
			}
		}

		// First check sequence
		if (!empty($primarySequenceName) && $primaryAutoincrementKey) {
			$primaryKey[$primaryAutoincrementKey] = $this->context->getInsertId($this->context->getConnection()->getSupplementalDriver()->delimite($primarySequenceName));

		// Autoincrement primary without sequence
		} elseif ($primaryAutoincrementKey) {
			$primaryKey[$primaryAutoincrementKey] = $this->context->getInsertId($primarySequenceName);

		// Multi column primary without autoincrement
		} elseif (is_array($this->primary)) {
			foreach ($this->primary as $key) {
				if (!isset($data[$key])) {
					return $data;
				}
			}

		// Primary without autoincrement, try get primary from inserting data
		} elseif ($this->primary && isset($data[$this->primary])) {
			$primaryKey = $data[$this->primary];

		// If primaryKey cannot be prepared, return inserted rows count
		} else {
			unset($this->refCache['referencing'][$this->getGeneralCacheKey()][$this->getSpecificCacheKey()]);
			return $return->getRowCount();
		}

		$row = $this->createSelectionInstance()
			->select('*')
			->wherePrimary($primaryKey)
			->fetch();

		if ($this->rows !== null) {
			if ($signature = $row->getSignature(false)) {
				$this->rows[$signature] = $row;
				$this->data[$signature] = $row;
			} else {
				$this->rows[] = $row;
				$this->data[] = $row;
			}
		}

		return $row;
	}


	/**
	 * Updates all rows in result set.
	 * Joins in UPDATE are supported only in MySQL
	 * @param  iterable ($column => $value)
	 * @return int number of affected rows
	 */
	public function update($data)
	{
		if ($data instanceof \Traversable) {
			$data = iterator_to_array($data);

		} elseif (!is_array($data)) {
			throw new Nette\InvalidArgumentException;
		}

		if (!$data) {
			return 0;
		}

		return $this->context->queryArgs(
			$this->sqlBuilder->buildUpdateQuery(),
			array_merge([$data], $this->sqlBuilder->getParameters())
		)->getRowCount();
	}


	/**
	 * Deletes all rows in result set.
	 * @return int number of affected rows
	 */
	public function delete()
	{
		return $this->query($this->sqlBuilder->buildDeleteQuery())->getRowCount();
	}


	/********************* references ****************d*g**/


	/**
	 * Returns referenced row.
	 * @param  ActiveRow
	 * @param  string|null
	 * @param  string|null
	 * @return ActiveRow|null|false null if the row does not exist, false if the relationship does not exist
	 */
	public function getReferencedTable(ActiveRow $row, $table, $column = null)
	{
		if (!$column) {
			$belongsTo = $this->conventions->getBelongsToReference($this->name, $table);
			if (!$belongsTo) {
				return false;
			}
			list($table, $column) = $belongsTo;
		}
		if (!$row->accessColumn($column)) {
			return false;
		}

		$checkPrimaryKey = $row[$column];

		$referenced = &$this->refCache['referenced'][$this->getSpecificCacheKey()]["$table.$column"];
		$selection = &$referenced['selection'];
		$cacheKeys = &$referenced['cacheKeys'];
		if ($selection === null || ($checkPrimaryKey !== null && !isset($cacheKeys[$checkPrimaryKey]))) {
			$this->execute();
			$cacheKeys = [];
			foreach ($this->rows as $row) {
				if ($row[$column] === null) {
					continue;
				}

				$key = $row[$column];
				$cacheKeys[$key] = true;
			}

			if ($cacheKeys) {
				$selection = $this->createSelectionInstance($table);
				$selection->where($selection->getPrimary(), array_keys($cacheKeys));
			} else {
				$selection = [];
			}
		}

		return isset($selection[$checkPrimaryKey]) ? $selection[$checkPrimaryKey] : null;
	}


	/**
	 * Returns referencing rows.
	 * @param  string
	 * @param  string
	 * @param  int primary key
	 * @return GroupedSelection|null
	 */
	public function getReferencingTable($table, $column, $active = null)
	{
		if (strpos($table, '.') !== false) {
			list($table, $column) = explode('.', $table);
		} elseif (!$column) {
			$hasMany = $this->conventions->getHasManyReference($this->name, $table);
			if (!$hasMany) {
				return null;
			}
			list($table, $column) = $hasMany;
		}

		$prototype = &$this->refCache['referencingPrototype'][$this->getSpecificCacheKey()]["$table.$column"];
		if (!$prototype) {
			$prototype = $this->createGroupedSelectionInstance($table, $column);
			$prototype->where("$table.$column", array_keys((array) $this->rows));
		}

		$clone = clone $prototype;
		$clone->setActive($active);
		return $clone;
	}


	/********************* interface Iterator ****************d*g**/


	public function rewind()
	{
		$this->execute();
		$this->keys = array_keys($this->data);
		reset($this->keys);
	}


	/** @return IRow */
	public function current()
	{
		if (($key = current($this->keys)) !== false) {
			return $this->data[$key];
		} else {
			return false;
		}
	}


	/**
	 * @return string|int row ID
	 */
	public function key()
	{
		return current($this->keys);
	}


	public function next()
	{
		do {
			next($this->keys);
		} while (($key = current($this->keys)) !== false && !isset($this->data[$key]));
	}


	public function valid()
	{
		return current($this->keys) !== false;
	}


	/********************* interface ArrayAccess ****************d*g**/


	/**
	 * Mimic row.
	 * @param  string row ID
	 * @param  IRow
	 * @return void
	 */
	public function offsetSet($key, $value)
	{
		$this->execute();
		$this->rows[$key] = $value;
	}


	/**
	 * Returns specified row.
	 * @param  string row ID
	 * @return IRow|null if there is no such row
	 */
	public function offsetGet($key)
	{
		$this->execute();
		return $this->rows[$key];
	}


	/**
	 * Tests if row exists.
	 * @param  string row ID
	 * @return bool
	 */
	public function offsetExists($key)
	{
		$this->execute();
		return isset($this->rows[$key]);
	}


	/**
	 * Removes row from result set.
	 * @param  string row ID
	 * @return void
	 */
	public function offsetUnset($key)
	{
		$this->execute();
		unset($this->rows[$key], $this->data[$key]);
	}
}
