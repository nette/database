<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Table;

use Nette\Database;


/**
 * Row interface.
 */
interface IRow extends Database\IRow
{

	function setTable(Selection $name);

	function getTable(): Selection;

	/**
	 * Returns primary key value.
	 * @return mixed
	 */
	function getPrimary(bool $need = TRUE);

	/**
	 * Returns row signature (composition of primary keys)
	 */
	function getSignature(bool $need = TRUE): string;

	/**
	 * Returns referencing rows.
	 */
	function related(string $key, string $throughColumn = NULL): GroupedSelection;

	/**
	 * Returns referenced row.
	 * @return IRow|NULL if the row does not exist
	 */
	function ref(string $key, string $throughColumn = NULL): ?IRow;

}
