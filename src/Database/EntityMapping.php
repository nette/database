<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;


/**
 * Translates identifier names between PHP conventions and database conventions.
 */
interface EntityMapping
{
	/**
	 * Translates database table name to PHP class name.
	 * @return ?class-string<Table\ActiveRow>
	 */
	function getClassName(string $table): ?string;

	/**
	 * Translates database column name to PHP property name.
	 */
	function getPropertyName(string $name): string;

	/**
	 * Translates PHP property name to database column name. In dotted paths
	 * (e.g. 'book.title' in WHERE/ORDER fragments) it is invoked only on the
	 * last segment; preceding segments are treated as table/alias names and
	 * left untouched.
	 */
	function getColumnName(string $name): string;
}
