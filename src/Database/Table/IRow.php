<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Table;

use Nette\Database;


/**
 * Row interface.
 */
interface IRow extends Database\IRow
{
	function setTable(Selection $name);

	/**
	 * @return Selection
	 */
	function getTable();

	/**
	 * Returns primary key value.
	 * @param  bool
	 * @return mixed
	 */
	function getPrimary($throw = true);

	/**
	 * Returns row signature (composition of primary keys)
	 * @param  bool
	 * @return string
	 */
	function getSignature($throw = true);

	/**
	 * Returns referencing rows.
	 * @param  string
	 * @param  string
	 * @return GroupedSelection
	 */
	function related($key, $throughColumn = null);

	/**
	 * Returns referenced row.
	 * @param  string
	 * @param  string
	 * @return IRow|null if the row does not exist
	 */
	function ref($key, $throughColumn = null);
}
