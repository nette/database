<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\OCI;

use Nette\Database\Drivers;


/**
 * PDO Oracle database driver.
 * Options:
 *    - formatDateTime => the format in which the date is stored in the database
 */
class Driver implements Drivers\Driver
{
	private const EngineClass = Drivers\Engines\OracleEngine::class;


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
		$engine = new (self::EngineClass)($connection);
		$options = $this->params['options'];
		if (isset($options['formatDateTime'])) {
			$engine->formatDateTime = $options['formatDateTime'];
		}
		return $engine;
	}
}
