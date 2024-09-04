<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\ODBC;

use Nette\Database\Drivers;
use Nette\Database\Drivers\Engines\ODBCEngine;


/**
 * PDO ODBC database driver.
 */
class Driver extends Drivers\PDO\Driver
{
	public function createEngine(Drivers\Connection $connection): ODBCEngine
	{
		return new ODBCEngine;
	}
}
