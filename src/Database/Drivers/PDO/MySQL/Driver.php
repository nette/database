<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\MySQL;

use Nette\Database\Drivers;


/**
 * PDO MySQL database driver.
 * Options:
 *    - charset => character encoding to set (default is utf8 or utf8mb4 since MySQL 5.5.3)
 *    - sqlmode => see http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
 *    - convertBoolean => converts INT(1) to boolean
 */
class Driver implements Drivers\Driver
{
	private const EngineClass = Drivers\Engines\MySQLEngine::class;
	private const DefaultCharset = 'utf8mb4';


	public function __construct(
		#[\SensitiveParameter]
		private readonly array $params,
	) {
	}


	public function connect()
	{
		$connection = new \PDO(...$this->params);
		$options = $this->params['options'];
		if ($charset = $options['charset'] ?? self::DefaultCharset) {
			$connection->query('SET NAMES ' . $connection->quote($charset));
		}

		if (isset($options['sqlmode'])) {
			$connection->query('SET sql_mode=' . $connection->quote($options['sqlmode']));
		}
		return $connection;
	}


	public function createDatabaseEngine($connection): Drivers\Engine
	{
		$engine = new (self::EngineClass)($connection);
		$options = $this->params['options'];
		if (isset($options['convertBoolean'])) {
			$engine->convertBoolean = (bool) $options['convertBoolean'];
		}
		return $engine;
	}
}
