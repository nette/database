<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\SQLite;

use Nette\Database\Drivers;
use Nette\Database\Drivers\Engines\SQLiteEngine;


/**
 * PDO SQLite database driver.
 */
class Driver extends Drivers\PDO\Driver
{
	public function createEngine(): SQLiteEngine
	{
		return new SQLiteEngine;
	}
}
