<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use JetBrains\PhpStorm\Language;
use Nette;
use Nette\Utils\Arrays;


/**
 * Represents a connection between PHP and a database server.
 */
class Connection
{
	/** @var array<callable(self): void>  Occurs after connection is established */
	public array $onConnect = [];

	/** @var array<callable(self, Result|DriverException): void>  Occurs after query is executed */
	public array $onQuery = [];
	private Drivers\Driver $driver;
	private ?Drivers\Connection $connection = null;
	private Drivers\Engine $engine;
	private SqlPreprocessor $preprocessor;
	private TypeConverter $typeConverter;
	private ?SqlLiteral $query = null;
	private int $transactionDepth = 0;


	public function __construct(
		private readonly string $dsn,
		?string $user = null,
		#[\SensitiveParameter]
		?string $password = null,
		array $options = [],
	) {
		$lazy = $options['lazy'] ?? false;
		unset($options['lazy']);

		Factory::configure($this, $options);
		$this->driver = Factory::createDriverFromDsn($dsn, $user, $password, $options);
		if (!$lazy) {
			$this->connect();
		}
	}


	public function connect(): void
	{
		if (!$this->connection) {
			$this->connection = $this->driver->connect();
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


	/** @deprecated use getConnection()->getNativeConnection() */
	public function getPdo(): \PDO
	{
		trigger_error(__METHOD__ . '() is deprecated, use getConnection()->getNativeConnection()', E_USER_DEPRECATED);
		return $this->getConnection()->getNativeConnection();
	}


	public function getConnection(): Drivers\Connection
	{
		$this->connect();
		return $this->connection;
	}


	/** @deprecated use getConnection() */
	public function getSupplementalDriver(): Drivers\Connection
	{
		trigger_error(__METHOD__ . '() is deprecated, use getConnection()', E_USER_DEPRECATED);
		return $this->getConnection();
	}


	public function getDatabaseEngine(): Drivers\Engine
	{
		return $this->engine ??= $this->driver->createDatabaseEngine(new Drivers\Wrapper\LazyConnection($this->getConnection(...)));
	}


	public function getServerVersion(): string
	{
		return $this->getConnection()->getServerVersion();
	}


	public function getReflection(): Reflection
	{
		return new Reflection($this->getDatabaseEngine());
	}


	public function getTypeConverter(): TypeConverter
	{
		return $this->typeConverter ??= new TypeConverter;
	}


	/** @deprecated */
	public function setRowNormalizer(?callable $normalizer): static
	{
		throw new Nette\DeprecatedException(__METHOD__ . "() is deprecated, configure 'convert*' options instead.");
	}


	public function getInsertId(?string $sequence = null): int|string
	{
		return $this->getConnection()->getInsertId($sequence);
	}


	public function quote(string $string): string
	{
		return $this->getConnection()->quote($string);
	}


	public function beginTransaction(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->execute($this->getConnection()->beginTransaction(...), new SqlLiteral('BEGIN TRANSACTION'));
	}


	public function commit(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->execute($this->getConnection()->commit(...), new SqlLiteral('COMMIT'));
	}


	public function rollBack(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->execute($this->getConnection()->rollBack(...), new SqlLiteral('ROLLBACK'));
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
	public function query(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): Result
	{
		$this->query = new SqlLiteral(...$this->preprocess($sql, ...$params));
		return $this->execute(
			fn() => $this->connection->query($this->query->getSql(), $this->query->getParameters()),
			$this->query,
		);
	}


	/** @deprecated  use query() */
	public function queryArgs(string $sql, array $params): Result
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


	private function execute(mixed $callback, SqlLiteral $query): Result
	{
		try {
			$time = microtime(true);
			$result = $callback();
			$time = microtime(true) - $time;
			$result = new Result($this, $query, $result, $time);
			Arrays::invoke($this->onQuery, $this, $result);
		} catch (DriverException $e) {
			Arrays::invoke($this->onQuery, $this, $e);
			throw $e;
		}
		return $result;
	}


	public function getLastQuery(): ?SqlLiteral
	{
		return $this->query;
	}


	/** @deprecated use getLastQuery()->getSql() */
	public function getLastQueryString(): ?string
	{
		trigger_error(__METHOD__ . '() is deprecated, use getLastQuery()->getSql()', E_USER_DEPRECATED);
		return $this->query?->getSql();
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
	 * Shortcut for query()->fetchAssoc()
	 * @param  literal-string  $sql
	 */
	public function fetchAssoc(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): ?array
	{
		return $this->query($sql, ...$params)->fetchAssoc();
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
	 * Shortcut for query()->fetchList()
	 * @param  literal-string  $sql
	 */
	public function fetchList(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): ?array
	{
		return $this->query($sql, ...$params)->fetchList();
	}


	/**
	 * Shortcut for query()->fetchList()
	 * @param  literal-string  $sql
	 */
	public function fetchFields(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): ?array
	{
		return $this->query($sql, ...$params)->fetchList();
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
