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
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 */
	function fetch(): ?array;

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
	 * @return list<array{name: string, nativeType: ?string, length: ?int, precision: ?int}>
	 */
	function getColumnsInfo(): array;

	/**
	 * Discards the remaining result set, allowing the statement to be re-executed.
	 */
	function free(): void;
}
