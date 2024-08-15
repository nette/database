<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO;

use Nette\Database\Drivers;
use PDO;


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

		$statement = $this->pdo->prepare($sql);
		foreach ($params as $key => $value) {
			$statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $types[gettype($value)] ?? PDO::PARAM_STR);
		}

		$statement->execute();
		return new ($this->resultClass)($statement, $this);
	}


	public function execute(string $sql): int
	{
		return $this->pdo->exec($sql);
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


	public function getServerVersion(): string
	{
		return $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
	}


	public function getNativeConnection(): PDO
	{
		return $this->pdo;
	}
}
