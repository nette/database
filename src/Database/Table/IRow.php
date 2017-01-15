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

	function getTable();

	function getPrimary($need = TRUE);

	function getSignature($need = TRUE);

	function related($key, $throughColumn = NULL);

	function ref($key, $throughColumn = NULL);

}
