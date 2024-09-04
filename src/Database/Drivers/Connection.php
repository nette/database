<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;


/**
 * Provides methods for executing queries and managing transactions.
 * Instances are created by a Driver.
 */
interface Connection
{
	/** Executes an SQL query with optional parameters and returns a result set. */
	function query(string $sql, array $params = []);

	/** Executes an SQL command and returns the number of affected rows. */
	function execute(string $sql): int;

	/** Returns the underlying database connection object. */
	function getNativeConnection(): mixed;

	/** Starts a new database transaction. */
	function beginTransaction(): void;

	/** Commits the current database transaction. */
	function commit(): void;

	/** Rolls back the current database transaction. */
	function rollBack(): void;

	/** Returns the ID of the last inserted row or sequence value. */
	function getInsertId(?string $sequence = null): int|string;

	/** Quotes a string for use in an SQL statement. */
	function quote(string $string): string;

	/** Returns the version of the database server. */
	function getServerVersion(): string;
}
