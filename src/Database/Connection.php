<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use JetBrains\PhpStorm\Language;
use Nette\Utils\Arrays;


/**
 * Represents a connection between PHP and a database server.
 */
class Connection
{
	/** @var array<callable(self): void>  Occurs after connection is established */
	public array $onConnect = [];

	/** @var array<callable(self, ResultSet|DriverException): void>  Occurs after query is executed */
	public array $onQuery = [];
	private \Closure $connector;
	private ?Drivers\Connection $connection = null;
	private ?Drivers\Engine $engine;
	private ?SqlPreprocessor $preprocessor;
	private TypeConverter $typeConverter;

	/** @var callable(array, ResultSet): array */
	private $rowNormalizer = [Helpers::class, 'normalizeRow'];
	private ?string $sql = null;
	private int $transactionDepth = 0;


	public function __construct(
		private readonly string $dsn,
		?string $user = null,
		#[\SensitiveParameter]
		?string $password = null,
		array $options = [],
	) {
		$lazy = $options['lazy'] ?? false;
		unset($options['newDateTime'], $options['lazy']);

		$factory = new Factory;
		$this->typeConverter = $factory->createTypeConverter($options);
		$this->connector = $factory->createConnectorFromDsn($dsn, $user, $password, $options);
		if (!$lazy) {
			$this->connect();
		}
	}


	public function connect(): void
	{
		if (!$this->connection) {
			$this->connection = ($this->connector)();
			Arrays::invoke($this->onConnect, $this);
		}
	}


	public function reconnect(): void
	{
		$this->disconnect();
		$this->connect();
	}


	public function disconnect(): void
	{
		$this->connection = null;
	}


	public function getDsn(): string
	{
		return $this->dsn;
	}


	/** @deprecated use getConnectionDriver()->getNativeConnection() */
	public function getPdo(): \PDO
	{
		trigger_error(__METHOD__ . '() is deprecated, use getConnectionDriver()->getNativeConnection()', E_USER_DEPRECATED);
		return $this->getConnectionDriver()->getNativeConnection();
	}


	public function getConnectionDriver(): Drivers\Connection
	{
		$this->connect();
		return $this->connection;
	}


	/** @deprecated use getConnectionDriver() */
	public function getSupplementalDriver(): Drivers\Connection
	{
		trigger_error(__METHOD__ . '() is deprecated, use getConnectionDriver()', E_USER_DEPRECATED);
		return $this->getConnectionDriver();
	}


	public function getDatabaseEngine(): Drivers\Engine
	{
		$this->connect();
		return $this->engine ??= $this->connection->getDatabaseEngine();
	}


	public function getServerVersion(): string
	{
		return $this->getConnectionDriver()->getServerVersion();
	}


	public function getReflection(): Reflection
	{
		return new Reflection($this->getDatabaseEngine());
	}


	public function getTypeConverter(): TypeConverter
	{
		return $this->typeConverter;
	}


	public function setRowNormalizer(?callable $normalizer): static
	{
		$this->rowNormalizer = $normalizer;
		return $this;
	}


	public function getInsertId(?string $sequence = null): int|string
	{
		return $this->getConnectionDriver()->getInsertId($sequence);
	}


	public function quote(string $string): string
	{
		return $this->getConnectionDriver()->quote($string);
	}


	public function beginTransaction(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->getConnectionDriver()->beginTransaction();
	}


	public function commit(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->getConnectionDriver()->commit();
	}


	public function rollBack(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->getConnectionDriver()->rollBack();
	}


	public function transaction(callable $callback): mixed
	{
		if ($this->transactionDepth === 0) {
			$this->beginTransaction();
		}

		$this->transactionDepth++;
		try {
			$res = $callback($this);
		} catch (\Throwable $e) {
			$this->transactionDepth--;
			if ($this->transactionDepth === 0) {
				$this->rollback();
			}

			throw $e;
		}

		$this->transactionDepth--;
		if ($this->transactionDepth === 0) {
			$this->commit();
		}

		return $res;
	}


	/**
	 * Generates and executes SQL query.
	 * @param  literal-string  $sql
	 */
	public function query(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): ResultSet
	{
		[$this->sql, $params] = $this->preprocess($sql, ...$params);
		try {
			$time = microtime(true);
			$result = $this->connection->query($this->sql, $params);
			$time = microtime(true) - $time;
			$resultSet = new ResultSet($this, $result, new SqlLiteral($this->sql, $params), $this->rowNormalizer, $time);
		} catch (DriverException $e) {
			Arrays::invoke($this->onQuery, $this, $e);
			throw $e;
		}

		Arrays::invoke($this->onQuery, $this, $resultSet);
		return $resultSet;
	}


	/** @deprecated  use query() */
	public function queryArgs(string $sql, array $params): ResultSet
	{
		trigger_error(__METHOD__ . '() is deprecated, use query()', E_USER_DEPRECATED);
		return $this->query($sql, ...$params);
	}


	/**
	 * @param  literal-string  $sql
	 * @return array{string, array}
	 */
	public function preprocess(string $sql, ...$params): array
	{
		$this->connect();
		$this->preprocessor ??= new SqlPreprocessor($this);
		return $params
			? $this->preprocessor->process(func_get_args())
			: [$sql, []];
	}


	public function getLastQueryString(): ?string
	{
		return $this->sql;
	}


	/********************* shortcuts ****************d*g**/


	/**
	 * Shortcut for query()->fetch()
	 * @param  literal-string  $sql
	 */
	public function fetch(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): ?Row
	{
		return $this->query($sql, ...$params)->fetch();
	}


	/**
	 * Shortcut for query()->fetchField()
	 * @param  literal-string  $sql
	 */
	public function fetchField(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): mixed
	{
		return $this->query($sql, ...$params)->fetchField();
	}


	/**
	 * Shortcut for query()->fetchFields()
	 * @param  literal-string  $sql
	 */
	public function fetchFields(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): ?array
	{
		return $this->query($sql, ...$params)->fetchFields();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 * @param  literal-string  $sql
	 */
	public function fetchPairs(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): array
	{
		return $this->query($sql, ...$params)->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 * @param  literal-string  $sql
	 */
	public function fetchAll(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): array
	{
		return $this->query($sql, ...$params)->fetchAll();
	}


	public static function literal(string $value, ...$params): SqlLiteral
	{
		return new SqlLiteral($value, $params);
	}
}
