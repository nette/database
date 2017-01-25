<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


/**
 * Container of database result fetched into IRow.
 */
interface IRowContainer extends \Traversable
{

	/**
	 * Fetches single row object.
	 */
	function fetch(): ?IRow;

	/**
	 * Fetches single field.
	 * @param  int|string
	 * @return mixed
	 */
	function fetchField($column = 0);

	/**
	 * Fetches all rows as associative array.
	 * @param  string|int column name used for an array key or NULL for numeric index
	 * @param  string|int column name used for an array value or NULL for the whole row
	 */
	function fetchPairs($key = NULL, $value = NULL): array;

	/**
	 * Fetches all rows.
	 * @return IRow[]
	 */
	function fetchAll(): array;

	/**
	 * Fetches all rows and returns associative tree.
	 * @param  string  associative descriptor
	 */
	function fetchAssoc(string $path): array;

}
