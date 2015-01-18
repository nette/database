<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;


/**
 * Database context.
 *
 * @author     David Grudl
 */
class Context extends Nette\Object
{
	/** @var Connection */
	private $connection;

	/** @var IReflection */
	private $reflection;

	/** @var Nette\Caching\IStorage */
	private $cacheStorage;


	public function __construct(Connection $connection, IReflection $reflection = NULL, Nette\Caching\IStorage $cacheStorage = NULL)
	{
		$this->connection = $connection;
		$this->reflection = $reflection ?: new Reflection\ConventionalReflection;
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
	public function getInsertId($name = NULL)
	{
		return $this->connection->getInsertId($name);
	}


	/**
	 * Generates and executes SQL query.
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return ResultSet
	 */
	public function query($statement)
	{
		return $this->connection->query(func_get_args());
	}


	/**
	 * @param  string  statement
	 * @param  array
	 * @return ResultSet
	 */
	public function queryArgs($statement, array $params)
	{
		return $this->connection->queryArgs($statement, $params);
	}


	/**
	 * @param  string
	 * @return Nette\Database\Table\Selection
	 */
	public function table($table)
	{
		return new Table\Selection($this->connection, $table, $this->reflection, $this->cacheStorage);
	}


	/** @return Connection */
	public function getConnection()
	{
		return $this->connection;
	}


	/** @return IReflection */
	public function getDatabaseReflection()
	{
		return $this->reflection;
	}


	/********************* shortcuts ****************d*g**/


	/**
	 * Shortcut for query()->fetch()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return Row
	 */
	public function fetch($args)
	{
		return $this->connection->query(func_get_args())->fetch();
	}


	/**
	 * Shortcut for query()->fetchField()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return mixed
	 */
	public function fetchField($args)
	{
		return $this->connection->query(func_get_args())->fetchField();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return array
	 */
	public function fetchPairs($args)
	{
		return $this->connection->query(func_get_args())->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return array
	 */
	public function fetchAll($args)
	{
		return $this->connection->query(func_get_args())->fetchAll();
	}


	/**
	 * @return SqlLiteral
	 */
	public static function literal($value)
	{
		$args = func_get_args();
		return new SqlLiteral(array_shift($args), $args);
	}

}
