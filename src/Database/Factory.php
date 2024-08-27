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
	private const TypeConverterOptions = ['convertBoolean', 'convertDateTime', 'newDateTime'];


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


	/** @internal */
	public static function configure(Connection $connection, array $options): void
	{
		$converter = $connection->getTypeConverter();
		foreach (self::TypeConverterOptions as $opt) {
			if (isset($options[$opt])) {
				$converter->$opt = (bool) $options[$opt];
				unset($options[$opt]);
			}
		}
	}
}
