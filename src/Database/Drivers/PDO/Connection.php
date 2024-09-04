<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Drivers\PDO;

use Nette\Database\{Drivers, Helpers};
use PDO;
use function gettype, is_float, is_int;


class Connection implements Drivers\Connection
{
	public string $metaTypeKey = 'native_type';


	public function __construct(
		protected readonly PDO $pdo,
	) {
	}


	public function query(string $sql, array $params = [])
	{
		$types = ['boolean' => PDO::PARAM_BOOL, 'integer' => PDO::PARAM_INT, 'resource' => PDO::PARAM_LOB, 'NULL' => PDO::PARAM_NULL];

		$statement = $this->pdo->prepare($sql);
		foreach ($params as $key => $value) {
			if (is_float($value)) { // PDO has no float type and would stringify the value with the precision ini
				$value = Helpers::formatFloat($value);
			}

			$statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $types[gettype($value)] ?? PDO::PARAM_STR);
		}

		$statement->setFetchMode(PDO::FETCH_ASSOC);
		$statement->execute();
		return $statement;
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
