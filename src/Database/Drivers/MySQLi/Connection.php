<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\MySQLi;

use mysqli;
use mysqli_sql_exception;
use Nette;
use Nette\Database\Drivers;


/**
 * MySQLi driver.
 *
 * Options:
 *   - host => the MySQL server host name
 *   - port (int) => the port number to attempt to connect to the MySQL server
 *   - socket => the socket or named pipe
 *   - username (or user)
 *   - password (or pass)
 *   - database => the database name to select
 *   - options (array) => array of driver specific constants (MYSQLI_*) and values {@see mysqli_options}
 *   - flags (int) => driver specific constants (MYSQLI_CLIENT_*) {@see mysqli_real_connect}
 *   - charset => character encoding to set (default is utf8)
 *   - persistent (bool) => try to find a persistent link?
 *   - sqlmode => see http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
 */
class Connection implements Drivers\Connection
{
	private Drivers\Engines\MySQLEngine $engine;
	private mysqli $db;


	public function __construct(
		string $host = 'localhost',
		string $username = '',
		string $password = '',
		?string $database = null,
		?int $port = 3306,
		?string $socket = null,
		bool $persistent = false,
		int $flags = 0,
		?string $charset = 'utf8mb4',
		?string $sqlmode = null,
	) {
		if (!extension_loaded('mysqli')) {
			throw new \LogicException("PHP extension 'mysqli' is not loaded.");
		}

		$this->engine = $this->getDatabaseEngine();
		$this->db = new mysqli;
		try {
			$this->db->real_connect(
				($persistent ? 'p:' : '') . $host,
				$username,
				$password,
				$database,
				$port,
				$socket,
				$flags,
			);
		} catch (mysqli_sql_exception $e) {
			throw new ($this->convertException($e, $args, Nette\Database\ConnectionException::class))(...$args);
		}

		if ($charset) {
			$this->db->query('SET NAMES ' . $this->db->real_escape_string($charset));
		}
		if ($sqlmode) {
			$this->db->query('SET sql_mode=' . $this->db->real_escape_string($sqlmode));
		}
	}


	public function getDatabaseEngine(): Drivers\Engines\MySQLEngine
	{
		return new Drivers\Engines\MySQLEngine($this);
	}


	public function convertException(
		mysqli_sql_exception $e,
		?array &$args,
		?string $class = null,
		?string $sql = null,
		?array $params = null,
	): string
	{
		$args = [$e->getMessage(), $e->getSqlState(), $e->getCode(), $sql ? new Nette\Database\SqlLiteral($sql, $params) : null, $e];
		return $this->engine->determineExceptionClass($e->getCode(), $e->getSqlState(), $e->getMessage())
			?? $class
			?? Nette\Database\DriverException::class;
	}


	public function query(string $sql, array $params = []): Result
	{
		try {
			$res = $this->db->query($sql);
		} catch (mysqli_sql_exception $e) {
			throw new ($this->convertException($e, $args, $sql, $params))(...$args);
		}

		if ($res instanceof \mysqli_result) {
			return new Result($res, $this);
		}

		throw new \Exception; // TODO
	}


	public function beginTransaction(): void
	{
		try {
			$this->db->begin_transaction();
		} catch (mysqli_sql_exception $e) {
			throw new ($this->convertException($e, $args))(...$args);
		}
	}


	public function commit(): void
	{
		try {
			$this->db->commit();
		} catch (mysqli_sql_exception $e) {
			throw new ($this->convertException($e, $args))(...$args);
		}
	}


	public function rollBack(): void
	{
		try {
			$this->db->rollback();
		} catch (mysqli_sql_exception $e) {
			throw new ($this->convertException($e, $args))(...$args);
		}
	}


	public function getInsertId(?string $sequence = null): int|string
	{
		return $this->db->insert_id;
	}


	public function quote(string $string): string
	{
		return "'" . $this->db->real_escape_string($string) . "'";
	}


	public function getServerVersion(): string
	{
		return $this->db->get_server_info();
	}


	public function getNativeConnection(): mysqli
	{
		return $this->db;
	}
}
