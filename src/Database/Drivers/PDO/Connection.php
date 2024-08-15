<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO;

use Nette;
use Nette\Database\Drivers;
use PDO;
use PDOException;


abstract class Connection implements Drivers\Connection
{
	protected readonly PDO $pdo;
	protected readonly Drivers\Engine $engine;


	public function __construct(
		string $dsn,
		?string $username = null,
		#[\SensitiveParameter]
		?string $password = null,
		array $options = [],
	) {
		$this->engine = $this->getDatabaseEngine();
		try {
			$this->pdo = new PDO($dsn, $username, $password, $options);
			$this->initialize($options);
		} catch (PDOException $e) {
			throw new ($this->convertException($e, $args, Nette\Database\ConnectionException::class))(...$args);
		}
	}


	protected function initialize(array $options): void
	{
	}


	public function convertException(
		PDOException $e,
		?array &$args,
		?string $class = null,
		?string $sql = null,
		?array $params = null,
	): string
	{
		if ($e->errorInfo !== null) {
			[$sqlState, $code] = $e->errorInfo;
			$code ??= 0;
		} elseif (preg_match('#SQLSTATE\[(.*?)\] \[(.*?)\] (.*)#A', $e->getMessage(), $m)) {
			$sqlState = $m[1];
			$code = (int) $m[2];
		} else {
			$code = $e->getCode();
			$sqlState = null;
		}

		$args = [$e->getMessage(), $sqlState, $code, $sql ? new Nette\Database\SqlLiteral($sql, $params) : null, $e];
		return $this->engine->determineExceptionClass($code, $sqlState, $e->getMessage())
			?? $class
			?? Nette\Database\DriverException::class;
	}


	public function query(string $sql, array $params = []): Result
	{
		try {
			return new Result($this->execute($sql, $params), $this);
		} catch (PDOException $e) {
			throw new ($this->convertException($e, $args, null, $sql, $params))(...$args);
		}
	}


	protected function execute(string $sql, array $params): \PDOStatement
	{
		$types = ['boolean' => PDO::PARAM_BOOL, 'integer' => PDO::PARAM_INT, 'resource' => PDO::PARAM_LOB, 'NULL' => PDO::PARAM_NULL];

		$statement = $this->pdo->prepare($sql);
		foreach ($params as $key => $value) {
			$statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $types[gettype($value)] ?? PDO::PARAM_STR);
		}

		$statement->execute();
		return $statement;
	}


	public function beginTransaction(): void
	{
		try {
			$this->pdo->beginTransaction();
		} catch (PDOException $e) {
			throw new ($this->convertException($e, $args))(...$args);
		}
	}


	public function commit(): void
	{
		try {
			$this->pdo->commit();
		} catch (PDOException $e) {
			throw new ($this->convertException($e, $args))(...$args);
		}
	}


	public function rollBack(): void
	{
		try {
			$this->pdo->rollBack();
		} catch (PDOException $e) {
			throw new ($this->convertException($e, $args))(...$args);
		}
	}


	public function getInsertId(?string $sequence = null): int|string
	{
		try {
			$res = $this->pdo->lastInsertId($sequence);
			return $res === false ? 0 : $res;
		} catch (PDOException $e) {
			throw new ($this->convertException($e, $args))(...$args);
		}
	}


	public function quote(string $string): string
	{
		return $this->pdo->quote($string);
	}


	public function getServerVersion(): string
	{
		return $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
	}


	public function getNativeConnection(): PDO
	{
		return $this->pdo;
	}


	public function getMetaTypeKey(): string
	{
		return 'native_type';
	}
}
