<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Database;

use Nette,
	PDO,
	PDOException;


/**
 * Represents a connection between PHP and a database server.
 *
 * @author     David Grudl
 *
 * @property-read  ISupplementalDriver  $supplementalDriver
 * @property-read  string               $dsn
 * @property-read  PDO                  $pdo
 */
class Connection extends Nette\Object
{
	/** @var array of function(Connection $connection); Occurs after connection is established */
	public $onConnect;

	/** @var array of function(Connection $connection, ResultSet|DriverException $result); Occurs after query is executed */
	public $onQuery;

	/** @var array */
	private $params;

	/** @var array */
	private $options;

	/** @var ISupplementalDriver */
	private $driver;

	/** @var SqlPreprocessor */
	private $preprocessor;

	/** @var PDO */
	private $pdo;


	public function __construct($dsn, $user = NULL, $password = NULL, array $options = NULL)
	{
		if (func_num_args() > 4) { // compatibility
			$options['driverClass'] = func_get_arg(4);
		}
		$this->params = array($dsn, $user, $password);
		$this->options = (array) $options;

		if (empty($options['lazy'])) {
			$this->connect();
		}
	}


	/** @return void */
	public function connect()
	{
		if ($this->pdo) {
			return;
		}

		try {
			$this->pdo = new PDO($this->params[0], $this->params[1], $this->params[2], $this->options);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			throw ConnectionException::from($e);
		}

		$class = empty($this->options['driverClass'])
			? 'Nette\Database\Drivers\\' . ucfirst(str_replace('sql', 'Sql', $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME))) . 'Driver'
			: $this->options['driverClass'];
		$this->driver = new $class($this, $this->options);
		$this->preprocessor = new SqlPreprocessor($this);
		$this->onConnect($this);
	}


	/** @return void */
	public function reconnect()
	{
		$this->disconnect();
		$this->connect();
	}


	/** @return void */
	public function disconnect()
	{
		$this->pdo = NULL;
	}


	/** @return string */
	public function getDsn()
	{
		return $this->params[0];
	}


	/** @return PDO */
	public function getPdo()
	{
		$this->connect();
		return $this->pdo;
	}


	/** @return ISupplementalDriver */
	public function getSupplementalDriver()
	{
		$this->connect();
		return $this->driver;
	}


	/**
	 * @param  string  sequence object
	 * @return string
	 */
	public function getInsertId($name = NULL)
	{
		try {
			return $this->getPdo()->lastInsertId($name);
		} catch (PDOException $e) {
			throw $this->driver->convertException($e);
		}
	}


	/**
	 * @param  string  string to be quoted
	 * @param  int     data type hint
	 * @return string
	 */
	public function quote($string, $type = PDO::PARAM_STR)
	{
		try {
			return $this->getPdo()->quote($string, $type);
		} catch (PDOException $e) {
			throw DriverException::from($e);
		}
	}


	/** @return void */
	function beginTransaction()
	{
		$this->queryArgs('::beginTransaction', array());
	}


	/** @return void */
	function commit()
	{
		$this->queryArgs('::commit', array());
	}


	/** @return void */
	public function rollBack()
	{
		$this->queryArgs('::rollBack', array());
	}


	/**
	 * Generates and executes SQL query.
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return ResultSet
	 */
	public function query($statement)
	{
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args);
	}


	/**
	 * @param  string  statement
	 * @param  array
	 * @return ResultSet
	 */
	public function queryArgs($statement, array $params)
	{
		$this->connect();
		if ($params) {
			array_unshift($params, $statement);
			list($statement, $params) = $this->preprocessor->process($params);
		}

		try {
			$result = new ResultSet($this, $statement, $params);
		} catch (PDOException $e) {
			$this->onQuery($this, $e);
			throw $e;
		}
		$this->onQuery($this, $result);
		return $result;
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
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args)->fetch();
	}


	/**
	 * Shortcut for query()->fetchField()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return mixed
	 */
	public function fetchField($args)
	{
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args)->fetchField();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return array
	 */
	public function fetchPairs($args)
	{
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args)->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return array
	 */
	public function fetchAll($args)
	{
		$args = func_get_args();
		return $this->queryArgs(array_shift($args), $args)->fetchAll();
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
