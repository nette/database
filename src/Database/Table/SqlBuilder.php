<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Table;

use Nette;
use Nette\Database\Conventions;
use Nette\Database\Driver;
use Nette\Database\Explorer;
use Nette\Database\IStructure;
use Nette\Database\SqlLiteral;


/**
 * Builds SQL query.
 * SqlBuilder is based on great library NotORM http://www.notorm.com written by Jakub Vrana.
 */
class SqlBuilder
{
	use Nette\SmartObject;

	/** @var string */
	protected $tableName;

	/** @var Conventions */
	protected $conventions;

	/** @var string delimited table name */
	protected $delimitedTable;

	/** @var array of column to select */
	protected $select = [];

	/** @var array of where conditions */
	protected $where = [];

	/** @var array of array of join conditions */
	protected $joinCondition = [];

	/** @var array of where conditions for caching */
	protected $conditions = [];

	/** @var array of parameters passed to where conditions */
	protected $parameters = [
		'select' => [],
		'joinCondition' => [],
		'where' => [],
		'group' => [],
		'having' => [],
		'order' => [],
	];

	/** @var array or columns to order by */
	protected $order = [];

	/** @var int number of rows to fetch */
	protected $limit;

	/** @var int first row to fetch */
	protected $offset;

	/** @var string columns to grouping */
	protected $group = '';

	/** @var string grouping condition */
	protected $having = '';

	/** @var array of reserved table names associated with chain */
	protected $reservedTableNames = [];

	/** @var array of table aliases */
	protected $aliases = [];

	/** @var string currently parsing alias for joins */
	protected $currentAlias;

	/** @var Driver */
	private $driver;

	/** @var IStructure */
	private $structure;

	/** @var array */
	private $cacheTableList;

	/** @var array of expanding joins */
	private $expandingJoins = [];


	public function __construct(string $tableName, Explorer $explorer)
	{
		$this->tableName = $tableName;
		$this->driver = $explorer->getConnection()->getDriver();
		$this->conventions = $explorer->getConventions();
		$this->structure = $explorer->getStructure();
		$tableNameParts = explode('.', $tableName);
		$this->delimitedTable = implode('.', array_map([$this->driver, 'delimite'], $tableNameParts));
		$this->checkUniqueTableName(end($tableNameParts), $tableName);
	}


	public function getTableName(): string
	{
		return $this->tableName;
	}


	public function buildInsertQuery(): string
	{
		return "INSERT INTO {$this->delimitedTable}";
	}


	public function buildUpdateQuery(): string
	{
		$query = "UPDATE {$this->delimitedTable} SET ?set" . $this->tryDelimite($this->buildConditions());

		if ($this->order !== []) {
			$query .= ' ORDER BY ' . implode(', ', $this->order);
		}

		if ($this->limit !== null || $this->offset) {
			$this->driver->applyLimit($query, $this->limit, $this->offset);
		}

		return $query;
	}


	public function buildDeleteQuery(): string
	{
		$query = "DELETE FROM {$this->delimitedTable}" . $this->tryDelimite($this->buildConditions());
		if ($this->limit !== null || $this->offset) {
			$this->driver->applyLimit($query, $this->limit, $this->offset);
		}

		return $query;
	}


	/**
	 * Returns select query hash for caching.
	 */
	public function getSelectQueryHash(array $columns = null): string
	{
		$parts = [
			'delimitedTable' => $this->delimitedTable,
			'queryCondition' => $this->buildConditions(),
			'queryEnd' => $this->buildQueryEnd(),
			$this->aliases,
			$this->limit, $this->offset,
		];
		if ($this->select) {
			$parts[] = $this->select;
		} elseif ($columns) {
			$parts[] = [$this->delimitedTable, $columns];
		} elseif ($this->group && !$this->driver->isSupported(Driver::SUPPORT_SELECT_UNGROUPED_COLUMNS)) {
			$parts[] = [$this->group];
		} else {
			$parts[] = "{$this->delimitedTable}.*";
		}
		return $this->getConditionHash(json_encode($parts), [
			$this->parameters['select'],
			$this->parameters['joinCondition'],
			$this->parameters['where'],
			$this->parameters['group'],
			$this->parameters['having'],
			$this->parameters['order'],
		]);
	}


	/**
	 * Returns SQL query.
	 * @param  string[]  $columns
	 */
	public function buildSelectQuery(array $columns = null): string
	{
		if (!$this->order && ($this->limit !== null || $this->offset)) {
			$this->order = array_map(
				function ($col) { return "$this->tableName.$col"; },
				(array) $this->conventions->getPrimary($this->tableName)
			);
		}

		$queryJoinConditions = $this->buildJoinConditions();
		$queryCondition = $this->buildConditions();
		$queryEnd = $this->buildQueryEnd();

		$joins = [];
		$finalJoinConditions = $this->parseJoinConditions($joins, $queryJoinConditions);
		$this->parseJoins($joins, $queryCondition);
		$this->parseJoins($joins, $queryEnd);

		if ($this->select) {
			$querySelect = $this->buildSelect($this->select);
			$this->parseJoins($joins, $querySelect);

		} elseif ($columns) {
			$prefix = $joins ? "{$this->delimitedTable}." : '';
			$cols = [];
			foreach ($columns as $col) {
				$cols[] = $prefix . $col;
			}
			$querySelect = $this->buildSelect($cols);

		} elseif ($this->group && !$this->driver->isSupported(Driver::SUPPORT_SELECT_UNGROUPED_COLUMNS)) {
			$querySelect = $this->buildSelect([$this->group]);
			$this->parseJoins($joins, $querySelect);

		} else {
			$prefix = $joins ? "{$this->delimitedTable}." : '';
			$querySelect = $this->buildSelect([$prefix . '*']);
		}

		$queryJoins = $this->buildQueryJoins($joins, $finalJoinConditions);
		$query = "{$querySelect} FROM {$this->delimitedTable}{$queryJoins}{$queryCondition}{$queryEnd}";

		$this->driver->applyLimit($query, $this->limit, $this->offset);

		return $this->tryDelimite($query);
	}


	public function getParameters(): array
	{
		if (!isset($this->parameters['joinConditionSorted'])) {
			$this->buildSelectQuery();
		}
		return array_merge(
			$this->parameters['select'],
			$this->parameters['joinConditionSorted'] ? array_merge(...array_values($this->parameters['joinConditionSorted'])) : [],
			$this->parameters['where'],
			$this->parameters['group'],
			$this->parameters['having'],
			$this->parameters['order']
		);
	}


	public function importConditions(self $builder): void
	{
		$this->where = $builder->where;
		$this->joinCondition = $builder->joinCondition;
		$this->parameters['where'] = $builder->parameters['where'];
		$this->parameters['joinCondition'] = $builder->parameters['joinCondition'];
		$this->conditions = $builder->conditions;
		$this->aliases = $builder->aliases;
		$this->reservedTableNames = $builder->reservedTableNames;
	}


	public function importGroupConditions(self $builder): bool
	{
		if ($builder->having) {
			$this->group = $builder->group;
			$this->having = $builder->having;
			$this->parameters['group'] = $builder->parameters['group'];
			$this->parameters['having'] = $builder->parameters['having'];
			return true;
		}
		return false;
	}


	/********************* SQL selectors ****************d*g**/


	public function addSelect($columns, ...$params): void
	{
		if (is_array($columns)) {
			throw new Nette\InvalidArgumentException('Select column must be a string.');
		}
		$this->select[] = $columns;
		$this->parameters['select'] = array_merge($this->parameters['select'], $params);
	}


	public function getSelect(): array
	{
		return $this->select;
	}


	public function resetSelect(): void
	{
		$this->select = [];
		$this->parameters['select'] = [];
	}


	public function addWhere($condition, ...$params): bool
	{
		return $this->addCondition($condition, $params, $this->where, $this->parameters['where']);
	}


	public function addJoinCondition(string $tableChain, $condition, ...$params): bool
	{
		$this->parameters['joinConditionSorted'] = null;
		if (!isset($this->joinCondition[$tableChain])) {
			$this->joinCondition[$tableChain] = $this->parameters['joinCondition'][$tableChain] = [];
		}
		return $this->addCondition($condition, $params, $this->joinCondition[$tableChain], $this->parameters['joinCondition'][$tableChain]);
	}


	protected function addCondition($condition, array $params, array &$conditions, array &$conditionsParameters): bool
	{
		if (is_array($condition) && !empty($params[0]) && is_array($params[0])) {
			return $this->addConditionComposition($condition, $params[0], $conditions, $conditionsParameters);
		}

		$hash = $this->getConditionHash($condition, $params);
		if (isset($this->conditions[$hash])) {
			return false;
		}

		$this->conditions[$hash] = $condition;
		$placeholderCount = substr_count($condition, '?');
		if ($placeholderCount > 1 && count($params) === 1 && is_array($params[0])) {
			$params = $params[0];
		}

		$condition = trim($condition);
		if ($placeholderCount === 0 && count($params) === 1) {
			$condition .= ' ?';
		} elseif ($placeholderCount !== count($params)) {
			throw new Nette\InvalidArgumentException('Argument count does not match placeholder count.');
		}

		$replace = null;
		$placeholderNum = 0;
		while (count($params)) {
			$arg = array_shift($params);
			preg_match('#(?:.*?\?.*?){' . $placeholderNum . '}(((?:&|\||^|~|\+|-|\*|/|%|\(|,|<|>|=|(?<=\W|^)(?:REGEXP|ALL|AND|ANY|BETWEEN|EXISTS|IN|[IR]?LIKE|OR|NOT|SOME|INTERVAL))\s*)?(?:\(\?\)|\?))#s', $condition, $match, PREG_OFFSET_CAPTURE);
			$hasOperator = ($match[1][0] === '?' && $match[1][1] === 0)
				? true
				: !empty($match[2][0]);

			if ($arg === null) {
				$replace = 'IS NULL';
				if ($hasOperator) {
					if (trim($match[2][0]) === 'NOT') {
						$replace = 'IS NOT NULL';
					} else {
						throw new Nette\InvalidArgumentException('Column operator does not accept null argument.');
					}
				}
			} elseif (is_array($arg) || $arg instanceof Selection) {
				if ($hasOperator) {
					if (trim($match[2][0]) === 'NOT') {
						$match[2][0] = rtrim($match[2][0]) . ' IN ';
					} elseif (trim($match[2][0]) !== 'IN') {
						throw new Nette\InvalidArgumentException('Column operator does not accept array argument.');
					}
				} else {
					$match[2][0] = 'IN ';
				}

				if ($arg instanceof Selection) {
					$clone = clone $arg;
					if (!$clone->getSqlBuilder()->select) {
						try {
							$clone->select($clone->getPrimary());
						} catch (\LogicException $e) {
							throw new Nette\InvalidArgumentException('Selection argument must have defined a select column.', 0, $e);
						}
					}

					if ($this->driver->isSupported(Driver::SUPPORT_SUBSELECT)) {
						$arg = null;
						$subSelectPlaceholderCount = substr_count($clone->getSql(), '?');
						$replace = $match[2][0] . '(' . $clone->getSql() . (!$subSelectPlaceholderCount && count($clone->getSqlBuilder()->getParameters()) === 1 ? ' ?' : '') . ')';
						if (count($clone->getSqlBuilder()->getParameters())) {
							array_unshift($params, ...$clone->getSqlBuilder()->getParameters());
						}
					} else {
						$arg = [];
						foreach ($clone as $row) {
							$arg[] = array_values(iterator_to_array($row));
						}
					}
				}

				if ($arg !== null) {
					if (!$arg) {
						$hasBrackets = strpos($condition, '(') !== false;
						$hasOperators = preg_match('#AND|OR#', $condition);
						$hasNot = strpos($condition, 'NOT') !== false;
						$hasPrefixNot = strpos($match[2][0], 'NOT') !== false;
						if (!$hasBrackets && ($hasOperators || ($hasNot && !$hasPrefixNot))) {
							throw new Nette\InvalidArgumentException('Possible SQL query corruption. Add parentheses around operators.');
						}
						$replace = $hasPrefixNot
							? 'IS NULL OR TRUE'
							: 'IS NULL AND FALSE';
						$arg = null;
					} else {
						$replace = $match[2][0] . '(?)';
						$conditionsParameters[] = $arg;
					}
				}
			} elseif ($arg instanceof SqlLiteral) {
				$conditionsParameters[] = $arg;
			} else {
				if (!$hasOperator) {
					$replace = '= ?';
				}
				$conditionsParameters[] = $arg;
			}

			if ($replace) {
				$condition = substr_replace($condition, $replace, $match[1][1], strlen($match[1][0]));
				$replace = null;
			}

			if ($arg !== null) {
				$placeholderNum++;
			}
		}

		$conditions[] = $condition;
		return true;
	}


	public function getConditions(): array
	{
		return array_values($this->conditions);
	}


	/**
	 * Adds alias.
	 */
	public function addAlias(string $chain, string $alias): void
	{
		if (isset($chain[0]) && $chain[0] !== '.' && $chain[0] !== ':') {
			$chain = '.' . $chain; // unified chain format
		}
		$this->checkUniqueTableName($alias, $chain);
		$this->aliases[$alias] = $chain;
	}


	protected function checkUniqueTableName(string $tableName, string $chain): void
	{
		if (isset($this->aliases[$tableName]) && ($chain === '.' . $tableName)) {
			$chain = $this->aliases[$tableName];
		}
		if (isset($this->reservedTableNames[$tableName])) {
			if ($this->reservedTableNames[$tableName] === $chain) {
				return;
			}
			throw new Nette\InvalidArgumentException("Table alias '$tableName' from chain '$chain' is already in use by chain '{$this->reservedTableNames[$tableName]}'. Please add/change alias for one of them.");
		}
		$this->reservedTableNames[$tableName] = $chain;
	}


	public function addOrder($columns, ...$params): void
	{
		$this->order[] = $columns;
		$this->parameters['order'] = array_merge($this->parameters['order'], $params);
	}


	public function setOrder(array $columns, array $parameters): void
	{
		$this->order = $columns;
		$this->parameters['order'] = $parameters;
	}


	public function getOrder(): array
	{
		return $this->order;
	}


	public function setLimit(?int $limit, ?int $offset): void
	{
		$this->limit = $limit;
		$this->offset = $offset;
	}


	public function getLimit(): ?int
	{
		return $this->limit;
	}


	public function getOffset(): ?int
	{
		return $this->offset;
	}


	public function setGroup($columns, ...$params): void
	{
		$this->group = $columns;
		$this->parameters['group'] = $params;
	}


	public function getGroup(): string
	{
		return $this->group;
	}


	public function setHaving($having, ...$params): void
	{
		$this->having = $having;
		$this->parameters['having'] = $params;
	}


	public function getHaving(): string
	{
		return $this->having;
	}


	/********************* SQL building ****************d*g**/


	protected function buildSelect(array $columns): string
	{
		return 'SELECT ' . implode(', ', $columns);
	}


	protected function parseJoinConditions(&$joins, $joinConditions): array
	{
		$tableJoins = $leftJoinDependency = $finalJoinConditions = [];
		foreach ($joinConditions as $tableChain => &$joinCondition) {
			$fooQuery = $tableChain . '.foo';
			$requiredJoins = [];
			$this->parseJoins($requiredJoins, $fooQuery);
			$tableAlias = substr($fooQuery, 0, -4);
			$tableJoins[$tableAlias] = $requiredJoins;
			$leftJoinDependency[$tableAlias] = [];
			$finalJoinConditions[$tableAlias] = preg_replace_callback($this->getColumnChainsRegxp(), function (array $match) use ($tableAlias, &$tableJoins, &$leftJoinDependency): string {
				$requiredJoins = [];
				$query = $this->parseJoinsCb($requiredJoins, $match);
				$queryParts = explode('.', $query);
				$tableJoins[$queryParts[0]] = $requiredJoins;
				if ($queryParts[0] !== $tableAlias) {
					foreach (array_keys($requiredJoins) as $requiredTable) {
						$leftJoinDependency[$tableAlias][$requiredTable] = $requiredTable;
					}
				}
				return $query;
			}, $joinCondition);
		}
		$this->parameters['joinConditionSorted'] = [];
		if (count($joinConditions)) {
			while ($tableJoins) {
				$this->getSortedJoins(key($tableJoins), $leftJoinDependency, $tableJoins, $joins);
			}
		}
		return $finalJoinConditions;
	}


	protected function getSortedJoins(string $table, &$leftJoinDependency, &$tableJoins, &$finalJoins): void
	{
		if (isset($this->expandingJoins[$table])) {
			$path = implode("' => '", array_map(function (string $value): string { return $this->reservedTableNames[$value]; }, array_merge(array_keys($this->expandingJoins), [$table])));
			throw new Nette\InvalidArgumentException("Circular reference detected at left join conditions (tables '$path').");
		}
		if (isset($tableJoins[$table])) {
			$this->expandingJoins[$table] = true;
			if (isset($leftJoinDependency[$table])) {
				foreach ($leftJoinDependency[$table] as $requiredTable) {
					if ($requiredTable === $table) {
						continue;
					}
					$this->getSortedJoins($requiredTable, $leftJoinDependency, $tableJoins, $finalJoins);
				}
			}
			if ($tableJoins[$table]) {
				foreach ($tableJoins[$table] as $requiredTable => $tmp) {
					if ($requiredTable === $table) {
						continue;
					}
					$this->getSortedJoins($requiredTable, $leftJoinDependency, $tableJoins, $finalJoins);
				}
			}
			$finalJoins += $tableJoins[$table];
			$key = isset($this->aliases[$table])
				? $table
				: $this->reservedTableNames[$table];
			if ($key[0] === '.') {
				$key = substr($key, 1);
			}
			$this->parameters['joinConditionSorted'] += isset($this->parameters['joinCondition'][$key])
				? [$table => $this->parameters['joinCondition'][$key]]
				: [];
			unset($tableJoins[$table], $this->expandingJoins[$table]);
		}
	}


	protected function parseJoins(&$joins, &$query): void
	{
		$query = preg_replace_callback($this->getColumnChainsRegxp(), function (array $match) use (&$joins): string {
			return $this->parseJoinsCb($joins, $match);
		}, $query);
	}


	private function getColumnChainsRegxp(): string
	{
		return '~
			(?P<chain> (?!\.) (?: [.:]? (?>[\w_]*[a-z][\w_]*) (\([\w_]*[a-z][\w_]*\))? ) *)  \. (?P<column> (?>[\w_]*[a-z][\w_]*) | \*  )
		~xi';
	}


	public function parseJoinsCb(&$joins, $match): string
	{
		$chain = $match['chain'];
		if (!empty($chain[0]) && ($chain[0] !== '.' && $chain[0] !== ':')) {
			$chain = '.' . $chain;  // unified chain format
		}

		preg_match_all('~
			(?P<del> [.:])?(?P<key> [\w_]*[a-z][\w_]* )(\((?P<throughColumn> [\w_]*[a-z][\w_]* )\))?
		~xi', $chain, $keyMatches, PREG_SET_ORDER);

		$parent = $this->tableName;
		$parentAlias = preg_replace('#^(.*\.)?(.*)$#', '$2', $this->tableName);

		// join schema keyMatch and table keyMatch to schema.table keyMatch
		if ($this->driver->isSupported(Driver::SUPPORT_SCHEMA) && count($keyMatches) > 1) {
			$tables = $this->getCachedTableList();
			if (
				!isset($tables[$keyMatches[0]['key']])
				&& isset($tables[$keyMatches[0]['key'] . '.' . $keyMatches[1]['key']])
			) {
				$keyMatch = array_shift($keyMatches);
				$keyMatches[0]['key'] = $keyMatch['key'] . '.' . $keyMatches[0]['key'];
				$keyMatches[0]['del'] = $keyMatch['del'];
			}
		}

		// do not make a join when referencing to the current table column - inner conditions
		// check it only when not making backjoin on itself - outer condition
		if ($keyMatches[0]['del'] === '.') {
			if (
				count($keyMatches) > 1
				&& ($parent === $keyMatches[0]['key']
					|| $parentAlias === $keyMatches[0]['key'])
			) {
				throw new Nette\InvalidArgumentException("Do not prefix table chain with origin table name '{$keyMatches[0]['key']}'. If you want to make self reference, please add alias.");
			}
			if ($parent === $keyMatches[0]['key']) {
				return "{$parent}.{$match['column']}";
			} elseif ($parentAlias === $keyMatches[0]['key']) {
				return "{$parentAlias}.{$match['column']}";
			}
		}
		$tableChain = null;
		foreach ($keyMatches as $index => $keyMatch) {
			$isLast = !isset($keyMatches[$index + 1]);
			if (!$index && isset($this->aliases[$keyMatch['key']])) {
				if ($keyMatch['del'] === ':') {
					throw new Nette\InvalidArgumentException("You are using has many syntax with alias (':{$keyMatch['key']}'). You have to move it to alias definition.");
				}
				$previousAlias = $this->currentAlias;
				$this->currentAlias = $keyMatch['key'];
				$requiredJoins = [];
				$query = $this->aliases[$keyMatch['key']] . '.foo';
				$this->parseJoins($requiredJoins, $query);
				$aliasJoin = array_pop($requiredJoins);
				$joins += $requiredJoins;
				[$table, , $parentAlias, $column, $primary] = $aliasJoin;
				$this->currentAlias = $previousAlias;

			} elseif ($keyMatch['del'] === ':') {
				if (isset($keyMatch['throughColumn'])) {
					$table = $keyMatch['key'];
					$belongsTo = $this->conventions->getBelongsToReference($table, $keyMatch['throughColumn']);
					if (!$belongsTo) {
						throw new Nette\InvalidArgumentException("No reference found for \${$parent}->{$keyMatch['key']}.");
					}
					[, $primary] = $belongsTo;

				} else {
					$hasMany = $this->conventions->getHasManyReference($parent, $keyMatch['key']);
					if (!$hasMany) {
						throw new Nette\InvalidArgumentException("No reference found for \${$parent}->related({$keyMatch['key']}).");
					}
					[$table, $primary] = $hasMany;
				}
				$column = $this->conventions->getPrimary($parent);

			} else {
				$belongsTo = $this->conventions->getBelongsToReference($parent, $keyMatch['key']);
				if (!$belongsTo) {
					throw new Nette\InvalidArgumentException("No reference found for \${$parent}->{$keyMatch['key']}.");
				}
				[$table, $column] = $belongsTo;
				$primary = $this->conventions->getPrimary($table);
			}

			if ($this->currentAlias && $isLast) {
				$tableAlias = $this->currentAlias;
			} elseif ($parent === $table) {
				$tableAlias = $parentAlias . '_ref';
			} elseif ($keyMatch['key']) {
				$tableAlias = $keyMatch['key'];
			} else {
				$tableAlias = preg_replace('#^(.*\.)?(.*)$#', '$2', $table);
			}

			$tableChain .= $keyMatch[0];
			if (!$isLast || !$this->currentAlias) {
				$this->checkUniqueTableName($tableAlias, $tableChain);
			}
			$joins[$tableAlias] = [$table, $tableAlias, $parentAlias, $column, $primary];
			$parent = $table;
			$parentAlias = $tableAlias;
		}

		return $tableAlias . ".{$match['column']}";
	}


	protected function buildQueryJoins(array $joins, array $leftJoinConditions = []): string
	{
		$return = '';
		foreach ($joins as [$joinTable, $joinAlias, $table, $tableColumn, $joinColumn]) {
			$return .=
				" LEFT JOIN {$joinTable}" . ($joinTable !== $joinAlias ? " {$joinAlias}" : '') .
				" ON {$table}.{$tableColumn} = {$joinAlias}.{$joinColumn}" .
				(isset($leftJoinConditions[$joinAlias]) ? " {$leftJoinConditions[$joinAlias]}" : '');
		}
		return $return;
	}


	protected function buildJoinConditions(): array
	{
		$conditions = [];
		foreach ($this->joinCondition as $tableChain => $joinConditions) {
			$conditions[$tableChain] = 'AND (' . implode(') AND (', $joinConditions) . ')';
		}
		return $conditions;
	}


	protected function buildConditions(): string
	{
		return $this->where
			? ' WHERE (' . implode(') AND (', $this->where) . ')'
			: '';
	}


	protected function buildQueryEnd(): string
	{
		$return = '';
		if ($this->group) {
			$return .= ' GROUP BY ' . $this->group;
		}
		if ($this->having) {
			$return .= ' HAVING ' . $this->having;
		}
		if ($this->order) {
			$return .= ' ORDER BY ' . implode(', ', $this->order);
		}
		return $return;
	}


	protected function tryDelimite(string $s): string
	{
		return preg_replace_callback('#(?<=[^\w`"\[?:]|^)[a-z_][a-z0-9_]*(?=[^\w`"(\]]|$)#Di', function (array $m): string {
			return strtoupper($m[0]) === $m[0]
				? $m[0]
				: $this->driver->delimite($m[0]);
		}, $s);
	}


	protected function addConditionComposition(
		array $columns,
		array $parameters,
		array &$conditions,
		array &$conditionsParameters
	): bool {
		if ($this->driver->isSupported(Driver::SUPPORT_MULTI_COLUMN_AS_OR_COND)) {
			$conditionFragment = '(' . implode(' = ? AND ', $columns) . ' = ?) OR ';
			$condition = substr(str_repeat($conditionFragment, count($parameters)), 0, -4);
			return $this->addCondition($condition, [Nette\Utils\Arrays::flatten($parameters)], $conditions, $conditionsParameters);
		} else {
			return $this->addCondition('(' . implode(', ', $columns) . ') IN', [$parameters], $conditions, $conditionsParameters);
		}
	}


	private function getConditionHash($condition, array $parameters): string
	{
		foreach ($parameters as $key => &$parameter) {
			if ($parameter instanceof Selection) {
				$parameter = $this->getConditionHash($parameter->getSql(), $parameter->getSqlBuilder()->getParameters());
			} elseif ($parameter instanceof SqlLiteral) {
				$parameter = $this->getConditionHash($parameter->__toString(), $parameter->getParameters());
			} elseif (is_object($parameter) && method_exists($parameter, '__toString')) {
				$parameter = $parameter->__toString();
			} elseif (is_array($parameter) || $parameter instanceof \ArrayAccess) {
				$parameter = $this->getConditionHash($key, $parameter);
			}
		}
		return md5($condition . json_encode($parameters));
	}


	private function getCachedTableList(): array
	{
		if (!$this->cacheTableList) {
			$this->cacheTableList = array_flip(array_map(function (array $pair): string {
				return $pair['fullName'] ?? $pair['name'];
			}, $this->structure->getTables()));
		}

		return $this->cacheTableList;
	}
}
