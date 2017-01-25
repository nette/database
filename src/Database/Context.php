<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

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
	 */
	public function getInsertId(string $name = NULL): string
	{
		return $this->connection->getInsertId($name);
	}


	/**
	 * Generates and executes SQL query.
	 */
	public function query(string $sql, ...$params): ResultSet
	{
		return $this->connection->query($sql, ...$params);
	}


	public function queryArgs(string $sql, array $params): ResultSet
	{
		return $this->connection->query($sql, ...$params);
	}


	public function table(string $table): Table\Selection
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
	 */
	public function fetch(string $sql, ...$params): ?Row
	{
		return $this->connection->query($sql, ...$params)->fetch();
	}


	/**
	 * Shortcut for query()->fetchField()
	 * @return mixed
	 */
	public function fetchField(string $sql, ...$params)
	{
		return $this->connection->query($sql, ...$params)->fetchField();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 */
	public function fetchPairs(string $sql, ...$params): array
	{
		return $this->connection->query($sql, ...$params)->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 */
	public function fetchAll(string $sql, ...$params): array
	{
		return $this->connection->query($sql, ...$params)->fetchAll();
	}


	public static function literal($value, ...$params): SqlLiteral
	{
		return new SqlLiteral($value, $params);
	}

}
