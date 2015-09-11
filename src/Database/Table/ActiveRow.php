<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

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
	private $dataRefreshed = FALSE;


	public function __construct(array $data, Selection $table)
	{
		$this->data = $data;
		$this->table = $table;
	}


	/**
	 * @internal
	 */
	public function setTable(Selection $table)
	{
		$this->table = $table;
	}


	/**
	 * @internal
	 */
	public function getTable()
	{
		return $this->table;
	}


	public function __toString()
	{
		try {
			return (string) $this->getPrimary();
		} catch (\Exception $e) {
			if (func_num_args()) {
				throw $e;
			}
			trigger_error("Exception in " . __METHOD__ . "(): {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}", E_USER_ERROR);
		}
	}


	/**
	 * @return array
	 */
	public function toArray()
	{
		$this->accessColumn(NULL);
		return $this->data;
	}


	/**
	 * Returns primary key value.
	 * @param  bool
	 * @return mixed possible int, string, array, object (Nette\Utils\DateTime)
	 */
	public function getPrimary($need = TRUE)
	{
		$primary = $this->table->getPrimary($need);
		if ($primary === NULL) {
			return NULL;

		} elseif (!is_array($primary)) {
			if (isset($this->data[$primary])) {
				return $this->data[$primary];
			} elseif ($need) {
				throw new Nette\InvalidStateException("Row does not contain primary $primary column data.");
			} else {
				return NULL;
			}

		} else {
			$primaryVal = array();
			foreach ($primary as $key) {
				if (!isset($this->data[$key])) {
					if ($need) {
						throw new Nette\InvalidStateException("Row does not contain primary $key column data.");
					} else {
						return NULL;
					}
				}
				$primaryVal[$key] = $this->data[$key];
			}
			return $primaryVal;
		}
	}


	/**
	 * Returns row signature (composition of primary keys)
	 * @param  bool
	 * @return string
	 */
	public function getSignature($need = TRUE)
	{
		return implode('|', (array) $this->getPrimary($need));
	}


	/**
	 * Returns referenced row.
	 * @param  string
	 * @param  string
	 * @return IRow or NULL if the row does not exist
	 */
	public function ref($key, $throughColumn = NULL)
	{
		$row = $this->table->getReferencedTable($this, $key, $throughColumn);
		if ($row === FALSE) {
			throw new Nette\MemberAccessException("No reference found for \${$this->table->name}->ref($key).");
		}

		return $row;
	}


	/**
	 * Returns referencing rows.
	 * @param  string
	 * @param  string
	 * @return GroupedSelection
	 */
	public function related($key, $throughColumn = NULL)
	{
		$groupedSelection = $this->table->getReferencingTable($key, $throughColumn, $this[$this->table->getPrimary()]);
		if (!$groupedSelection) {
			throw new Nette\MemberAccessException("No reference found for \${$this->table->name}->related($key).");
		}

		return $groupedSelection;
	}


	/**
	 * Updates row.
	 * @param  array|\Traversable (column => value)
	 * @return bool
	 */
	public function update($data)
	{
		if ($data instanceof \Traversable) {
			$data = iterator_to_array($data);
		}

		$primary = $this->getPrimary();
		if (!is_array($primary)) {
			$primary = array($this->table->getPrimary() => $primary);
		}

		$selection = $this->table->createSelectionInstance()
			->wherePrimary($primary);

		if ($selection->update($data)) {
			if ($tmp = array_intersect_key($data, $primary)) {
				$selection = $this->table->createSelectionInstance()
					->wherePrimary($tmp + $primary);
			}
			$selection->select('*');
			if (($row = $selection->fetch()) === FALSE) {
				throw new Nette\InvalidStateException('Database refetch failed; row does not exist!');
			}
			$this->data = $row->data;
			return TRUE;
		} else {
			return FALSE;
		}
	}


	/**
	 * Deletes row.
	 * @return int number of affected rows
	 */
	public function delete()
	{
		$res = $this->table->createSelectionInstance()
			->wherePrimary($this->getPrimary())
			->delete();

		if ($res > 0 && ($signature = $this->getSignature(FALSE))) {
			unset($this->table[$signature]);
		}

		return $res;
	}


	/********************* interface IteratorAggregate ****************d*g**/


	public function getIterator()
	{
		$this->accessColumn(NULL);
		return new \ArrayIterator($this->data);
	}


	/********************* interface ArrayAccess & magic accessors ****************d*g**/


	/**
	 * Stores value in column.
	 * @param  string column name
	 * @param  string value
	 * @return void
	 */
	public function offsetSet($key, $value)
	{
		$this->__set($key, $value);
	}


	/**
	 * Returns value of column.
	 * @param  string column name
	 * @return string
	 */
	public function offsetGet($key)
	{
		return $this->__get($key);
	}


	/**
	 * Tests if column exists.
	 * @param  string column name
	 * @return bool
	 */
	public function offsetExists($key)
	{
		return $this->__isset($key);
	}


	/**
	 * Removes column from data.
	 * @param  string column name
	 * @return void
	 */
	public function offsetUnset($key)
	{
		$this->__unset($key);
	}


	public function __set($key, $value)
	{
		throw new Nette\DeprecatedException('ActiveRow is read-only; use update() method instead.');
	}


	/**
	 * @param  string
	 * @return ActiveRow|mixed
	 * @throws Nette\MemberAccessException
	 */
	public function &__get($key)
	{
		$this->accessColumn($key);
		if (array_key_exists($key, $this->data)) {
			return $this->data[$key];
		}

		$referenced = $this->table->getReferencedTable($this, $key);
		if ($referenced !== FALSE) {
			$this->accessColumn($key, FALSE);
			return $referenced;
		}

		$this->removeAccessColumn($key);
		$hint = Nette\Utils\ObjectMixin::getSuggestion(array_keys($this->data), $key);
		throw new Nette\MemberAccessException("Cannot read an undeclared column '$key'" . ($hint ? ", did you mean '$hint'?" : '.'));
	}


	public function __isset($key)
	{
		$this->accessColumn($key);
		if (array_key_exists($key, $this->data)) {
			return isset($this->data[$key]);
		}
		$this->removeAccessColumn($key);
		return FALSE;
	}


	public function __unset($key)
	{
		throw new Nette\DeprecatedException('ActiveRow is read-only.');
	}


	/**
	 * @internal
	 */
	public function accessColumn($key, $selectColumn = TRUE)
	{
		$this->table->accessColumn($key, $selectColumn);
		if ($this->table->getDataRefreshed() && !$this->dataRefreshed) {
			$this->data = $this->table[$this->getSignature()]->data;
			$this->dataRefreshed = TRUE;
		}
		return array_key_exists($key, $this->data);
	}


	protected function removeAccessColumn($key)
	{
		$this->table->removeAccessColumn($key);
	}

}
