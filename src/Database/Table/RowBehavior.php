<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Table;

use Nette;
use function array_intersect_key, array_key_exists, array_keys, array_map, implode, is_array, is_string, iterator_to_array;


/**
 * Implementation of the Row interface: a database-attached row with support for relations.
 * Composing it together with Row allows a row class to extend a plain value base class
 * instead of ActiveRow.
 */
trait RowBehavior
{
	/** @var array<class-string, list<string>> */
	private static array $declaredProperties = [];

	private bool $dataRefreshed = false;
	private readonly ?Nette\Database\EntityMapping $entityMapping;


	public function __construct(
		/** @var array<string, mixed> */
		private array $data,
		/** @var Selection<ActiveRow> */
		private Selection $table,
	) {
		$this->entityMapping = $table->getExplorer()->getEntityMapping();
		foreach (self::declaredProperties(static::class) as $name) {
			unset($this->$name);
		}
	}


	/**
	 * Returns names of declared public non-static properties of the row class, so they can be unset to fall through to __get.
	 * @param  class-string  $class
	 * @return list<string>
	 */
	private static function declaredProperties(string $class): array
	{
		if (isset(self::$declaredProperties[$class])) {
			return self::$declaredProperties[$class];
		}
		$result = [];
		foreach ((new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
			if (!$prop->isStatic()) {
				$result[] = $prop->getName();
			}
		}
		return self::$declaredProperties[$class] = $result;
	}


	/**
	 * @internal
	 * @param Selection<ActiveRow> $table
	 */
	public function setTable(Selection $table): void
	{
		$this->table = $table;
	}


	/**
	 * @internal
	 * @return Selection<ActiveRow>
	 */
	public function getTable(): Selection
	{
		return $this->table;
	}


	public function getExplorer(): Nette\Database\Explorer
	{
		return $this->table->getExplorer();
	}


	public function __toString(): string
	{
		return (string) $this->getPrimary();
	}


	/** @return array<string, mixed> */
	public function toArray(): array
	{
		$this->accessColumn(null);
		$entityMapping = $this->entityMapping;
		if ($entityMapping) {
			$translated = [];
			foreach ($this->data as $key => $value) {
				$translated[$entityMapping->getPropertyName($key)] = $value;
			}
			return $translated;
		}
		return $this->data;
	}


	/**
	 * Returns primary key value, or an array of values for composite primary keys.
	 * Composite key arrays are keyed by database column names (unlike toArray(),
	 * which uses property names) so the result can be passed directly to
	 * Selection::wherePrimary().
	 */
	public function getPrimary(bool $throw = true): mixed
	{
		$primary = $this->table->getPrimary($throw);
		if ($primary === null) {
			return null;

		} elseif (!is_array($primary)) {
			if (isset($this->data[$primary])) {
				return $this->data[$primary];
			} elseif ($throw) {
				throw new Nette\InvalidStateException("Row does not contain primary $primary column data.");
			} else {
				return null;
			}
		} else {
			$primaryVal = [];
			foreach ($primary as $key) {
				if (!isset($this->data[$key])) {
					if ($throw) {
						throw new Nette\InvalidStateException("Row does not contain primary $key column data.");
					} else {
						return null;
					}
				}

				$primaryVal[$key] = $this->data[$key];
			}

			return $primaryVal;
		}
	}


	/**
	 * Returns row signature (composition of primary keys).
	 */
	public function getSignature(bool $throw = true): string
	{
		return implode('|', (array) $this->getPrimary($throw));
	}


	/**
	 * Returns referenced row, or null if the row does not exist.
	 */
	public function ref(string $key, ?string $throughColumn = null): ?ActiveRow
	{
		$row = $this->table->getReferencedTable($this, $key, $throughColumn);
		if ($row === false) {
			throw new Nette\MemberAccessException("No reference found for \${$this->table->getName()}->ref($key).");
		}

		return $row;
	}


	/**
	 * Returns referencing rows collection.
	 * @return GroupedSelection<ActiveRow>
	 */
	public function related(string $key, ?string $throughColumn = null): GroupedSelection
	{
		$primary = $this->table->getPrimary();
		if (!is_string($primary)) {
			throw new Nette\NotSupportedException('related() does not support tables with a composite primary key.');
		}

		$groupedSelection = $this->table->getReferencingTable($key, $throughColumn, $this->__get($primary));
		if (!$groupedSelection) {
			throw new Nette\MemberAccessException("No reference found for \${$this->table->getName()}->related($key).");
		}

		return $groupedSelection;
	}


	/**
	 * Updates row data and refreshes the instance from database. Returns true if the row was changed.
	 * @param  iterable<string, mixed>  $data
	 */
	public function update(iterable $data): bool
	{
		$data = iterator_to_array($data);

		$primary = $this->getPrimary();
		if (!is_array($primary)) {
			$primary = [$this->table->getPrimary() => $primary];
		}

		$selection = $this->table->createSelectionInstance()
			->wherePrimary($primary);

		if ($selection->update($data)) {
			$columnData = $this->entityMapping
				? Nette\Database\Helpers::translateColumns($data, $this->entityMapping)
				: $data;
			if ($tmp = array_intersect_key($columnData, $primary)) {
				$selection = $this->table->createSelectionInstance()
					->wherePrimary($tmp + $primary);
			}

			$selection->select('*');
			if (($row = $selection->fetch()) === null) {
				throw new Nette\InvalidStateException('Database refetch failed; row does not exist!');
			}

			$this->data = $row->data;
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Deletes the row from database.
	 * @return int  number of affected rows
	 */
	public function delete(): int
	{
		$res = $this->table->createSelectionInstance()
			->wherePrimary($this->getPrimary())
			->delete();

		if ($res > 0 && ($signature = $this->getSignature(throw: false))) {
			unset($this->table[$signature]);
		}

		return $res;
	}


	/********************* interface IteratorAggregate ****************d*g**/


	/** @return \ArrayIterator<string, mixed> */
	public function getIterator(): \Iterator
	{
		return new \ArrayIterator($this->toArray());
	}


	/********************* interface ArrayAccess & magic accessors ****************d*g**/


	public function offsetSet($column, $value): void
	{
		$this->__set($column, $value);
	}


	public function offsetGet($column): mixed
	{
		return $this->__get($column);
	}


	public function offsetExists($column): bool
	{
		return $this->__isset($column);
	}


	public function offsetUnset($column): void
	{
		$this->__unset($column);
	}


	public function __set(string $column, mixed $value): void
	{
		throw new Nette\DeprecatedException('ActiveRow is read-only; use update() method instead.');
	}


	/**
	 * Returns column value, or a referenced row if the key matches a relationship.
	 * @return ActiveRow|mixed
	 * @throws Nette\MemberAccessException  if the column does not exist and no relationship is found
	 */
	public function &__get(string $key): mixed
	{
		$column = $this->entityMapping?->getColumnName($key) ?? $key;

		if ($this->accessColumn($column)) {
			return $this->data[$column];
		}

		$referenced = $this->table->getReferencedTable($this, $key);
		if ($referenced !== false) {
			$this->accessColumn($key, selectColumn: false);
			return $referenced;
		}

		// the column may exist but be excluded from the narrowed SELECT, e.g. when it was
		// probed by isset() before a migration added it; reload all columns and retry once
		if ($this->table->getPreviousAccessedColumns() && !$this->table->getSqlBuilder()->getSelect()) {
			$this->accessColumn(null);
			if (array_key_exists($column, $this->data)) {
				return $this->data[$column];
			}
		}

		$this->removeAccessColumn($column);
		$available = $this->entityMapping
			? array_map(fn(string $col) => $this->entityMapping->getPropertyName($col), array_keys($this->data))
			: array_keys($this->data);
		$hint = Nette\Utils\Helpers::getSuggestion($available, $key);
		throw new Nette\MemberAccessException("Cannot read an undeclared column '$key'" . ($hint ? ", did you mean '$hint'?" : '.'));
	}


	public function __isset(string $key): bool
	{
		$column = $this->entityMapping?->getColumnName($key) ?? $key;

		if ($this->accessColumn($column)) {
			return isset($this->data[$column]);
		}

		$referenced = $this->table->getReferencedTable($this, $key);
		if ($referenced !== false) {
			$this->accessColumn($key, selectColumn: false);
			return (bool) $referenced;
		}

		$this->removeAccessColumn($column);
		return false;
	}


	public function __unset(string $key): void
	{
		throw new Nette\DeprecatedException('ActiveRow is read-only.');
	}


	/** @internal */
	public function accessColumn(?string $key, bool $selectColumn = true): bool
	{
		if ($this->table->accessColumn($key, $selectColumn) && !$this->dataRefreshed) {
			if (!isset($this->table[$this->getSignature()])) {
				throw new Nette\InvalidStateException("Database refetch failed; row with signature '{$this->getSignature()}' does not exist!");
			}

			$this->data = $this->table[$this->getSignature()]->data;
			$this->dataRefreshed = true;
		}

		$key ??= '';
		return isset($this->data[$key]) || array_key_exists($key, $this->data);
	}


	protected function removeAccessColumn(string $key): void
	{
		$this->table->removeAccessColumn($key);
	}
}
