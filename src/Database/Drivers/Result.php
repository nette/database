<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;


/**
 * Database query result set.
 */
interface Result
{
	/** Fetches the next row from the result set as an associative array. */
	function fetch(): ?array;

	/** Fetches the next row from the result set as an indexed array. */
	function fetchList(): ?array;

	/** Returns the number of columns in the result set. */
	function getColumnCount(): int;

	/** Returns the number of rows in the result set or number of affected rows */
	function getRowCount(): int;

	/**
	 * Returns metadata for all columns in a result set.
	 * @return list<array{name: string, nativeType: ?string, size: ?int, scale: ?int}>
	 */
	function getColumnsInfo(): array;

	/** Frees the result set. */
	function free(): void;
}
