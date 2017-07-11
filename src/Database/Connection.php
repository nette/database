<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

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
		$this->params = [$dsn, $user, $password];
		$this->options = (array) $options;

		if (empty($options['lazy'])) {
			$this->connect();
		}
	}


	public function connect(): void
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
		$this->driver = new $class;
		$this->driver->initialize($this, $this->options);
		$this->preprocessor = new SqlPreprocessor($this);
		$this->onConnect($this);
	}


	public function reconnect(): void
	{
		$this->disconnect();
		$this->connect();
	}


	public function disconnect(): void
	{
		$this->pdo = null;
	}


	public function getDsn(): string
	{
		return $this->params[0];
	}


	public function getPdo(): PDO
	{
		$this->connect();
		return $this->pdo;
	}


	public function getSupplementalDriver(): ISupplementalDriver
	{
		$this->connect();
		return $this->driver;
	}


	/**
	 * @param  string  sequence object
	 */
	public function getInsertId(string $name = null): string
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
	 */
	public function quote(string $string, int $type = PDO::PARAM_STR): string
	{
		try {
			return $this->getPdo()->quote($string, $type);
		} catch (PDOException $e) {
			throw DriverException::from($e);
		}
	}


	function beginTransaction(): void
	{
		$this->query('::beginTransaction');
	}


	function commit(): void
	{
		$this->query('::commit');
	}


	public function rollBack(): void
	{
		$this->query('::rollBack');
	}


	/**
	 * Generates and executes SQL query.
	 */
	public function query(string $sql, ...$params): ResultSet
	{
		[$sql, $params] = $this->preprocess($sql, ...$params);
		try {
			$result = new ResultSet($this, $sql, $params);
		} catch (PDOException $e) {
			$this->onQuery($this, $e);
			throw $e;
		}
		$this->onQuery($this, $result);
		return $result;
	}


	public function queryArgs(string $sql, array $params): ResultSet
	{
		return $this->query($sql, ...$params);
	}


	/**
	 * @return [string, array]
	 */
	public function preprocess($sql, ...$params): array
	{
		$this->connect();
		return $params
			? $this->preprocessor->process(func_get_args())
			: [$sql, []];
	}


	/********************* shortcuts ****************d*g**/


	/**
	 * Shortcut for query()->fetch()
	 */
	public function fetch(string $sql, ...$params): ?Row
	{
		return $this->query($sql, ...$params)->fetch();
	}


	/**
	 * Shortcut for query()->fetchField()
	 * @return mixed
	 */
	public function fetchField(string $sql, ...$params)
	{
		return $this->query($sql, ...$params)->fetchField();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 */
	public function fetchPairs(string $sql, ...$params): array
	{
		return $this->query($sql, ...$params)->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 */
	public function fetchAll(string $sql, ...$params): array
	{
		return $this->query($sql, ...$params)->fetchAll();
	}


	public static function literal($value, ...$params): SqlLiteral
	{
		return new SqlLiteral($value, $params);
	}
}
