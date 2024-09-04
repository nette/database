<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\MySQL;

use Nette\Database\Drivers;
use Nette\Database\Drivers\Engines\MySQLEngine;


/**
 * PDO MySQL database driver.
 */
class Driver extends Drivers\PDO\Driver
{
	private const DefaultCharset = 'utf8mb4';


	public function __construct(
		protected readonly string $dsn,
		protected readonly ?string $username = null,
		#[\SensitiveParameter]
		protected readonly ?string $password = null,
		protected readonly array $options = [],
		protected readonly ?string $charset = self::DefaultCharset,
		protected readonly ?string $sqlmode = null,
		protected readonly ?bool $convertBoolean = null,
	) {
	}


	public function connect(): Drivers\PDO\Connection
	{
		$connection = parent::connect();
		if ($this->charset) {
			$connection->query('SET NAMES ' . $connection->quote($this->charset));
		}
		if ($this->sqlmode) {
			$connection->query('SET sql_mode=' . $connection->quote($this->sqlmode));
		}
		return $connection;
	}


	public function createEngine(Drivers\Connection $connection): MySQLEngine
	{
		$engine = new MySQLEngine($connection);
		$engine->convertBoolean = $this->convertBoolean ?? $engine->convertBoolean;
		return $engine;
	}
}
