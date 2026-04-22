<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use JetBrains\PhpStorm\Language;
use Nette;
use Nette\Utils\Arrays;
use PDO;
use PDOException;
use function str_replace, ucfirst;


/**
 * Manages database connection and executes SQL queries.
 */
class Connection
{
	/** @var array<callable(self): void>  Occurs after connection is established */
	public array $onConnect = [];

	/** @var array<callable(self, ResultSet|DriverException): void>  Occurs after query is executed */
	public array $onQuery = [];
	private Driver $driver;
	private SqlPreprocessor $preprocessor;
	private ?PDO $pdo = null;

	/** @var ?\Closure(array<string, mixed>, ResultSet): array<string, mixed> */
	private ?\Closure $rowNormalizer;
	private ?string $sql = null;
	private int $transactionDepth = 0;


	/** @param array<mixed> $options */
	public function __construct(
		private readonly string $dsn,
		#[\SensitiveParameter]
		private readonly ?string $user = null,
		#[\SensitiveParameter]
		private readonly ?string $password = null,
		private readonly array $options = [],
	) {
		$this->rowNormalizer = !empty($options['newDateTime'])
			? fn(array $row, ResultSet $resultSet): array => Helpers::normalizeRow($row, $resultSet, DateTime::class)
			: Helpers::normalizeRow(...);
		if (empty($options['lazy'])) {
			$this->connect();
		}
	}


	/**
	 * Connects to the database server if not already connected.
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

		$class = empty($this->options['driverClass'])
			? 'Nette\Database\Drivers\\' . ucfirst(str_replace('sql', 'Sql', $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME))) . 'Driver'
			: $this->options['driverClass'];
		$driver = new $class;
		if (!$driver instanceof Driver) {
			throw new Nette\InvalidStateException("Driver class '$class' does not implement " . Driver::class . '.');
		}
		$this->driver = $driver;
		$this->preprocessor = new SqlPreprocessor($this);
		$this->driver->initialize($this, $this->options);
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
		return $this->pdo ?? throw new Nette\ShouldNotHappenException;
	}


	public function getDriver(): Driver
	{
		$this->connect();
		return $this->driver;
	}


	/** @deprecated use getDriver() */
	public function getSupplementalDriver(): Driver
	{
		$this->connect();
		return $this->driver;
	}


	public function getReflection(): Reflection
	{
		return new Reflection($this->getDriver());
	}


	/**
	 * Sets a callback for normalizing each result row (e.g., type conversion). Pass null to disable.
	 * @param ?(callable(array<mixed>, ResultSet): array<mixed>) $normalizer
	 */
	public function setRowNormalizer(?callable $normalizer): static
	{
		$this->rowNormalizer = $normalizer ? $normalizer(...) : null;
		return $this;
	}


	/**
	 * Returns the ID of the last inserted row, or the last value from a sequence.
	 */
	public function getInsertId(?string $sequence = null): string
	{
		try {
			$res = $this->getPdo()->lastInsertId($sequence);
			return $res === false ? '0' : $res;
		} catch (PDOException $e) {
			throw $this->driver->convertException($e);
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
	public function query(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): ResultSet
	{
		[$this->sql, $params] = $this->preprocess($sql, ...$params);
		try {
			$result = new ResultSet($this, $this->sql, $params, $this->rowNormalizer);
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
	public function queryArgs(string $sql, array $params): ResultSet
	{
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
