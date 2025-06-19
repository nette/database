<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use JetBrains\PhpStorm\Language;
use Nette\Utils\Arrays;
use Nette\Utils\DateTime;
use PDO;
use PDOException;
use function func_get_args, str_replace, ucfirst;


/**
 * Manages database connection and executes SQL queries.
 */
class Connection
{
	private const Drivers = [
		'pdo-mssql' => Drivers\Engines\MSSQLEngine::class,
		'pdo-mysql' => Drivers\Engines\MySQLEngine::class,
		'pdo-oci' => Drivers\Engines\OracleEngine::class,
		'pdo-odbc' => Drivers\Engines\ODBCEngine::class,
		'pdo-pgsql' => Drivers\Engines\PostgreSQLEngine::class,
		'pdo-sqlite' => Drivers\Engines\SQLiteEngine::class,
		'pdo-sqlsrv' => Drivers\Engines\SQLServerEngine::class,
	];

	/** @var array<callable(self): void>  Occurs after connection is established */
	public array $onConnect = [];

	/** @var array<callable(self, Result|DriverException): void>  Occurs after query is executed */
	public array $onQuery = [];
	private Drivers\Engine $engine;
	private SqlPreprocessor $preprocessor;
	private ?PDO $pdo = null;

	/** @var ?\Closure(array<string, mixed>, Result): array<string, mixed> */
	private ?\Closure $rowNormalizer;
	private ?string $sql = null;
	private int $transactionDepth = 0;


	public function __construct(
		private readonly string $dsn,
		#[\SensitiveParameter]
		private readonly ?string $user = null,
		#[\SensitiveParameter]
		private readonly ?string $password = null,
		private readonly array $options = [],
	) {
		$this->rowNormalizer = ($options['newDateTime'] ?? null) === false
			? fn(array $row, ResultSet $resultSet): array => Helpers::normalizeRow($row, $resultSet, DateTime::class)
			: Helpers::normalizeRow(...);
	}


	/**
	 * @throws ConnectionException
	 */
	public function connect(): void
	{
		if ($this->pdo) {
			return;
		}

		try {
			$this->pdo = new PDO($this->dsn, $this->user, $this->password, $this->options);
		} catch (PDOException $e) {
			throw ConnectionException::from($e);
		}

		$driver = explode(':', $this->dsn)[0];
		$class = empty($this->options['driverClass'])
			? (self::Drivers['pdo-' . $driver] ?? throw new \LogicException("Unknown PDO driver '$driver'."))
			: $this->options['driverClass'];
		$this->engine = new $class;
		$this->preprocessor = new SqlPreprocessor($this);
		$this->engine->initialize($this, $this->options);
		Arrays::invoke($this->onConnect, $this);
	}


	/**
	 * Disconnects and connects to database again.
	 */
	public function reconnect(): void
	{
		$this->disconnect();
		$this->connect();
	}


	/**
	 * Disconnects from database.
	 */
	public function disconnect(): void
	{
		$this->pdo = null;
	}


	public function getDsn(): string
	{
		return $this->dsn;
	}


	public function getPdo(): PDO
	{
		$this->connect();
		return $this->pdo;
	}


	/** @deprecated use getDriver() */
	public function getSupplementalDriver(): Drivers\Engine
	{
		trigger_error(__METHOD__ . '() is deprecated, use getDriver()', E_USER_DEPRECATED);
		$this->connect();
		return $this->engine;
	}


	public function getDatabaseEngine(): Drivers\Engine
	{
		$this->connect();
		return $this->engine;
	}


	public function getReflection(): Reflection
	{
		return new Reflection($this->getDatabaseEngine());
	}


	/**
	 * Sets callback for row preprocessing.
	 */
	public function setRowNormalizer(?callable $normalizer): static
	{
		$this->rowNormalizer = $normalizer ? $normalizer(...) : null;
		return $this;
	}


	/**
	 * Returns last inserted ID.
	 */
	public function getInsertId(?string $sequence = null): string
	{
		try {
			$res = $this->getPdo()->lastInsertId($sequence);
			return $res === false ? '0' : $res;
		} catch (PDOException $e) {
			throw $this->engine->convertException($e);
		}
	}


	/**
	 * Quotes string for use in SQL.
	 */
	public function quote(string $string, int $type = PDO::PARAM_STR): string
	{
		try {
			return $this->getPdo()->quote($string, $type);
		} catch (PDOException $e) {
			throw DriverException::from($e);
		}
	}


	/**
	 * Starts a transaction.
	 * @throws \LogicException  when called inside a transaction
	 */
	public function beginTransaction(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->query('::beginTransaction');
	}


	/**
	 * Commits current transaction.
	 * @throws \LogicException  when called inside a transaction
	 */
	public function commit(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->query('::commit');
	}


	/**
	 * Rolls back current transaction.
	 * @throws \LogicException  when called inside a transaction
	 */
	public function rollBack(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->query('::rollBack');
	}


	/**
	 * Executes callback inside a transaction.
	 * @param  callable(static): mixed  $callback
	 */
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
		[$this->sql, $params] = $this->preprocess($sql, ...$params);
		try {
			$result = new Result($this, $this->sql, $params, $this->rowNormalizer);
		} catch (PDOException $e) {
			Arrays::invoke($this->onQuery, $this, $e);
			throw $e;
		}

		Arrays::invoke($this->onQuery, $this, $result);
		return $result;
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
	 * @return ?list<mixed>
	 */
	public function fetchList(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): ?array
	{
		return $this->query($sql, ...$params)->fetchList();
	}


	/**
	 * Shortcut for query()->fetchList()
	 * @param  literal-string  $sql
	 * @return ?list<mixed>
	 */
	public function fetchFields(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): ?array
	{
		return $this->query($sql, ...$params)->fetchList();
	}


	/**
	 * Shortcut for query()->fetchPairs()
	 * @param  literal-string  $sql
	 * @return array<mixed, mixed>
	 */
	public function fetchPairs(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): array
	{
		return $this->query($sql, ...$params)->fetchPairs();
	}


	/**
	 * Shortcut for query()->fetchAll()
	 * @param  literal-string  $sql
	 * @return Row[]
	 */
	public function fetchAll(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): array
	{
		return $this->query($sql, ...$params)->fetchAll();
	}


	/**
	 * Creates SQL literal value.
	 */
	public static function literal(string $value, ...$params): SqlLiteral
	{
		return new SqlLiteral($value, $params);
	}
}
