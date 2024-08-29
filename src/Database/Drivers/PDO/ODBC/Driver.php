<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\ODBC;

use Nette\Database\Drivers;


/**
 * PDO ODBC database driver.
 */
class Driver implements Drivers\Driver
{
	private const EngineClass = Drivers\Engines\ODBCEngine::class;


	public function __construct(
		#[\SensitiveParameter]
		private readonly array $params,
	) {
	}


	public function connect(): Drivers\Connection
	{
		return new Drivers\PDO\Connection(...$this->params);
	}


	public function createDatabaseEngine(Drivers\Connection $connection): Drivers\Engine
	{
		return new (self::EngineClass)($connection);
	}
}
