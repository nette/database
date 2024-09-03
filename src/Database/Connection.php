<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use JetBrains\PhpStorm\Language;
use Nette;
use Nette\Utils\Arrays;
use PDOException;


/**
 * Manages database connection and executes SQL queries.
 */
class Connection
{
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

	/** @var array<callable(self): void>  Occurs after connection is established */
	public array $onConnect = [];

	/** @var array<callable(self, Result|DriverException): void>  Occurs after query is executed */
	public array $onQuery = [];
	private Drivers\Driver $driver;
	private ?Drivers\Connection $connection = null;
	private Drivers\Engine $engine;
	private SqlPreprocessor $preprocessor;
	private TypeConverter $typeConverter;
	private ?string $sql = null;
	private int $transactionDepth = 0;


	/** @param array<mixed> $options */
	public function __construct(
		private readonly string $dsn,
		?string $username = null,
		#[\SensitiveParameter]
		?string $password = null,
		array $options = [],
	) {
		$driver = explode(':', $dsn)[0];
		$class = empty($options['driverClass'])
			? (self::Drivers['pdo-' . $driver] ?? throw new \LogicException("Unknown PDO driver '$driver'."))
			: $options['driverClass'];
		$args = compact('dsn', 'username', 'password', 'options');
		unset($options['lazy'], $options['driverClass']);
		foreach ($options as $key => $value) {
			if (!is_int($key) && $value !== null) {
				$args[$key] = $value;
				unset($args['options'][$key]);
			}
		}
		$args = array_diff_key($args, array_flip(self::TypeConverterOptions));
		$this->driver = new $class(...$args);
		$this->typeConverter = new TypeConverter;
		array_map(fn($opt) => isset($options[$opt]) && ($this->typeConverter->$opt = (bool) $options[$opt]), self::TypeConverterOptions);
	}


	/**
	 * Connects to the database server if not already connected.
	 * @throws ConnectionException
	 */
	public function connect(): void
	{
		if ($this->connection) {
			return;
		}

		try {
			$this->connection = $this->driver->connect();
		} catch (PDOException $e) {
			throw ConnectionException::from($e);
		}

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
		return $this->engine ??= $this->driver->createEngine(new Drivers\Accessory\LazyConnection($this->getConnection(...)));
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
		return $this->typeConverter;
	}


	/** @deprecated */
	public function setRowNormalizer(?callable $normalizer): static
	{
		throw new Nette\DeprecatedException(__METHOD__ . "() is deprecated, configure 'convert*' options instead.");
	}


	/**
	 * Returns the ID of the last inserted row, or the last value from a sequence.
	 */
	public function getInsertId(?string $sequence = null): string
	{
		try {
			return $this->getConnection()->getInsertId($sequence);
		} catch (PDOException $e) {
			throw $this->getDatabaseEngine()->convertException($e);
		}
	}


	/**
	 * Quotes string for use in SQL.
	 */
	public function quote(string $string): string
	{
		return $this->getConnection()->quote($string);
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
	 * Executes callback inside a transaction. Supports nesting.
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
				$this->rollBack();
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
	public function query(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): Result
	{
		[$this->sql, $params] = $this->preprocess($sql, ...$params);
		try {
			$result = new Result($this, $this->sql, $params);
		} catch (PDOException $e) {
			Arrays::invoke($this->onQuery, $this, $e);
			throw $e;
		}

		Arrays::invoke($this->onQuery, $this, $result);
		return $result;
	}


	/**
	 * @deprecated  use query()
	 * @param  literal-string  $sql
	 * @param  array<mixed>  $params
	 */
	public function queryArgs(string $sql, array $params): Result
	{
		trigger_error(__METHOD__ . '() is deprecated, use query()', E_USER_DEPRECATED);
		return $this->query($sql, ...$params);
	}


	/**
	 * Preprocesses SQL query with parameter substitution and returns the resulting SQL and bound parameters.
	 * @param  literal-string  $sql
	 * @return array{string, array<mixed>}
	 */
	public function preprocess(string $sql, mixed ...$params): array
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
	 * Executes SQL query and returns the first row, or null if no rows were returned.
	 * @param  literal-string  $sql
	 */
	public function fetch(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): ?Row
	{
		return $this->query($sql, ...$params)->fetch();
	}


	/**
	 * Executes SQL query and returns the first row as an associative array, or null.
	 * @param  literal-string  $sql
	 * @return ?array<mixed>
	 */
	public function fetchAssoc(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): ?array
	{
		return $this->query($sql, ...$params)->fetchAssoc();
	}


	/**
	 * Executes SQL query and returns the first field of the first row, or null.
	 * @param  literal-string  $sql
	 */
	public function fetchField(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): mixed
	{
		return $this->query($sql, ...$params)->fetchField();
	}


	/**
	 * Executes SQL query and returns the first row as an indexed array, or null.
	 * @param  literal-string  $sql
	 * @return ?list<mixed>
	 */
	public function fetchList(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): ?array
	{
		return $this->query($sql, ...$params)->fetchList();
	}


	/**
	 * Executes SQL query and returns the first row as an indexed array, or null.
	 * @param  literal-string  $sql
	 * @return ?list<mixed>
	 */
	public function fetchFields(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): ?array
	{
		return $this->query($sql, ...$params)->fetchList();
	}


	/**
	 * Executes SQL query and returns rows as key-value pairs.
	 * @param  literal-string  $sql
	 * @return array<mixed, mixed>
	 */
	public function fetchPairs(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): array
	{
		return $this->query($sql, ...$params)->fetchPairs();
	}


	/**
	 * Executes SQL query and returns all rows as an array of Row objects.
	 * @param  literal-string  $sql
	 * @return list<Row>
	 */
	public function fetchAll(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): array
	{
		return $this->query($sql, ...$params)->fetchAll();
	}


	/**
	 * Creates SQL literal value.
	 */
	public static function literal(string $value, mixed ...$params): SqlLiteral
	{
		return new SqlLiteral($value, $params);
	}
}
