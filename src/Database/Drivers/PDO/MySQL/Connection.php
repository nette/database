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
 * Driver options:
 *    - charset => character encoding to set (default is utf8 or utf8mb4 since MySQL 5.5.3)
 *    - sqlmode => see http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
 *    - convertBoolean => converts INT(1) to boolean
 */
class Connection extends Drivers\PDO\Connection
{
	public const DefaultCharset = 'utf8mb4';


	public function getDatabaseEngine(): Drivers\Engines\MySQLEngine
	{
		return new Drivers\Engines\MySQLEngine($this);
	}


	protected function initialize(array $options): void
	{
		if ($charset = $options['charset'] ?? self::DefaultCharset) {
			$this->query('SET NAMES ' . $this->quote($charset));
		}

		if (isset($options['sqlmode'])) {
			$this->query('SET sql_mode=' . $this->quote($options['sqlmode']));
		}

		if (isset($options['convertBoolean'])) {
			$this->engine->convertBoolean = (bool) $options['convertBoolean'];
		}
	}
}
