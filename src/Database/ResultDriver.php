<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


/**
 * Supplemental database driver for result-set.
 */
interface ResultDriver
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
	 * Returns associative array of detected types (IStructure::FIELD_*) in result set.
	 */
	function getColumnTypes(): array;

	/**
	 * Returns associative array of original table names.
	 */
	function getColumnMeta(int $col): array;
}
