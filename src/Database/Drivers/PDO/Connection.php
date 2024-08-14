<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO;

use Nette;
use Nette\Database\Drivers;
use Nette\Database\SqlLiteral;
use PDO;
use PDOException;


class Connection implements Drivers\Connection
{
	public readonly PDO $pdo;
	public string $resultClass = Result::class;
	public string $metaTypeKey = 'native_type';


	public function __construct(
		private string $engineClass,
		string $dsn,
		?string $username = null,
		#[\SensitiveParameter]
		?string $password = null,
		array $options = [],
	) {
		try {
			$this->pdo = new PDO($dsn, $username, $password, $options);
		} catch (PDOException $e) {
			throw new ($this->convertException($e, $args, Nette\Database\ConnectionException::class))(...$args);
		}
	}


	public function convertException(
		PDOException $e,
		?array &$args,
		?string $class = null,
		?SqlLiteral $query = null,
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

		$args = [$e->getMessage(), $sqlState, $code, $query, $e];
		return $this->engineClass::determineExceptionClass($code, $sqlState, $e->getMessage())
			?? $class
			?? Nette\Database\DriverException::class;
	}


	public function query(string $sql, array $params = []): Result
	{
		$types = ['boolean' => PDO::PARAM_BOOL, 'integer' => PDO::PARAM_INT, 'resource' => PDO::PARAM_LOB, 'NULL' => PDO::PARAM_NULL];

		try {
			$statement = $this->pdo->prepare($sql);
			foreach ($params as $key => $value) {
				$statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $types[gettype($value)] ?? PDO::PARAM_STR);
			}
			$statement->execute();
			return new ($this->resultClass)($statement, $this);

		} catch (PDOException $e) {
			throw new ($this->convertException($e, $args, null, new SqlLiteral($sql, $params)))(...$args);
		}
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
			$id = $this->pdo->lastInsertId($sequence);
			if ($id === '0' || $id === '' || $id === false) {
				throw new Nette\Database\DriverException('Cannot retrieve last generated ID.');
			}
			$int = (int) $id;
			return $id === (string) $int ? $int : $id;

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
}
