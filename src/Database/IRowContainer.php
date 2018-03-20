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
	 * @return mixed
	 */
	function fetchField();

	/**
	 * Fetches all rows as associative array.
	 * @param  string|int  $key  column name used for an array key or null for numeric index
	 * @param  string|int  $value  column name used for an array value or null for the whole row
	 */
	function fetchPairs($key = null, $value = null): array;

	/**
	 * Fetches all rows.
	 * @return IRow[]
	 */
	function fetchAll(): array;

	/**
	 * Fetches all rows and returns associative tree.
	 * @param  string  $path  associative descriptor
	 */
	function fetchAssoc(string $path): array;
}
