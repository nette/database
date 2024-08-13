<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\SQLite;

use Nette\Database\Drivers;


/**
 * PDO SQLite database driver.
 * Options:
 *    - formatDateTime => the format in which the date is stored in the database
 */
class Driver implements Drivers\Driver
{
	private const EngineClass = Drivers\Engines\SQLiteEngine::class;


	public function __construct(
		#[\SensitiveParameter]
		private readonly array $params,
	) {
	}


	public function connect(): Drivers\Connection
	{
		$connection = new Drivers\PDO\Connection(self::EngineClass, ...$this->params);
		$connection->metaTypeKey = 'sqlite:decl_type';
		return $connection;
	}


	public function createDatabaseEngine(Drivers\Connection $connection): Drivers\Engine
	{
		$engine = new (self::EngineClass)($connection);
		$options = $this->params['options'];
		if (isset($options['formatDateTime'])) {
			$engine->formatDateTime = $options['formatDateTime'];
		}
		return $engine;
	}
}