<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;


/**
 * Container of database result fetched into IRow.
 */
interface IRowContainer extends \Traversable
{

	/**
	 * Fetches single row object.
	 * @return IRow|bool if there is no row
	 */
	function fetch();

	/**
	 * Fetches all rows as associative array.
	 * @param  string|int column name used for an array key or null for numeric index
	 * @param  string|int column name used for an array value or null for the whole row
	 * @return array
	 */
	function fetchPairs($key = null, $value = null);

	/**
	 * Fetches all rows.
	 * @return IRow[]
	 */
	function fetchAll();

	/**
	 * Fetches all rows and returns associative tree.
	 * @param  string  associative descriptor
	 * @return array
	 */
	function fetchAssoc($path);
}
