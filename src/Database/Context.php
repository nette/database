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
class Context extends Nette\Object
{
	/** @var Connection */
	private $connection;

	/** @var IStructure */
	private $structure;

	/** @var IConventions */
	private $conventions;

	/** @var Nette\Caching\IStorage */
	private $cacheStorage;


	public function __construct(Connection $connection, IStructure $structure, IConventions $conventions = NULL, Nette\Caching\IStorage $cacheStorage = NULL)
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
	public function getInsertId($name = NULL)
	{
		return $this->connection->getInsertId($name);
	}


	/**
	 * Generates and executes SQL query.
	 * @param  string
	 * @param  mixed   [parameters, ...]
	 * @return ResultSet
	 */
	public function query($sql)
	{
		return $this->connection->query(func_get_args());
	}


	/**
	 * @param  string
	 * @return ResultSet
	 */
	public function queryArgs($sql, array $params)
	{
		return $this->connection->queryArgs($sql, $params);
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


	/** @deprecated */
	public function getDatabaseReflection()
	{
		trigger_error(__METHOD__ . '() is deprecated; use getConventions() instead.', E_USER_DEPRECATED);
		return $this->conventions;
	}


	/********************* shortcuts ****************d*g**/


	/**
	 * Shortcut for query()->fetch()
	 * @param  string
	 * @param  mixed   [parameters, ...]
	 * @return Row
	 */
	public function fetch($args)
	{
		return $this->connection->query(func_get_args())->fetch();
	}


	/**
	 * Shortcut for query()->fetchField()
	 * @param  string
	 * @param  mixed   [parameters, ...]
	 * @return mixed
	 */
	public function fetchField($args)
	{
		return $this->connection->query(func_get_args())->fetchField();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 * @param  string
	 * @param  mixed   [parameters, ...]
	 * @return array
	 */
	public function fetchPairs($args)
	{
		return $this->connection->query(func_get_args())->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 * @param  string
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
