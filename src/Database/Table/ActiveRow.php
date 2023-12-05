<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Table;

use Nette;


/**
 * Single row representation.
 * ActiveRow is based on the great library NotORM http://www.notorm.com written by Jakub Vrana.
 */
class ActiveRow implements \IteratorAggregate, IRow
{
	/** @var Selection */
	private $table;

	/** @var array of row data */
	private $data;

	/** @var bool */
	private $dataRefreshed = false;


	public function __construct(array $data, Selection $table)
	{
		$this->data = $data;
		$this->table = $table;
	}


	/**
	 * @internal
	 */
	public function setTable(Selection $table): void
	{
		$this->table = $table;
	}


	/**
	 * @internal
	 */
	public function getTable(): Selection
	{
		return $this->table;
	}


	public function __toString()
	{
		try {
			return (string) $this->getPrimary();
		} catch (\Throwable $e) {
			if (func_num_args() || PHP_VERSION_ID >= 70400) {
				throw $e;
			}

			trigger_error('Exception in ' . __METHOD__ . "(): {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}", E_USER_ERROR);
			return '';
		}
	}


	public function toArray(): array
	{
		$this->accessColumn(null);
		return $this->data;
	}


	/**
	 * Returns primary key value.
	 * @return mixed possible int, string, array, object (Nette\Utils\DateTime)
	 */
	public function getPrimary(bool $throw = true)
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
	 * Returns row signature (composition of primary keys)
	 */
	public function getSignature(bool $throw = true): string
	{
		return implode('|', (array) $this->getPrimary($throw));
	}


	/**
	 * Returns referenced row.
	 * @return self|null if the row does not exist
	 */
	public function ref(string $key, ?string $throughColumn = null): ?self
	{
		$row = $this->table->getReferencedTable($this, $key, $throughColumn);
		if ($row === false) {
			throw new Nette\MemberAccessException("No reference found for \${$this->table->getName()}->ref($key).");
		}

		return $row;
	}


	/**
	 * Returns referencing rows.
	 */
	public function related(string $key, ?string $throughColumn = null): GroupedSelection
	{
		$groupedSelection = $this->table->getReferencingTable($key, $throughColumn, $this[$this->table->getPrimary()]);
		if (!$groupedSelection) {
			throw new Nette\MemberAccessException("No reference found for \${$this->table->getName()}->related($key).");
		}

		return $groupedSelection;
	}


	/**
	 * Updates row.
	 */
	public function update(iterable $data): bool
	{
		if ($data instanceof \Traversable) {
			$data = iterator_to_array($data);
		}

		$primary = $this->getPrimary();
		if (!is_array($primary)) {
			$primary = [$this->table->getPrimary() => $primary];
		}

		$selection = $this->table->createSelectionInstance()
			->wherePrimary($primary);

		if ($selection->update($data)) {
			if ($tmp = array_intersect_key($data, $primary)) {
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
	 * Deletes row.
	 * @return int number of affected rows
	 */
	public function delete(): int
	{
		$res = $this->table->createSelectionInstance()
			->wherePrimary($this->getPrimary())
			->delete();

		if ($res > 0 && ($signature = $this->getSignature(false))) {
			unset($this->table[$signature]);
		}

		return $res;
	}


	/********************* interface IteratorAggregate ****************d*g**/


	public function getIterator(): \Iterator
	{
		$this->accessColumn(null);
		return new \ArrayIterator($this->data);
	}


	/********************* interface ArrayAccess & magic accessors ****************d*g**/


	/**
	 * Stores value in column.
	 * @param  string  $column
	 * @param  mixed  $value
	 */
	public function offsetSet($column, $value): void
	{
		$this->__set($column, $value);
	}


	/**
	 * Returns value of column.
	 * @param  string  $column
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($column)
	{
		return $this->__get($column);
	}


	/**
	 * Tests if column exists.
	 * @param  string  $column
	 */
	public function offsetExists($column): bool
	{
		return $this->__isset($column);
	}


	/**
	 * Removes column from data.
	 * @param  string  $column
	 */
	public function offsetUnset($column): void
	{
		$this->__unset($column);
	}


	public function __set(string $column, $value): void
	{
		throw new Nette\DeprecatedException('ActiveRow is read-only; use update() method instead.');
	}


	/**
	 * @return ActiveRow|mixed
	 * @throws Nette\MemberAccessException
	 */
	public function &__get(string $key)
	{
		if ($this->accessColumn($key)) {
			return $this->data[$key];
		}

		$referenced = $this->table->getReferencedTable($this, $key);
		if ($referenced !== false) {
			$this->accessColumn($key, false);
			return $referenced;
		}

		$this->removeAccessColumn($key);
		$hint = Nette\Utils\Helpers::getSuggestion(array_keys($this->data), $key);
		throw new Nette\MemberAccessException("Cannot read an undeclared column '$key'" . ($hint ? ", did you mean '$hint'?" : '.'));
	}


	public function __isset(string $key): bool
	{
		if ($this->accessColumn($key)) {
			return isset($this->data[$key]);
		}

		$referenced = $this->table->getReferencedTable($this, $key);
		if ($referenced !== false) {
			$this->accessColumn($key, false);
			return (bool) $referenced;
		}

		$this->removeAccessColumn($key);
		return false;
	}


	public function __unset(string $key): void
	{
		throw new Nette\DeprecatedException('ActiveRow is read-only.');
	}


	/**
	 * @internal
	 */
	public function accessColumn(?string $key, bool $selectColumn = true): bool
	{
		if ($this->table->accessColumn($key, $selectColumn) && !$this->dataRefreshed) {
			if (!isset($this->table[$this->getSignature()])) {
				throw new Nette\InvalidStateException("Database refetch failed; row with signature '{$this->getSignature()}' does not exist!");
			}

			$this->data = $this->table[$this->getSignature()]->data;
			$this->dataRefreshed = true;
		}

		return isset($this->data[$key]) || array_key_exists($key, $this->data);
	}


	protected function removeAccessColumn(string $key): void
	{
		$this->table->removeAccessColumn($key);
	}
}
