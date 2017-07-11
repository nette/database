<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;
use Nette\Database\Conventions\StaticConventions;


/**
 * Database context.
 */
class Context
{
	use Nette\SmartObject;

	/** @var Connection */
	private $connection;

	/** @var IStructure */
	private $structure;

	/** @var IConventions */
	private $conventions;

	/** @var Nette\Caching\IStorage */
	private $cacheStorage;


	public function __construct(Connection $connection, IStructure $structure, IConventions $conventions = null, Nette\Caching\IStorage $cacheStorage = null)
	{
		$this->connection = $connection;
		$this->structure = $structure;
		$this->conventions = $conventions ?: new StaticConventions;
		$this->cacheStorage = $cacheStorage;
	}


	/** @return void */
	public function beginTransaction()
	{
		$this->connection->beginTransaction();
	}


	/** @return void */
	public function commit()
	{
		$this->connection->commit();
	}


	/** @return void */
	public function rollBack()
	{
		$this->connection->rollBack();
	}


	/**
	 * @param  string  sequence object
	 * @return string
	 */
	public function getInsertId($name = null)
	{
		return $this->connection->getInsertId($name);
	}


	/**
	 * Generates and executes SQL query.
	 * @param  string
	 * @return ResultSet
	 */
	public function query($sql, ...$params)
	{
		return $this->connection->query($sql, ...$params);
	}


	/**
	 * @param  string
	 * @return ResultSet
	 */
	public function queryArgs($sql, array $params)
	{
		return $this->connection->query($sql, ...$params);
	}


	/**
	 * @param  string
	 * @return Table\Selection
	 */
	public function table($table)
	{
		return new Table\Selection($this, $this->conventions, $table, $this->cacheStorage);
	}


	/** @return Connection */
	public function getConnection()
	{
		return $this->connection;
	}


	/** @return IStructure */
	public function getStructure()
	{
		return $this->structure;
	}


	/** @return IConventions */
	public function getConventions()
	{
		return $this->conventions;
	}


	/********************* shortcuts ****************d*g**/


	/**
	 * Shortcut for query()->fetch()
	 * @param  string
	 * @return Row
	 */
	public function fetch($sql, ...$params)
	{
		return $this->connection->query($sql, ...$params)->fetch();
	}


	/**
	 * Shortcut for query()->fetchField()
	 * @param  string
	 * @return mixed
	 */
	public function fetchField($sql, ...$params)
	{
		return $this->connection->query($sql, ...$params)->fetchField();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 * @param  string
	 * @return array
	 */
	public function fetchPairs($sql, ...$params)
	{
		return $this->connection->query($sql, ...$params)->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 * @param  string
	 * @return array
	 */
	public function fetchAll($sql, ...$params)
	{
		return $this->connection->query($sql, ...$params)->fetchAll();
	}


	/**
	 * @return SqlLiteral
	 */
	public static function literal($value, ...$params)
	{
		return new SqlLiteral($value, $params);
	}
}
