<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO;

use Nette\Database\Drivers;
use PDO;


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
		$this->pdo = new PDO($dsn, $username, $password, $options);
		$this->initialize($options);
	}


	protected function initialize(array $options): void
	{
	}


	public function query(string $sql, array $params = []): Result
	{
		$types = ['boolean' => PDO::PARAM_BOOL, 'integer' => PDO::PARAM_INT, 'resource' => PDO::PARAM_LOB, 'NULL' => PDO::PARAM_NULL];

		$statement = $this->pdo->prepare($sql);
		foreach ($params as $key => $value) {
			$statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $types[gettype($value)] ?? PDO::PARAM_STR);
		}

		$statement->execute();
		return new Result($statement, $this);
	}


	public function beginTransaction(): void
	{
		$this->pdo->beginTransaction();

	}


	public function commit(): void
	{
		$this->pdo->commit();

	}


	public function rollBack(): void
	{
		$this->pdo->rollBack();

	}


	public function getInsertId(?string $sequence = null): string
	{
		$res = $this->pdo->lastInsertId($sequence);
		return $res === false ? '0' : $res;

	}


	public function quote(string $string): string
	{
		return $this->pdo->quote($string);
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
