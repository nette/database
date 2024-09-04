<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\PgSQL;

use Nette\Database\Drivers;
use Nette\Database\Drivers\Engines\PostgreSQLEngine;


/**
 * PDO PostgreSQL database driver.
 */
class Driver extends Drivers\PDO\Driver
{
	public function createEngine(Drivers\Connection $connection): PostgreSQLEngine
	{
		return new PostgreSQLEngine($connection);
	}
}
