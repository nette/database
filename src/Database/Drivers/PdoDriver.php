<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette;
use Nette\Database\DriverException;
use PDO;
use PDOException;


/**
 * PDO-based driver.
 */
abstract class PdoDriver implements Nette\Database\Driver
{
	protected ?PDO $pdo = null;


	public function connect(
		string $dsn,
		?string $user = null,
		#[\SensitiveParameter]
		?string $password = null,
		?array $options = null,
	): void
	{
		try {
			$this->pdo = new PDO($dsn, $user, $password, $options);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			throw Nette\Database\ConnectionException::from($e);
		}
	}


	public function getPdo(): ?PDO
	{
		return $this->pdo;
	}


	public function query(string $queryString, array $params)
	{
		try {
			$types = ['boolean' => PDO::PARAM_BOOL, 'integer' => PDO::PARAM_INT, 'resource' => PDO::PARAM_LOB, 'NULL' => PDO::PARAM_NULL];

			$statement = $this->pdo->prepare($queryString);
			foreach ($params as $key => $value) {
				$type = gettype($value);
				$statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $types[$type] ?? PDO::PARAM_STR);
			}

			$statement->setFetchMode(PDO::FETCH_ASSOC);
			@$statement->execute(); // @ PHP generates warning when ATTR_ERRMODE = ERRMODE_EXCEPTION bug #73878
			return $statement;

		} catch (PDOException $e) {
			$e = $this->convertException($e);
			$e->queryString = $queryString;
			$e->params = $params;
			throw $e;
		}
	}


	public function beginTransaction(): void
	{
		try {
			$this->pdo->beginTransaction();
		} catch (PDOException $e) {
			throw $this->convertException($e);
		}
	}


	public function commit(): void
	{
		try {
			$this->pdo->commit();
		} catch (PDOException $e) {
			throw $this->convertException($e);
		}
	}


	public function rollBack(): void
	{
		try {
			$this->pdo->rollBack();
		} catch (PDOException $e) {
			throw $this->convertException($e);
		}
	}


	public function getInsertId(?string $sequence = null): string
	{
		try {
			$res = $this->pdo->lastInsertId($sequence);
			return $res === false ? '0' : $res;
		} catch (PDOException $e) {
			throw $this->convertException($e);
		}
	}


	public function quote(string $string, int $type = PDO::PARAM_STR): string
	{
		try {
			return $this->pdo->quote($string, $type);
		} catch (PDOException $e) {
			throw DriverException::from($e);
		}
	}
}
