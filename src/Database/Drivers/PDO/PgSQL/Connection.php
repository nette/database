<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\PgSQL;

use Nette\Database\Drivers;


/**
 * PDO PostgreSQL database driver connection.
 */
class Connection extends Drivers\PDO\Connection
{
	public function getDatabaseEngine(): Drivers\Engines\PostgreSQLEngine
	{
		return new Drivers\Engines\PostgreSQLEngine($this);
	}


	public function query(string $sql, array $params = []): Result
	{
		return new Result($this->execute($sql, $params), $this);
	}
}
