<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;
use PDO;


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
	/** @var array of function (Connection $connection); Occurs after connection is established */
	public $onConnect;

	/** @var array of function (Connection $connection, ResultSet|Exception $result); Occurs after query is executed */
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


	public function connect()
	{
		if ($this->pdo) {
			return;
		}
		$this->pdo = new PDO($this->params[0], $this->params[1], $this->params[2], $this->options);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$class = empty($this->options['driverClass'])
			? 'Nette\Database\Drivers\\' . ucfirst(str_replace('sql', 'Sql', $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME))) . 'Driver'
			: $this->options['driverClass'];
		$this->driver = new $class($this, $this->options);
		$this->preprocessor = new SqlPreprocessor($this);
		$this->onConnect($this);
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
		return $this->getPdo()->lastInsertId($name);
	}


	/**
	 * @param  string  string to be quoted
	 * @param  int     data type hint
	 * @return string
	 */
	public function quote($string, $type = PDO::PARAM_STR)
	{
		return $this->getPdo()->quote($string, $type);
	}


	/** @return void */
	function beginTransaction()
	{
		$this->query('::beginTransaction');
	}


	/** @return void */
	function commit()
	{
		$this->query('::commit');
	}


	/** @return void */
	public function rollBack()
	{
		$this->query('::rollBack');
	}


	/**
	 * Generates and executes SQL query.
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return ResultSet
	 */
	public function query($statement)
	{
		$this->connect();

		$args = is_array($statement) ? $statement : func_get_args(); // accepts arrays only internally
		list($statement, $params) = count($args) > 1
			? $this->preprocessor->process($args)
			: array($args[0], array());

		try {
			$result = new ResultSet($this, $statement, $params);
		} catch (\PDOException $e) {
			$e->queryString = $statement;
			$this->onQuery($this, $e);
			throw $e;
		}
		$this->onQuery($this, $result);
		return $result;
	}


	/**
	 * @param  string  statement
	 * @param  array
	 * @return ResultSet
	 */
	public function queryArgs($statement, array $params)
	{
		array_unshift($params, $statement);
		return $this->query($params);
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
		return $this->query(func_get_args())->fetch();
	}


	/**
	 * Shortcut for query()->fetchField()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return mixed
	 */
	public function fetchField($args)
	{
		return $this->query(func_get_args())->fetchField();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return array
	 */
	public function fetchPairs($args)
	{
		return $this->query(func_get_args())->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 * @param  string  statement
	 * @param  mixed   [parameters, ...]
	 * @return array
	 */
	public function fetchAll($args)
	{
		return $this->query(func_get_args())->fetchAll();
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
