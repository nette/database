<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;
use PDO;
use PDOException;


/**
 * Represents a connection between PHP and a database server.
 *
 * @property-read  ISupplementalDriver  $supplementalDriver
 * @property-read  string               $dsn
 * @property-read  PDO                  $pdo
 */
class Connection extends Nette\Object
{
	/** @var callable[]  function (Connection $connection); Occurs after connection is established */
	public $onConnect;

	/** @var callable[]  function (Connection $connection, ResultSet|DriverException $result); Occurs after query is executed */
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
	 * @param  string
	 * @param  mixed   [parameters, ...]
	 * @return ResultSet
	 */
	public function query($sql)
	{
		$this->connect();

		$args = is_array($sql) ? $sql : func_get_args(); // accepts arrays only internally
		list($sql, $params) = count($args) > 1
			? $this->preprocessor->process($args)
			: array($args[0], array());

		try {
			$result = new ResultSet($this, $sql, $params);
		} catch (PDOException $e) {
			$this->onQuery($this, $e);
			throw $e;
		}
		$this->onQuery($this, $result);
		return $result;
	}


	/**
	 * @param  string
	 * @return ResultSet
	 */
	public function queryArgs($sql, array $params)
	{
		array_unshift($params, $sql);
		return $this->query($params);
	}


	/**
	 * @return [string, array]
	 */
	public function preprocess($sql)
	{
		$this->connect();
		return func_num_args() > 1
			? $this->preprocessor->process(func_get_args())
			: array($sql, array());
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
		return $this->query(func_get_args())->fetch();
	}


	/**
	 * Shortcut for query()->fetchField()
	 * @param  string
	 * @param  mixed   [parameters, ...]
	 * @return mixed
	 */
	public function fetchField($args)
	{
		return $this->query(func_get_args())->fetchField();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 * @param  string
	 * @param  mixed   [parameters, ...]
	 * @return array
	 */
	public function fetchPairs($args)
	{
		return $this->query(func_get_args())->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 * @param  string
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
