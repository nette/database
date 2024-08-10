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
		'mysqli' => Drivers\MySQLi\Connection::class,
		'pdo-mssql' => Drivers\PDO\MSSQL\Connection::class,
		'pdo-mysql' => Drivers\PDO\MySQL\Connection::class,
		'pdo-oci' => Drivers\PDO\OCI\Connection::class,
		'pdo-odbc' => Drivers\PDO\ODBC\Connection::class,
		'pdo-pgsql' => Drivers\PDO\PgSQL\Connection::class,
		'pdo-sqlite' => Drivers\PDO\SQLite\Connection::class,
		'pdo-sqlsrv' => Drivers\PDO\SQLSrv\Connection::class,
	];


	public function __construct(
		private bool $lazy = true,
	) {
	}


	public function createFromParameters(
		#[\SensitiveParameter]
		...$params,
	): Connection
	{
		$params = count($params) === 1 && is_array($params[0] ?? null) ? $params[0] : $params;
		$factory = $this->createConnectorFromParameters($params);
		return $this->createFromConnector($factory);
	}


	public function createFromDsn(
		string $dsn,
		?string $username = null,
		#[\SensitiveParameter]
		?string $password = null,
		array $options = [],
	): Connection
	{
		$factory = $this->createConnectorFromDsn($dsn, $username, $password, $options);
		return $this->createFromConnector($factory);
	}


	/**
	 * @param  \Closure(): Drivers\Connection  $connector
	 */
	public function createFromConnector(\Closure $connector): Connection
	{
		$connection = new Connection($connector);
		if (!$this->lazy) {
			$connection->connect();
		}
		return $connection;

	}


	/** @internal */
	public function createConnectorFromParameters(
		#[\SensitiveParameter]
		array $params,
	): \Closure
	{
		if ($class = $params['driverClass'] ?? null) {
			if (!is_subclass_of($class, Drivers\Connection::class)) {
				throw new \LogicException("Driver class '$class' is not subclass of " . Drivers\Connection::class);
			}
			unset($params['driverClass']);

		} elseif ($driver = $params['driver'] ?? null) {
			$class = self::Drivers[$driver] ?? null;
			if (!$class) {
				throw new \LogicException("Unknown driver '$driver'.");
			}
			unset($params['driver']);

		} elseif ($params['dsn'] ?? null) {
			return $this->createConnectorFromDsn(...$params);

		} else {
			throw new \LogicException("Missing options 'driver' or 'driverClass'.");
		}

		return fn() => new $class(...$params);
	}


	/** @internal */
	public function createConnectorFromDsn(
		string $dsn,
		?string $username,
		#[\SensitiveParameter]
		?string $password,
		array $options = [],
	): \Closure
	{
		$driver = explode(':', $dsn)[0];
		$class = self::Drivers['pdo-' . $driver] ?? null;
		if (!$class) {
			throw new \LogicException("Unknown PDO driver '$driver'.");
		}
		return fn() => new $class($dsn, $username, $password, $options);
	}


	public function createTypeConverter(array &$options): TypeConverter
	{
		$converter = new TypeConverter;
		foreach (['convertBoolean', 'convertDateTime', 'convertDecimal'] as $opt) {
			if (isset($options[$opt])) {
				$converter->$opt = (bool) $options[$opt];
				unset($options[$opt]);
			}
		}
		return $converter;
	}
}
