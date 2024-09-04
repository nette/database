<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\OCI;

use Nette\Database\Drivers;
use Nette\Database\Drivers\Engines\OracleEngine;


/**
 * PDO Oracle database driver.
 */
class Driver extends Drivers\PDO\Driver
{
	public function __construct(
		protected readonly string $dsn,
		protected readonly ?string $username = null,
		#[\SensitiveParameter]
		protected readonly ?string $password = null,
		protected readonly array $options = [],
		protected readonly ?string $formatDateTime = null,
	) {
	}


	public function createEngine(Drivers\Connection $connection): OracleEngine
	{
		$engine = new OracleEngine($connection);
		if ($this->formatDateTime) {
			$engine->formatDateTime = $this->formatDateTime;
		}
		return $engine;
	}
}
