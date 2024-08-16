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
		'pdo-mssql' => Drivers\MsSqlDriver::class,
		'pdo-mysql' => Drivers\MySqlDriver::class,
		'pdo-oci' => Drivers\OciDriver::class,
		'pdo-odbc' => Drivers\OdbcDriver::class,
		'pdo-pgsql' => Drivers\PgSqlDriver::class,
		'pdo-sqlite' => Drivers\SqliteDriver::class,
		'pdo-sqlsrv' => Drivers\SqlsrvDriver::class,
	];


	/** @internal */
	public function createConnectorFromDsn(
		string $dsn,
		?string $username = null,
		#[\SensitiveParameter]
		?string $password = null,
		array $options = [],
	): Drivers\Engine
	{
		if ($class = $params['driverClass'] ?? null) {
			if (!is_subclass_of($class, Drivers\Engine::class)) {
				throw new \LogicException("Driver class '$class' is not subclass of " . Drivers\Engine::class);
			}

		} else {
			$driver = explode(':', $dsn)[0];
			$class = self::Drivers['pdo-' . $driver] ?? null;
			if (!$class) {
				throw new \LogicException("Unknown PDO driver '$driver'.");
			}
		}

		return new $class;
	}
}
