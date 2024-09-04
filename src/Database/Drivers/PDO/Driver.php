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
use PDOException;


/**
 * Base PDO database driver.
 */
abstract class Driver implements Drivers\Driver
{
	public function __construct(
		protected readonly string $dsn,
		protected readonly ?string $username = null,
		#[\SensitiveParameter]
		protected readonly ?string $password = null,
		protected readonly array $options = [],
	) {
	}


	public function connect(): Connection
	{
		try {
			$pdo = new \PDO($this->dsn, $this->username, $this->password, $this->options);
		} catch (PDOException $e) {
			throw new DriverException(...self::exceptionArgs($e));
		}
		return new Connection($pdo);
	}


	public static function exceptionArgs(PDOException $e, ?SqlLiteral $query = null): array
	{
		return [$e->getMessage(), $e->errorInfo[0] ?? null, $e->errorInfo[1] ?? 0, $query, $e];
	}
}
