<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\MSSQL;

use Nette\Database\Drivers;


/**
 * PDO MS SQL database driver.
 */
class Driver implements Drivers\Driver
{
	private const EngineClass = Drivers\Engines\MSSQLEngine::class;


	public function __construct(
		#[\SensitiveParameter]
		private readonly array $params,
	) {
	}


	public function connect()
	{
		return new \PDO(...$this->params);
	}


	public function createDatabaseEngine($connection): Drivers\Engine
	{
		return new (self::EngineClass)($connection);
	}
}
