<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Drivers;

use Nette;


/**
 * Supplemental SQLite2 database driver.
 */
class Sqlite2Driver extends SqliteDriver
{
	public function formatLike($value, $pos)
	{
		throw new Nette\NotSupportedException;
	}


	public function getForeignKeys($table)
	{
		throw new Nette\NotSupportedException; // @see http://www.sqlite.org/foreignkeys.html
	}


	public function getColumnTypes(\PDOStatement $statement)
	{
	}
}
