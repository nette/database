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
 * Filtered table representation.
 * Selection is based on the great library NotORM http://www.notorm.com written by Jakub Vrana.
 */
class Selection implements \Iterator, IRowContainer, \ArrayAccess, \Countable
{
	use Nette\SmartObject;

	protected Explorer $explorer;

	/** back compatibility */
	protected Explorer $context;
	protected Conventions $conventions;
	protected ?Nette\Caching\Cache $cache;
	protected SqlBuilder $sqlBuilder;

	/** table name */
	protected string $name;

	/** @var string|string[]|null primary key field name */
	protected string|array|null $primary;

	/** primary column sequence name, false for autodetection */
	protected string|bool|null $primarySequence = false;

	/** @var ActiveRow[]|null data read from database in [primary key => ActiveRow] format */
	protected ?array $rows = null;

	/** @var ActiveRow[]|null modifiable data in [primary key => ActiveRow] format */
	protected ?array $data = null;

	protected bool $dataRefreshed = false;

	/** cache array of Selection and GroupedSelection prototypes */
	protected mixed $globalRefCache;

	protected mixed $refCache;
	protected ?string $generalCacheKey = null;
	protected ?string $specificCacheKey = null;

	/** of [conditions => [key => ActiveRow]]; used by GroupedSelection */
	protected array $aggregation = [];
	protected array|false|null $accessedColumns = null;
	protected array|false|null $previousAccessedColumns = null;

	/** should instance observe accessed columns caching */
	protected ?self $observeCache = null;

	/** of primary key values */
	protected array $keys = [];


	/**
	 * Creates filtered table representation.
	 */
	public function __construct(
		Explorer $explorer,
		Conventions $conventions,
		string $tableName,
		?Nette\Caching\Storage $cacheStorage = null,
	) {
		$this->explorer = $this->context = $explorer;
		$this->conventions = $conventions;
		$this->name = $tableName;

		$this->cache = $cacheStorage
			? new Nette\Caching\Cache($cacheStorage, 'Nette.Database.' . md5($explorer->getConnection()->getDsn()))
			: null;
		$this->primary = $conventions->getPrimary($tableName);
		$this->sqlBuilder = new SqlBuilder($tableName, $explorer);
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


	public function getName(): string
	{
		return $this->name;
	}


	/**
	 * @return string|string[]|null
	 */
	public function getPrimary(bool $throw = true)
	{
		if ($this->primary === null && $throw) {
			throw new \LogicException("Table '{$this->name}' does not have a primary key.");
		}

		return $this->primary;
	}


	public function getPrimarySequence(): ?string
	{
		if ($this->primarySequence === false) {
			$this->primarySequence = $this->explorer->getStructure()->getPrimaryKeySequence($this->name);
		}

		return $this->primarySequence;
	}


	/**
	 * @return static
	 */
	public function setPrimarySequence(string $sequence)
	{
		$this->primarySequence = $sequence;
		return $this;
	}


	public function getSql(): string
	{
		return $this->sqlBuilder->buildSelectQuery($this->getPreviousAccessedColumns());
	}


	/**
	 * Loads cache of previous accessed columns and returns it.
	 * @internal
	 * @return array|bool
	 */
	public function getPreviousAccessedColumns()
	{
		if ($this->cache && $this->previousAccessedColumns === null) {
			$this->accessedColumns = $this->previousAccessedColumns = $this->cache->load($this->getGeneralCacheKey());
			$this->previousAccessedColumns ??= [];
		}

		return array_keys(array_filter((array) $this->previousAccessedColumns));
	}


	/**
	 * @internal
	 */
	public function getSqlBuilder(): SqlBuilder
	{
		return $this->sqlBuilder;
	}


	/********************* quick access ****************d*g**/


	/**
	 * Returns row specified by primary key.
	 * @param  mixed  $key  primary key
	 */
	public function get($key): ?ActiveRow
	{
		$clone = clone $this;
		return $clone->wherePrimary($key)->fetch();
	}


	/**
	 * Fetches single row object.
	 */
	public function fetch(): ?ActiveRow
	{
		$this->execute();
		$return = current($this->data);
		next($this->data);
		return $return === false ? null : $return;
	}


	/**
	 * Fetches single field.
	 * @return mixed
	 * @deprecated
	 */
	public function fetchField(?string $column = null)
	{
		if ($column) {
			$this->select($column);
		}

		$row = $this->fetch();
		if ($row) {
			return $column ? $row[$column] : array_values($row->toArray())[0];
		}

		return null;
	}


	/**
	 * Fetches all rows as associative array.
	 * @param  string|int  $key  column name used for an array key or null for numeric index
	 * @param  string|int  $value  column name used for an array value or null for the whole row
	 */
	public function fetchPairs($key = null, $value = null): array
	{
		return Nette\Database\Helpers::toPairs($this->fetchAll(), $key, $value);
	}


	/**
	 * Fetches all rows.
	 * @return ActiveRow[]
	 */
	public function fetchAll(): array
	{
		return iterator_to_array($this);
	}


	/**
	 * Fetches all rows and returns associative tree.
	 * @param  string  $path  associative descriptor
	 */
	public function fetchAssoc(string $path): array
	{
		$rows = array_map('iterator_to_array', $this->fetchAll());
		return Nette\Utils\Arrays::associate($rows, $path);
	}


	/********************* sql selectors ****************d*g**/


	/**
	 * Adds select clause, more calls appends to the end.
	 * @param  string  $columns  for example "column, MD5(column) AS column_md5"
	 * @return static
	 */
	public function select(string $columns, ...$params)
	{
		$this->emptyResultSet();
		$this->sqlBuilder->addSelect($columns, ...$params);
		return $this;
	}


	/**
	 * Adds condition for primary key.
	 * @param  mixed  $key
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
	 * @param  string|array  $condition  possibly containing ?
	 * @return static
	 */
	public function where($condition, ...$params)
	{
		$this->condition($condition, $params);
		return $this;
	}


	/**
	 * Adds ON condition when joining specified table, more calls appends with AND.
	 * @param  string  $tableChain  table chain or table alias for which you need additional left join condition
	 * @param  string  $condition  possibly containing ?
	 * @return static
	 */
	public function joinWhere(string $tableChain, string $condition, ...$params)
	{
		$this->condition($condition, $params, $tableChain);
		return $this;
	}


	/**
	 * Adds condition, more calls appends with AND.
	 * @param  string|string[]  $condition  possibly containing ?
	 */
	protected function condition($condition, array $params, ?string $tableChain = null): void
	{
		$this->emptyResultSet();
		if (is_array($condition) && $params === []) { // where(['column1' => 1, 'column2 > ?' => 2])
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
	 * @param  array  $parameters ['column1' => 1, 'column2 > ?' => 2, 'full condition']
	 * @return static
	 * @throws Nette\InvalidArgumentException
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
	 * @param  string  $columns  for example 'column1, column2 DESC'
	 * @return static
	 */
	public function order(string $columns, ...$params)
	{
		$this->emptyResultSet();
		$this->sqlBuilder->addOrder($columns, ...$params);
		return $this;
	}


	/**
	 * Sets limit clause, more calls rewrite old values.
	 * @return static
	 */
	public function limit(?int $limit, ?int $offset = null)
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setLimit($limit, $offset);
		return $this;
	}


	/**
	 * Sets offset using page number, more calls rewrite old values.
	 * @return static
	 */
	public function page(int $page, int $itemsPerPage, &$numOfPages = null)
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
	 * @return static
	 */
	public function group(string $columns, ...$params)
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setGroup($columns, ...$params);
		return $this;
	}


	/**
	 * Sets having clause, more calls rewrite old value.
	 * @return static
	 */
	public function having(string $having, ...$params)
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setHaving($having, ...$params);
		return $this;
	}


	/**
	 * Aliases table. Example ':book:book_tag.tag', 'tg'
	 * @return static
	 */
	public function alias(string $tableChain, string $alias)
	{
		$this->sqlBuilder->addAlias($tableChain, $alias);
		return $this;
	}


	/********************* aggregations ****************d*g**/


	/**
	 * Executes aggregation function.
	 * @param  string  $function  select call in "FUNCTION(column)" format
	 * @return mixed
	 */
	public function aggregation(string $function, ?string $groupFunction = null)
	{
		$selection = $this->createSelectionInstance();
		$selection->getSqlBuilder()->importConditions($this->getSqlBuilder());
		if ($groupFunction && $selection->getSqlBuilder()->importGroupConditions($this->getSqlBuilder())) {
			$selection->select("$function AS aggregate");
			$query = "SELECT $groupFunction(aggregate) AS groupaggregate FROM (" . $selection->getSql() . ') AS aggregates';
			return $this->explorer->query($query, ...$selection->getSqlBuilder()->getParameters())->fetch()->groupaggregate;
		} else {
			$selection->select($function);
			foreach ($selection->fetch() as $val) {
				return $val;
			}
		}
	}


	/**
	 * Counts number of rows. If column is not provided returns count of result rows, otherwise runs new sql counting query.
	 */
	public function count(?string $column = null): int
	{
		if (!$column) {
			$this->execute();
			return count($this->data);
		}

		return (int) $this->aggregation("COUNT($column)", 'SUM');
	}


	/**
	 * Returns minimum value from a column.
	 * @return mixed
	 */
	public function min(string $column)
	{
		return $this->aggregation("MIN($column)", 'MIN');
	}


	/**
	 * Returns maximum value from a column.
	 * @return mixed
	 */
	public function max(string $column)
	{
		return $this->aggregation("MAX($column)", 'MAX');
	}


	/**
	 * Returns sum of values in a column.
	 * @return mixed
	 */
	public function sum(string $column)
	{
		return $this->aggregation("SUM($column)", 'SUM');
	}


	/********************* internal ****************d*g**/


	protected function execute(): void
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
			$usedPrimary = $usedPrimary && $primary !== '';
			$this->rows[$usedPrimary ? $primary : $key] = $row;
		}

		$this->data = $this->rows;

		if ($usedPrimary && $this->accessedColumns !== false) {
			foreach ((array) $this->primary as $primary) {
				$this->accessedColumns[$primary] = true;
			}
		}
	}


	protected function createRow(array $row): ActiveRow
	{
		return new ActiveRow($row, $this);
	}


	public function createSelectionInstance(?string $table = null): self
	{
		return new self($this->explorer, $this->conventions, $table ?: $this->name, $this->cache ? $this->cache->getStorage() : null);
	}


	protected function createGroupedSelectionInstance(string $table, string $column): GroupedSelection
	{
		return new GroupedSelection($this->explorer, $this->conventions, $table, $column, $this, $this->cache ? $this->cache->getStorage() : null);
	}


	protected function query(string $query): Nette\Database\ResultSet
	{
		return $this->explorer->query($query, ...$this->sqlBuilder->getParameters());
	}


	protected function emptyResultSet(bool $clearCache = true, bool $deleteReferencedCache = true): void
	{
		if ($this->rows !== null && $clearCache) {
			$this->saveCacheState();
		}

		if ($clearCache) {
			// NOT NULL in case of missing some column
			$this->previousAccessedColumns = null;
			$this->generalCacheKey = null;
		}

		$null = null;
		$this->rows = &$null;
		$this->specificCacheKey = null;
		$this->refCache['referencingPrototype'] = [];
		if ($deleteReferencedCache) {
			$this->refCache['referenced'] = [];
		}
	}


	protected function saveCacheState(): void
	{
		if (
			$this->observeCache === $this
			&& $this->cache
			&& !$this->sqlBuilder->getSelect()
			&& $this->accessedColumns !== $this->previousAccessedColumns
		) {
			$previousAccessed = $this->cache->load($this->getGeneralCacheKey());
			$accessed = $this->accessedColumns;
			$needSave = is_array($accessed) && is_array($previousAccessed)
				? array_intersect_key($accessed, $previousAccessed) !== $accessed
				: $accessed !== $previousAccessed;

			if ($needSave) {
				$save = is_array($accessed) && is_array($previousAccessed)
					? $previousAccessed + $accessed
					: $accessed;
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
	protected function loadRefCache(): void
	{
	}


	/**
	 * Returns general cache key independent on query parameters or sql limit
	 * Used e.g. for previously accessed columns caching
	 */
	protected function getGeneralCacheKey(): string
	{
		if ($this->generalCacheKey) {
			return $this->generalCacheKey;
		}

		$key = [self::class, $this->name, $this->sqlBuilder->getConditions()];
		$trace = [];
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $item) {
			$trace[] = isset($item['file'], $item['line'])
				? $item['file'] . $item['line']
				: null;
		}

		$key[] = $trace;
		return $this->generalCacheKey = md5(serialize($key));
	}


	/**
	 * Returns object specific cache key dependent on query parameters
	 * Used e.g. for reference memory caching
	 */
	protected function getSpecificCacheKey(): string
	{
		if ($this->specificCacheKey) {
			return $this->specificCacheKey;
		}

		return $this->specificCacheKey = $this->sqlBuilder->getSelectQueryHash($this->getPreviousAccessedColumns());
	}


	/**
	 * @internal
	 * @param  string|null column name or null to reload all columns
	 * @return bool if selection requeried for more columns.
	 */
	public function accessColumn(?string $key, bool $selectColumn = true): bool
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

		if (
			$selectColumn
			&& $this->previousAccessedColumns
			&& (
				$key === null
				|| !isset($this->previousAccessedColumns[$key])
			)
			&& !$this->sqlBuilder->getSelect()
		) {
			if ($this->sqlBuilder->getLimit()) {
				$generalCacheKey = $this->generalCacheKey;
				$sqlBuilder = $this->sqlBuilder;

				$primaryValues = [];
				foreach ((array) $this->rows as $row) {
					$primary = $row->getPrimary();
					$primaryValues[] = is_array($primary)
						? array_values($primary)
						: $primary;
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
	 */
	public function removeAccessColumn(string $key): void
	{
		if ($this->cache && is_array($this->accessedColumns)) {
			$this->accessedColumns[$key] = false;
		}
	}


	/**
	 * Returns if selection requeried for more columns.
	 */
	public function getDataRefreshed(): bool
	{
		return $this->dataRefreshed;
	}


	/********************* manipulation ****************d*g**/


	/**
	 * Inserts row in a table.
	 * @param  iterable|Selection  $data  [$column => $value]|\Traversable|Selection for INSERT ... SELECT
	 * @return ActiveRow|int|bool Returns ActiveRow or number of affected rows for Selection or table without primary key
	 */
	public function insert(iterable $data)
	{
		//should be called before query for not to spoil PDO::lastInsertId
		$primarySequenceName = $this->getPrimarySequence();
		$primaryAutoincrementKey = $this->explorer->getStructure()->getPrimaryAutoincrementKey($this->name);

		if ($data instanceof self) {
			$return = $this->explorer->query($this->sqlBuilder->buildInsertQuery() . ' ' . $data->getSql(), ...$data->getSqlBuilder()->getParameters());

		} else {
			if ($data instanceof \Traversable) {
				$data = iterator_to_array($data);
			}

			$return = $this->explorer->query($this->sqlBuilder->buildInsertQuery() . ' ?values', $data);
		}

		$this->loadRefCache();

		if ($data instanceof self || $this->primary === null) {
			unset($this->refCache['referencing'][$this->getGeneralCacheKey()][$this->getSpecificCacheKey()]);
			return $return->getRowCount();
		}

		$primaryKey = [];
		foreach ((array) $this->primary as $key) {
			if (isset($data[$key])) {
				$primaryKey[$key] = $data[$key];
			}
		}

		// First check sequence
		if (!empty($primarySequenceName) && $primaryAutoincrementKey) {
			$primaryKey[$primaryAutoincrementKey] = $this->explorer->getInsertId($this->explorer->getConnection()->getDriver()->delimite($primarySequenceName));

		// Autoincrement primary without sequence
		} elseif ($primaryAutoincrementKey) {
			$primaryKey[$primaryAutoincrementKey] = $this->explorer->getInsertId($primarySequenceName);

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
	 * @return int number of affected rows
	 */
	public function update(iterable $data): int
	{
		if ($data instanceof \Traversable) {
			$data = iterator_to_array($data);
		}

		if (!$data) {
			return 0;
		}

		return $this->explorer->query(
			$this->sqlBuilder->buildUpdateQuery(),
			...array_merge([$data], $this->sqlBuilder->getParameters()),
		)->getRowCount();
	}


	/**
	 * Deletes all rows in result set.
	 * @return int number of affected rows
	 */
	public function delete(): int
	{
		return $this->query($this->sqlBuilder->buildDeleteQuery())->getRowCount();
	}


	/********************* references ****************d*g**/


	/**
	 * Returns referenced row.
	 * @return ActiveRow|false|null  null if the row does not exist, false if the relationship does not exist
	 */
	public function getReferencedTable(ActiveRow $row, ?string $table, ?string $column = null)
	{
		if (!$column) {
			$belongsTo = $this->conventions->getBelongsToReference($this->name, $table);
			if (!$belongsTo) {
				return false;
			}

			[$table, $column] = $belongsTo;
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

		return $selection[$checkPrimaryKey] ?? null;
	}


	/**
	 * Returns referencing rows.
	 * @param  int|string  $active  primary key
	 */
	public function getReferencingTable(string $table, ?string $column = null, $active = null): ?GroupedSelection
	{
		if (strpos($table, '.') !== false) {
			[$table, $column] = explode('.', $table);
		} elseif (!$column) {
			$hasMany = $this->conventions->getHasManyReference($this->name, $table);
			if (!$hasMany) {
				return null;
			}

			[$table, $column] = $hasMany;
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


	public function rewind(): void
	{
		$this->execute();
		$this->keys = array_keys($this->data);
		reset($this->keys);
	}


	/** @return ActiveRow|false */
	#[\ReturnTypeWillChange]
	public function current()
	{
		return ($key = current($this->keys)) !== false
			? $this->data[$key]
			: false;
	}


	/**
	 * @return string|int row ID
	 */
	#[\ReturnTypeWillChange]
	public function key()
	{
		return current($this->keys);
	}


	public function next(): void
	{
		do {
			next($this->keys);
		} while (($key = current($this->keys)) !== false && !isset($this->data[$key]));
	}


	public function valid(): bool
	{
		return current($this->keys) !== false;
	}


	/********************* interface ArrayAccess ****************d*g**/


	/**
	 * Mimic row.
	 * @param  string  $key
	 * @param  ActiveRow  $value
	 */
	public function offsetSet($key, $value): void
	{
		$this->execute();
		$this->rows[$key] = $value;
	}


	/**
	 * Returns specified row.
	 * @param  string  $key
	 */
	public function offsetGet($key): ?ActiveRow
	{
		$this->execute();
		return $this->rows[$key];
	}


	/**
	 * Tests if row exists.
	 * @param  string  $key
	 */
	public function offsetExists($key): bool
	{
		$this->execute();
		return isset($this->rows[$key]);
	}


	/**
	 * Removes row from result set.
	 * @param  string  $key
	 */
	public function offsetUnset($key): void
	{
		$this->execute();
		unset($this->rows[$key], $this->data[$key]);
	}
}
