<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use JetBrains\PhpStorm\Language;
use Nette;
use Nette\Database\Conventions\StaticConventions;
use function class_exists;


/**
 * Provides high-level database layer with ActiveRow pattern.
 */
class Explorer
{
	private readonly Conventions $conventions;


	public function __construct(
		private readonly Connection $connection,
		private readonly IStructure $structure,
		?Conventions $conventions = null,
		private readonly ?Nette\Caching\Storage $cacheStorage = null,
	) {
		$this->conventions = $conventions ?: new StaticConventions;
	}


	public function beginTransaction(): void
	{
		$this->connection->beginTransaction();
	}


	public function commit(): void
	{
		$this->connection->commit();
	}


	public function rollBack(): void
	{
		$this->connection->rollBack();
	}


	/**
	 * Executes callback inside a transaction.
	 * @param  callable(static): mixed  $callback
	 */
	public function transaction(callable $callback): mixed
	{
		return $this->connection->transaction(fn() => $callback($this));
	}


	public function getInsertId(?string $sequence = null): string
	{
		return $this->connection->getInsertId($sequence);
	}


	/**
	 * Generates and executes SQL query.
	 * @param  literal-string  $sql
	 */
	public function query(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): ResultSet
	{
		return $this->connection->query($sql, ...$params);
	}


	/**
	 * @deprecated  use query()
	 * @param  literal-string  $sql
	 * @param  array<mixed>  $params
	 */
	public function queryArgs(string $sql, array $params): ResultSet
	{
		return $this->connection->query($sql, ...$params);
	}


	/**
	 * Returns table selection.
	 * @return Table\Selection<Table\ActiveRow>
	 */
	public function table(string $table): Table\Selection
	{
		return new Table\Selection($this, $this->conventions, $table, $this->cacheStorage);
	}


	public function getConnection(): Connection
	{
		return $this->connection;
	}


	public function getStructure(): IStructure
	{
		return $this->structure;
	}


	public function getConventions(): Conventions
	{
		return $this->conventions;
	}


	/**
	 * Creates an ActiveRow instance, using the configured row mapping class if available.
	 * @param  array<string, mixed>  $data
	 * @param  Table\Selection<Table\ActiveRow>  $selection
	 */
	public function createActiveRow(array $data, Table\Selection $selection): Table\ActiveRow
	{
		return new Table\ActiveRow($data, $selection);
	}


	/**
	 * @internal
	 * @param Table\Selection<Table\ActiveRow> $refSelection
	 * @return Table\GroupedSelection<Table\ActiveRow>
	 */
	public function createGroupedSelection(
		Table\Selection $refSelection,
		string $table,
		string $column,
	): Table\GroupedSelection
	{
		return new Table\GroupedSelection($this, $this->conventions, $table, $column, $refSelection, $this->cacheStorage);
	}


	/********************* shortcuts ****************d*g**/


	/**
	 * Executes SQL query and returns the first row, or null if no rows were returned.
	 * @param  literal-string  $sql
	 */
	public function fetch(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): ?Row
	{
		return $this->connection->query($sql, ...$params)->fetch();
	}


	/**
	 * Executes SQL query and returns the first row as an associative array, or null.
	 * @param  literal-string  $sql
	 * @return ?array<mixed>
	 */
	public function fetchAssoc(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): ?array
	{
		return $this->connection->query($sql, ...$params)->fetchAssoc();
	}


	/**
	 * Executes SQL query and returns the first field of the first row, or null.
	 * @param  literal-string  $sql
	 */
	public function fetchField(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): mixed
	{
		return $this->connection->query($sql, ...$params)->fetchField();
	}


	/**
	 * Executes SQL query and returns the first row as an indexed array, or null.
	 * @param  literal-string  $sql
	 * @return ?list<mixed>
	 */
	public function fetchList(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): ?array
	{
		return $this->connection->query($sql, ...$params)->fetchList();
	}


	/**
	 * Executes SQL query and returns the first row as an indexed array, or null.
	 * @param  literal-string  $sql
	 * @return ?list<mixed>
	 */
	public function fetchFields(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): ?array
	{
		return $this->connection->query($sql, ...$params)->fetchList();
	}


	/**
	 * Executes SQL query and returns rows as key-value pairs.
	 * @param  literal-string  $sql
	 * @return array<mixed, mixed>
	 */
	public function fetchPairs(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): array
	{
		return $this->connection->query($sql, ...$params)->fetchPairs();
	}


	/**
	 * Executes SQL query and returns all rows as an array of Row objects.
	 * @param  literal-string  $sql
	 * @return list<Row>
	 */
	public function fetchAll(#[Language('SQL')] string $sql, #[Language('GenericSQL')] mixed ...$params): array
	{
		return $this->connection->query($sql, ...$params)->fetchAll();
	}


	/**
	 * Creates SQL literal value.
	 */
	public static function literal(string $value, mixed ...$params): SqlLiteral
	{
		return new SqlLiteral($value, $params);
	}
}


class_exists(Context::class);
