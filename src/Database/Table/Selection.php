<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Table;

use Nette;
use Nette\Database\Conventions;
use Nette\Database\Explorer;
use function array_filter, array_intersect_key, array_keys, array_map, array_merge, array_values, ceil, count, current, explode, func_num_args, hash, implode, is_array, is_int, iterator_to_array, key, next, reset, serialize, str_contains, substr_count;


/**
 * Represents filtered table result.
 * Selection is based on the great library NotORM http://www.notorm.com written by Jakub Vrana.
 * @template T of ActiveRow
 * @implements \Iterator<string|int, T>
 * @implements \ArrayAccess<string|int, T>
 */
class Selection implements \Iterator, IRowContainer, \ArrayAccess, \Countable
{
	protected readonly Explorer $explorer;

	/** back compatibility */
	protected Explorer $context;
	protected readonly Conventions $conventions;
	protected readonly ?Nette\Caching\Cache $cache;
	protected SqlBuilder $sqlBuilder;

	/** table name */
	protected readonly string $name;

	/** @var string|string[]|null primary key field name */
	protected readonly string|array|null $primary;

	/** primary column sequence name, false for autodetection */
	protected string|false|null $primarySequence = false;

	/** @var ?array<T> data read from database in [primary key => ActiveRow] format */
	protected ?array $rows = null;

	/** @var ?array<T> modifiable data in [primary key => ActiveRow] format */
	protected ?array $data = null;

	protected bool $dataRefreshed = false;

	/** cache array of Selection and GroupedSelection prototypes */
	protected mixed $globalRefCache;

	protected mixed $refCache;
	protected ?string $generalCacheKey = null;
	protected ?string $specificCacheKey = null;

	/** @var array<string, array<int|string, Nette\Database\Row|ActiveRow>> of [conditions => [group value => row]]; used by GroupedSelection */
	protected array $aggregation = [];

	/** @var array<string, bool>|false|null column => selected */
	protected array|false|null $accessedColumns = null;

	/** @var array<string, bool>|false|null */
	protected array|false|null $previousAccessedColumns = null;

	/** @var ?self<ActiveRow> should instance observe accessed columns caching */
	protected ?self $observeCache = null;

	/** @var list<string|int> of primary key values */
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
			? new Nette\Caching\Cache($cacheStorage, 'Nette.Database.' . hash('xxh128', $explorer->getConnection()->getDsn()))
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
	 * Returns table primary key.
	 * @return ($throw is true ? string|string[] : string|string[]|null)
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
	 * @return list<string>
	 */
	public function getPreviousAccessedColumns(): array
	{
		if ($this->cache && $this->previousAccessedColumns === null) {
			$this->accessedColumns = $this->previousAccessedColumns = $this->cache->load($this->getGeneralCacheKey());
			$this->previousAccessedColumns ??= [];
		}

		return is_array($this->previousAccessedColumns)
			? array_keys(array_filter($this->previousAccessedColumns))
			: [];
	}


	/**
	 * @internal
	 */
	public function getSqlBuilder(): SqlBuilder
	{
		return $this->sqlBuilder;
	}


	public function getExplorer(): Explorer
	{
		return $this->explorer;
	}


	/********************* quick access ****************d*g**/


	/**
	 * Returns row specified by primary key.
	 * @return ?T
	 */
	public function get(mixed $key): ?ActiveRow
	{
		$clone = clone $this;
		return $clone->wherePrimary($key)->fetch();
	}


	/**
	 * Returns the next row or null if there are no more rows.
	 * @return ?T
	 */
	public function fetch(): ?ActiveRow
	{
		$this->execute();
		if ($this->data === null) {
			return null;
		}

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
	 * @param  string|int|\Closure(T): array{0: mixed, 1?: mixed}|null  $keyOrCallback
	 * @return array<mixed>
	 */
	public function fetchPairs(string|int|\Closure|null $keyOrCallback = null, string|int|null $value = null): array
	{
		return Nette\Database\Helpers::toPairs($this->fetchAll(), $keyOrCallback, $value);
	}


	/**
	 * Returns all rows as an array indexed by primary key.
	 * @return T[]
	 */
	public function fetchAll(): array
	{
		return iterator_to_array($this);
	}


	/**
	 * Returns all rows as associative tree.
	 * @deprecated
	 * @return array<mixed>
	 */
	public function fetchAssoc(string $path): array
	{
		$rows = array_map(iterator_to_array(...), $this->fetchAll());
		return (array) Nette\Utils\Arrays::associate($rows, $path);
	}


	/********************* sql selectors ****************d*g**/


	/**
	 * Adds select clause, more calls append to the end.
	 * @param  string  $columns  for example "column, MD5(column) AS column_md5"
	 */
	public function select(string $columns, mixed ...$params): static
	{
		$this->emptyResultSet();
		$this->sqlBuilder->addSelect($columns, ...$params);
		return $this;
	}


	/**
	 * Adds condition for primary key.
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
			$primary = $this->getPrimary();
			if (is_array($primary)) {
				throw new Nette\NotSupportedException("Table '{$this->name}' has composite primary key, pass values as a list or associative array.");
			}

			$this->where($this->name . '.' . $primary, $key);
		}

		return $this;
	}


	/**
	 * Adds where condition, more calls append with AND.
	 * @param  string|array<mixed>  $condition  possibly containing ?
	 */
	public function where(string|array $condition, mixed ...$params): static
	{
		$this->condition($condition, $params);
		return $this;
	}


	/**
	 * Adds ON condition when joining specified table, more calls appends with AND.
	 * @param  string  $tableChain  table chain or table alias for which you need additional left join condition
	 * @param  string  $condition  possibly containing ?
	 */
	public function joinWhere(string $tableChain, string $condition, mixed ...$params): static
	{
		$this->condition($condition, $params, $tableChain);
		return $this;
	}


	/**
	 * Adds a WHERE or JOIN condition. When $tableChain is given, the condition is added to the JOIN ON clause.
	 * @param  string|string[]  $condition  possibly containing ?
	 * @param  mixed[]  $params
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
	 * @param  array<mixed>  $parameters ['column1' => 1, 'column2 > ?' => 2, 'full condition']
	 * @throws Nette\InvalidArgumentException
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
	 */
	public function order(string $columns, mixed ...$params): static
	{
		$this->emptyResultSet();
		$this->sqlBuilder->addOrder($columns, ...$params);
		return $this;
	}


	/**
	 * Sets LIMIT clause, more calls rewrite old values.
	 */
	public function limit(?int $limit, ?int $offset = null): static
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setLimit($limit, $offset);
		return $this;
	}


	/**
	 * Sets LIMIT and OFFSET for the given page number. Optionally calculates total number of pages.
	 */
	public function page(int $page, int $itemsPerPage, ?int &$numOfPages = null): static
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
	 */
	public function group(string $columns, mixed ...$params): static
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setGroup($columns, ...$params);
		return $this;
	}


	/**
	 * Sets HAVING clause, more calls rewrite old value.
	 */
	public function having(string $having, mixed ...$params): static
	{
		$this->emptyResultSet();
		$this->sqlBuilder->setHaving($having, ...$params);
		return $this;
	}


	/**
	 * Aliases table. Example ':book:book_tag.tag', 'tg'
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
			$row = $this->explorer->query($query, ...$selection->getSqlBuilder()->getParameters())->fetch()
				?? throw new Nette\ShouldNotHappenException('Aggregation query returned no rows.');
			return $row->groupaggregate;
		} else {
			$selection->select($function);
			foreach ($selection->fetch() ?? [] as $val) {
				return $val;
			}
			return null;
		}
	}


	/**
	 * Returns count of fetched rows, or runs COUNT($column) query when column is specified.
	 */
	public function count(?string $column = null): int
	{
		if (!$column) {
			$this->execute();
			return count($this->data ?? []);
		}

		return max(0, (int) $this->aggregation("COUNT($column)", 'SUM'));
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
		$pdoStatement = $result->getPdoStatement() ?? throw new Nette\ShouldNotHappenException;
		foreach ($pdoStatement as $key => $row) {
			$row = $this->createRow($result->normalizeRow($row));
			$primary = $row->getSignature(throw: false);
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


	/**
	 * @deprecated
	 * @param  array<mixed>  $row
	 * @return T
	 */
	protected function createRow(array $row): ActiveRow
	{
		/** @var T */
		return $this->explorer->createActiveRow($row, $this);
	}


	/**
	 * @deprecated
	 * @return ($table is null ? Selection<T> : Selection<ActiveRow>)
	 */
	public function createSelectionInstance(?string $table = null): self
	{
		return $this->explorer->table($table ?: $this->name);
	}


	/**
	 * @deprecated
	 * @return GroupedSelection<ActiveRow>
	 */
	protected function createGroupedSelectionInstance(string $table, string $column): GroupedSelection
	{
		return $this->explorer->createGroupedSelection($this, $table, $column);
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
	 * Returns the root Selection used as the shared cache anchor for referenced rows.
	 * @param-out string $refPath
	 * @return Selection<ActiveRow>
	 */
	protected function getRefTable(mixed &$refPath): self
	{
		$refPath = '';
		return $this;
	}


	/**
	 * Initializes the reference cache for the current selection. Overridden by GroupedSelection.
	 */
	protected function loadRefCache(): void
	{
	}


	/**
	 * Returns general cache key independent of query parameters or SQL limit.
	 * Used e.g. for previously accessed columns caching.
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
	 * Returns object-specific cache key dependent on query parameters.
	 * Used e.g. for reference memory caching.
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
	 * @param  ?string  $key column name or null to reload all columns
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

				$this->emptyResultSet(clearCache: false);
				$this->sqlBuilder = clone $this->sqlBuilder;
				$this->sqlBuilder->setLimit(null, null);
				$this->wherePrimary($primaryValues);

				$this->generalCacheKey = $generalCacheKey;
				$this->previousAccessedColumns = [];
				$this->execute();
				$this->sqlBuilder = $sqlBuilder;
			} else {
				$this->emptyResultSet(clearCache: false);
				$this->previousAccessedColumns = [];
				$this->execute();
			}

			$this->dataRefreshed = true;

			// move iterator to specific key
			if (isset($currentKey) && $this->data !== null) {
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
	 * Checks whether the selection re-queried for additional columns.
	 */
	public function getDataRefreshed(): bool
	{
		return $this->dataRefreshed;
	}


	/********************* manipulation ****************d*g**/


	/**
	 * Inserts one or more rows into the table.
	 * Returns the inserted ActiveRow for single-row inserts, or the number of affected rows otherwise.
	 * @param  iterable<string, mixed>|Selection<ActiveRow>  $data
	 * @return ($data is array<string, mixed> ? T|array<string, mixed> : int)
	 */
	public function insert(iterable $data): ActiveRow|array|int
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
			return $return->getRowCount()
				?? throw new Nette\InvalidStateException('Cannot determine the number of affected rows.');
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
			return $return->getRowCount()
				?? throw new Nette\InvalidStateException('Cannot determine the number of affected rows.');
		}

		/** @phpstan-var T $row */
		$row = $this->createSelectionInstance()
			->select('*')
			->wherePrimary($primaryKey)
			->fetch()
			?? throw new Nette\ShouldNotHappenException;

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
	 * Updates all rows matching current conditions. JOINs in UPDATE are supported only by MySQL.
	 * @param  iterable<string, mixed>  $data
	 * @return int  number of affected rows
	 */
	public function update(iterable $data): int
	{
		if ($data instanceof \Traversable) {
			$data = iterator_to_array($data);
		}

		if (!$data) {
			return 0;
		}

		return $this->explorer
			->query($this->sqlBuilder->buildUpdateQuery(), ...array_merge([$data], $this->sqlBuilder->getParameters()))
			->getRowCount()
			?? throw new Nette\InvalidStateException('Cannot determine the number of affected rows.');
	}


	/**
	 * Deletes all rows matching current conditions.
	 * @return int  number of affected rows
	 */
	public function delete(): int
	{
		return $this->query($this->sqlBuilder->buildDeleteQuery())->getRowCount()
			?? throw new Nette\InvalidStateException('Cannot determine the number of affected rows.');
	}


	/********************* references ****************d*g**/


	/**
	 * Returns a referenced (parent) row for a belongs-to relationship.
	 * Returns null if the referenced row does not exist, false if the relationship is not defined.
	 * @return ActiveRow|false|null
	 */
	public function getReferencedTable(ActiveRow $row, ?string $table, ?string $column = null): ActiveRow|false|null
	{
		if (!$column) {
			if ($table === null) {
				return false;
			}

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
			foreach ($this->rows ?? [] as $row) {
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

		return $selection[$checkPrimaryKey ?? ''] ?? null;
	}


	/**
	 * Returns a grouped selection of referencing (child) rows for a has-many relationship.
	 * @return GroupedSelection<ActiveRow>|null
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
			$hasMany = $this->conventions->getHasManyReference($this->name, $table);
			if (!$hasMany) {
				return null;
			}

			[$table, $column] = $hasMany;
		}

		/** @var ?GroupedSelection<ActiveRow> $prototype */
		$prototype = &$this->refCache['referencingPrototype'][$this->getSpecificCacheKey()]["$table.$column"];
		if (!$prototype) {
			$prototype = $this->createGroupedSelectionInstance($table, $column);
			$prototype->where("$table.$column", array_keys((array) $this->rows));
			$prototype->getSpecificCacheKey();
		}

		$clone = clone $prototype;
		if ($active !== null) {
			$clone->setActive($active);
		}

		return $clone;
	}


	/********************* interface Iterator ****************d*g**/


	public function rewind(): void
	{
		$this->execute();
		$this->keys = array_keys($this->data ?? []);
		reset($this->keys);
	}


	/** @return T|false */
	public function current(): ActiveRow|false
	{
		$key = current($this->keys);
		return $key !== false && isset($this->data[$key])
			? $this->data[$key]
			: false;
	}


	public function key(): string|int
	{
		$key = current($this->keys);
		return $key === false ? -1 : $key;
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
	 * Sets a row by primary key.
	 * @param  string|int  $key
	 * @param  T  $value
	 */
	public function offsetSet($key, $value): void
	{
		$this->execute();
		$this->rows[$key] = $value;
	}


	/**
	 * Returns specified row.
	 * @param  string|int  $key
	 * @return ?T
	 */
	public function offsetGet($key): ?ActiveRow
	{
		$this->execute();
		return $this->rows[$key] ?? null;
	}


	/**
	 * Tests if row exists.
	 * @param  string|int  $key
	 */
	public function offsetExists($key): bool
	{
		$this->execute();
		return isset($this->rows[$key]);
	}


	/**
	 * Removes row from result set.
	 * @param  string|int  $key
	 */
	public function offsetUnset($key): void
	{
		$this->execute();
		unset($this->rows[$key], $this->data[$key]);
	}
}
