<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;


/**
 * Resolves PHP class name for each database table.
 */
interface EntityMapping
{
	/**
	 * Translates database table name to PHP class name.
	 * @return ?class-string<Table\ActiveRow>
	 */
	function getClassName(string $table): ?string;
}
