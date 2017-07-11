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
 */
class Connection
{
	use Nette\SmartObject;

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


	public function __construct($dsn, $user = null, $password = null, array $options = null)
	{
		if (func_num_args() > 4) { // compatibility
			trigger_error(__METHOD__ . " fifth argument is deprecated, use \$options['driverClass'].", E_USER_DEPRECATED);
			$options['driverClass'] = func_get_arg(4);
		}
		$this->params = [$dsn, $user, $password];
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
		$this->pdo = null;
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
	public function getInsertId($name = null)
	{
		try {
			$res = $this->getPdo()->lastInsertId($name);
			return $res === false ? '0' : $res;
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
	public function beginTransaction()
	{
		$this->query('::beginTransaction');
	}


	/** @return void */
	public function commit()
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
	 * @return ResultSet
	 */
	public function query($sql, ...$params)
	{
		list($sql, $params) = $this->preprocess($sql, ...$params);
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
		return $this->query($sql, ...$params);
	}


	/**
	 * @return [string, array]
	 */
	public function preprocess($sql, ...$params)
	{
		$this->connect();
		return $params
			? $this->preprocessor->process(func_get_args())
			: [$sql, []];
	}


	/********************* shortcuts ****************d*g**/


	/**
	 * Shortcut for query()->fetch()
	 * @param  string
	 * @return Row
	 */
	public function fetch($sql, ...$params)
	{
		return $this->query($sql, ...$params)->fetch();
	}


	/**
	 * Shortcut for query()->fetchField()
	 * @param  string
	 * @return mixed
	 */
	public function fetchField($sql, ...$params)
	{
		return $this->query($sql, ...$params)->fetchField();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 * @param  string
	 * @return array
	 */
	public function fetchPairs($sql, ...$params)
	{
		return $this->query($sql, ...$params)->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 * @param  string
	 * @return array
	 */
	public function fetchAll($sql, ...$params)
	{
		return $this->query($sql, ...$params)->fetchAll();
	}


	/**
	 * @return SqlLiteral
	 */
	public static function literal($value, ...$params)
	{
		return new SqlLiteral($value, $params);
	}
}
