<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;


/**
 * Database connection driver.
 */
interface Connection
{
	function getDatabaseEngine(): Engine;

	function query(string $sql, array $params = []): Result;

	function getNativeConnection(): mixed;

	function beginTransaction(): void;

	function commit(): void;

	function rollBack(): void;

	function getInsertId(?string $sequence = null): int|string;

	function quote(string $string): string;

	function getServerVersion(): string;
}
