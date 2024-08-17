<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


final class Factory
{
	private const Drivers = [
		'pdo-mssql' => Drivers\PDO\MSSQL\Connection::class,
		'pdo-mysql' => Drivers\PDO\MySQL\Connection::class,
		'pdo-oci' => Drivers\PDO\OCI\Connection::class,
		'pdo-odbc' => Drivers\PDO\ODBC\Connection::class,
		'pdo-pgsql' => Drivers\PDO\PgSQL\Connection::class,
		'pdo-sqlite' => Drivers\PDO\SQLite\Connection::class,
		'pdo-sqlsrv' => Drivers\PDO\SQLSrv\Connection::class,
	];


	/** @internal */
	public function createConnectorFromDsn(
		string $dsn,
		?string $username = null,
		#[\SensitiveParameter]
		?string $password = null,
		array $options = [],
	): \Closure
	{
		if ($class = $params['driverClass'] ?? null) {
			if (!is_subclass_of($class, Drivers\Connection::class)) {
				throw new \LogicException("Driver class '$class' is not subclass of " . Drivers\Connection::class);
			}

		} else {
			$driver = explode(':', $dsn)[0];
			$class = self::Drivers['pdo-' . $driver] ?? null;
			if (!$class) {
				throw new \LogicException("Unknown PDO driver '$driver'.");
			}
		}

		return fn() => new $class($dsn, $username, $password, $options);
	}
}
