<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;


interface Result
{
	/**
	 * Returns the next row as an associative array or null if there are no more rows.
	 */
	function fetch(): ?array;

	/**
	 * Returns the next row as an indexed array or null if there are no more rows.
	 */
	function fetchList(): ?array;

	/**
	 * Returns the number of columns in a result set.
	 */
	function getColumnCount(): int;

	/**
	 * Returns the number of rows in a result set.
	 */
	function getRowCount(): int;

	/**
	 * Returns metadata for all columns in a result set.
	 * @return list<array{name: string, nativeType: ?string, size: ?int, scale: ?int}>
	 */
	function getColumnsInfo(): array;

	/**
	 * Discards the remaining result set, allowing the statement to be re-executed.
	 */
	function free(): void;
}
