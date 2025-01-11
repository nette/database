<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO;

use Nette\Database\DriverException;
use Nette\Database\Drivers;
use Nette\Database\SqlLiteral;
use PDO;
use PDOException;


class Connection implements Drivers\Connection
{
	public string $resultClass = Result::class;
	public string $metaTypeKey = 'native_type';


	public function __construct(
		protected readonly PDO $pdo,
	) {
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
			throw new DriverException(...Driver::exceptionArgs($e, new SqlLiteral($sql, $params)));
		}
	}


	public function execute(string $sql): int
	{
		try {
			return $this->pdo->exec($sql);
		} catch (PDOException $e) {
			throw new DriverException(...Driver::exceptionArgs($e, new SqlLiteral($sql)));
		}
	}


	public function beginTransaction(): void
	{
		try {
			$this->pdo->beginTransaction();
		} catch (PDOException $e) {
			throw new DriverException(...Driver::exceptionArgs($e));
		}
	}


	public function commit(): void
	{
		try {
			$this->pdo->commit();
		} catch (PDOException $e) {
			throw new DriverException(...Driver::exceptionArgs($e));
		}
	}


	public function rollBack(): void
	{
		try {
			$this->pdo->rollBack();
		} catch (PDOException $e) {
			throw new DriverException(...Driver::exceptionArgs($e));
		}
	}


	public function getInsertId(?string $sequence = null): string
	{
		try {
			$res = $this->pdo->lastInsertId($sequence);
			return $res === false ? '0' : $res;
		} catch (PDOException $e) {
			throw new DriverException(...Driver::exceptionArgs($e));
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
