<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO;

use Nette\Database\Drivers;


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
		return new Connection(new \PDO($this->dsn, $this->username, $this->password, $this->options));
	}
}
