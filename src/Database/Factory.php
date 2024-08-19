<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette\StaticClass;


final class Factory
{
	use StaticClass;

	private const Drivers = [
		'pdo-mssql' => Drivers\PDO\MSSQL\Driver::class,
		'pdo-mysql' => Drivers\PDO\MySQL\Driver::class,
		'pdo-oci' => Drivers\PDO\OCI\Driver::class,
		'pdo-odbc' => Drivers\PDO\ODBC\Driver::class,
		'pdo-pgsql' => Drivers\PDO\PgSQL\Driver::class,
		'pdo-sqlite' => Drivers\PDO\SQLite\Driver::class,
		'pdo-sqlsrv' => Drivers\PDO\SQLSrv\Driver::class,
	];


	/** @internal */
	public static function createDriverFromDsn(
		string $dsn,
		?string $username = null,
		#[\SensitiveParameter]
		?string $password = null,
		array $options = [],
	): Drivers\Driver
	{
		if ($class = $params['driverClass'] ?? null) {
			if (!is_subclass_of($class, Drivers\Driver::class)) {
				throw new \LogicException("Driver class '$class' is not subclass of " . Drivers\Driver::class);
			}

		} else {
			$driver = explode(':', $dsn)[0];
			$class = self::Drivers['pdo-' . $driver] ?? null;
			if (!$class) {
				throw new \LogicException("Unknown PDO driver '$driver'.");
			}
		}

		return new $class(['dsn' => $dsn, 'username' => $username, 'password' => $password, 'options' => $options]);
	}
}
