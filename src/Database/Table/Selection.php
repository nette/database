<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Table;

use Nette;
use Nette\Database\Explorer;
use function array_filter, array_intersect_key, array_keys, array_map, array_merge, array_values, ceil, count, current, explode, func_num_args, hash, implode, is_array, is_int, iterator_to_array, key, next, reset, serialize, str_contains, substr_count;


/**
 * Represents filtered table result.
 * Selection is based on the great library NotORM http://www.notorm.com written by Jakub Vrana.
 * @template T of ActiveRow
 * @implements \IteratorAggregate<T>
 * @implements \ArrayAccess<T>
 */
class Selection implements \IteratorAggregate, \ArrayAccess, \Countable
{
	protected readonly Explorer $explorer;
	protected readonly ?Nette\Caching\Cache $cache;
	protected SqlBuilder $sqlBuilder;

	/** table name */
	protected readonly string $name;

	/** @var string|string[]|null primary key field name */
	protected readonly string|array|null $primary;

	/** primary column sequence name, false for autodetection */
	protected string|bool|null $primarySequence = false;

	/** @var array<T>|null data read from database in [primary key => ActiveRow] format */
	protected ?array $rows = null;

	/** @var array<T>|null modifiable data in [primary key => ActiveRow] format */
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


	/**
	 * Creates filtered table representation.
	 */
	public function __construct(
		Explorer $explorer,
		string $tableName,
	) {
		$this->explorer = $explorer;
		$this->name = $tableName;
		$this->cache = $explorer->getCache();
		$this->primary = $explorer->getConventions()->getPrimary($tableName);
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
	 * Returns table primary key.
	 * @return string|string[]|null
	 */
	public function getPrimary(bool $throw = true): string|array|null
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


	/** @return static<T> */
	public function setPrimarySequence(string $sequence): static
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
	 */
	public function getPreviousAccessedColumns(): array|bool
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
	 * @return T|null
	 */
	public function get(mixed $key): ?ActiveRow
	{
		$clone = clone $this;
		return $clone->wherePrimary($key)->fetch();
	}


	/**
	 * Returns the next row or null if there are no more rows.
	 * @return T|null
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
	 * @deprecated
	 */
	public function fetchField(?string $column = null): mixed
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
	 * Returns all rows as associative array, where first argument specifies key column and second value column.
	 * For duplicate keys, the last value is used. When using null as key, array is indexed from zero.
	 * Alternatively accepts callback returning value or key-value pairs.
	 * @return array<T|mixed>
	 */
	public function fetchPairs(string|int|\Closure|null $keyOrCallback = null, string|int|null $value = null): array
	{
		return Nette\Database\Helpers::toPairs($this->fetchAll(), $keyOrCallback, $value);
	}


	/**
	 * Returns all rows.
	 * @return T[]
	 */
	public function fetchAll(): array
	{
		return iterator_to_array($this);
	}


	/**
	 * Returns all rows as associative tree.
	 * @deprecated
	 */
	public function fetchAssoc(string $path): array
	{
		$rows = array_map('iterator_to_array', $this->fetchAll());
		return Nette\Utils\Arrays::associate($rows, $path);
	}


	/********************* sql selectors ****************d*g**/


	/**
	 * Adds select clause, more calls append to the end.
	 * @param  string  $columns  for example "column, MD5(column) AS column_md5"
	 * @return static<T>
	 */
	public function select(string $columns, ...$params): static
	{
		$this->emptyResultSet();
		$this->sqlBuilder->addSelect($columns, ...$params);
		return $this;
	}


	/**
	 * Adds condition for primary key.
	 * @return static<T>
	 */
	public function wherePrimary(mixed $key): static
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
	 * Adds where condition, more calls append with AND.
	 * @param  string|array  $condition  possibly containing ?
	 * @return static<T>
	 */
	public function where(string|array $condition, ...$params): static
	{
		$this->condition($condition, $params);
		return $this;
	}


	/**
	 * Adds ON condition when joining specified table, more calls appends with AND.
	 * @param  string  $tableChain  table chain or table alias for which you need additional left join condition
	 * @param  string  $condition  possibly containing ?
	 * @return static<T>
	 */
	public function joinWhere(string $tableChain, string $condition, ...$params): static
	{
		$this->condition($condition, $params, $tableChain);
		return $this;
	}


	/**
	 * Adds condition, more calls appends with AND.
	 * @param  string|string[]  $condition  possibly containing ?
	 */
	protected function condition(string|array $condition, array $params, ?string $tableChain = null): void
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
	 * @throws Nette\InvalidArgumentException
	 * @return static<T>
	 */
	public function whereOr(array $parameters): static
	{
		if (count($parameters) < 2) {
			return $this->where($parameters);
		}

		$columns = [];
		$values = [];
		foreach ($parameters as $key => $val) {
			if (is_int($key)) { // whereOr(['full condition'])
				$columns[] = $val;
			} elseif (!str_contains($key, '?')) { // whereOr(['column1' => 1])
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
	 * Adds ORDER BY clause, more calls appends to the end.
	 * @param  string  $columns  for example 'column1, column2 DESC'
	 * @return static<T>
	 */
	public function order(string $columns, ...$params): static
	{
		$this->emptyResultSet();
		$this->sqlBuilder->addOrder($columns, ...$params);
		return $this;
	}


	/**
	 * Sets LIMIT clause, more calls rewrite old values.
	 * @return static<T>
	 */
	public function limit(?int $limit, ?int $offset = null): static
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setLimit($limit, $offset);
		return $this;
	}


	/**
	 * Sets OFFSET using page number, more calls rewrite old values.
	 * @return static<T>
	 */
	public function page(int $page, int $itemsPerPage, &$numOfPages = null): static
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
	 * Sets GROUP BY clause, more calls rewrite old value.
	 * @return static<T>
	 */
	public function group(string $columns, ...$params): static
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setGroup($columns, ...$params);
		return $this;
	}


	/**
	 * Sets HAVING clause, more calls rewrite old value.
	 * @return static<T>
	 */
	public function having(string $having, ...$params): static
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setHaving($having, ...$params);
		return $this;
	}


	/**
	 * Aliases table. Example ':book:book_tag.tag', 'tg'
	 * @return static<T>
	 */
	public function alias(string $tableChain, string $alias): static
	{
		$this->sqlBuilder->addAlias($tableChain, $alias);
		return $this;
	}


	/********************* aggregations ****************d*g**/


	/**
	 * Executes aggregation function.
	 * @param  string  $function  select call in "FUNCTION(column)" format
	 */
	public function aggregation(string $function, ?string $groupFunction = null): mixed
	{
		$selection = $this->createSelectionInstance();
		$selection->getSqlBuilder()->importConditions($this->getSqlBuilder());
		if ($groupFunction && $selection->getSqlBuilder()->importGroupConditions($this->getSqlBuilder())) {
			$selection->select("$function AS aggregate");
			$query = "SELECT $groupFunction(aggregate) AS groupaggregate FROM (" . $selection->getSql() . ') AS aggregates';
			return $this->explorer->query($query, ...$selection->getSqlBuilder()->getParameters())->fetch()->groupaggregate;
		} else {
			$selection->select($function);
			foreach ($selection->fetch() ?? [] as $val) {
				return $val;
			}
			return null;
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
	 */
	public function min(string $column): mixed
	{
		return $this->aggregation("MIN($column)", 'MIN');
	}


	/**
	 * Returns maximum value from a column.
	 */
	public function max(string $column): mixed
	{
		return $this->aggregation("MAX($column)", 'MAX');
	}


	/**
	 * Returns sum of values in a column.
	 */
	public function sum(string $column): mixed
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

		$key = 0;
		$this->rows = [];
		$usedPrimary = true;
		while ($row = @$result->fetchAssoc()) { // @ may contain duplicate columns
			$row = $this->createRow($row);
			$usedPrimary = $usedPrimary && ($primary = $row->getSignature(false)) !== '';
			$this->rows[$usedPrimary ? $primary : $key++] = $row;
		}

		$this->data = $this->rows;

		if ($usedPrimary && $this->accessedColumns !== false) {
			foreach ((array) $this->primary as $primary) {
				$this->accessedColumns[$primary] = true;
			}
		}
	}


	/** @deprecated */
	protected function createRow(array $row): ActiveRow
	{
		return $this->explorer->createActiveRow($row, $this);
	}


	/** @deprecated */
	public function createSelectionInstance(?string $table = null): self
	{
		return $this->explorer->table($table ?: $this->name);
	}


	/** @deprecated */
	protected function createGroupedSelectionInstance(string $table, string $column): GroupedSelection
	{
		return $this->explorer->createGroupedSelection($this, $table, $column);
	}


	protected function query(string $query): Nette\Database\Result
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
	 */
	protected function getRefTable(&$refPath): self
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
		return $this->generalCacheKey = hash('xxh128', serialize($key));
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
	 * Inserts row in a table. Returns ActiveRow or number of affected rows for Selection or table without primary key.
	 * @param  iterable|Selection  $data
	 * @return T|array|int|bool
	 */
	public function insert(iterable $data): ActiveRow|array|int|bool
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
			$primaryKey[$primaryAutoincrementKey] = $this->explorer->getInsertId($this->explorer->getDatabaseEngine()->delimit($primarySequenceName));

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
	public function getReferencedTable(ActiveRow $row, ?string $table, ?string $column = null): ActiveRow|false|null
	{
		if (!$column) {
			$belongsTo = $this->explorer->getConventions()->getBelongsToReference($this->name, $table);
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
	 */
	public function getReferencingTable(
		string $table,
		?string $column = null,
		int|string|null $active = null,
	): ?GroupedSelection
	{
		if (str_contains($table, '.')) {
			[$table, $column] = explode('.', $table);
		} elseif (!$column) {
			$hasMany = $this->explorer->getConventions()->getHasManyReference($this->name, $table);
			if (!$hasMany) {
				return null;
			}

			[$table, $column] = $hasMany;
		}

		$prototype = &$this->refCache['referencingPrototype'][$this->getSpecificCacheKey()]["$table.$column"];
		if (!$prototype) {
			$prototype = $this->createGroupedSelectionInstance($table, $column);
			$prototype->where("$table.$column", array_keys((array) $this->rows));
			$prototype->getSpecificCacheKey();
		}

		$clone = clone $prototype;
		$clone->setActive($active);
		return $clone;
	}


	/********************* interface IteratorAggregate ****************d*g**/


	/** @return \Generator<T> */
	public function getIterator(): \Generator
	{
		$this->execute();
		foreach ($this->data as $key => $value) {
			if (isset($this->data[$key])) { // may be unset by offsetUnset
				yield $key => $value;
			}
		}
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
	 * @return ?T
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
