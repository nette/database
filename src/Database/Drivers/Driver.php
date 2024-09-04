<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;


/**
 * Creates connection and database engine instances.
 */
interface Driver
{
	/** Establishes a connection to the database. */
	function connect();

	/** Creates a engine instance for the specific database platform. */
	function createEngine($connection): Engine;
}
