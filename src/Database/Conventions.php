<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use Nette\Database\Conventions\AmbiguousReferenceKeyException;


/**
 * Provides naming conventions for database tables and columns.
 */
interface Conventions
{
	/**
	 * Returns primary key for table.
	 * @return string|list<string>|null
	 */
	function getPrimary(string $table): string|array|null;

	/**
	 * Returns the referencing table name and referencing column for a has-many relationship.
	 * Example: (author, book) returns [book, author_id]
	 *
	 * @return ?array{string, string}
	 * @throws AmbiguousReferenceKeyException
	 */
	function getHasManyReference(string $table, string $key): ?array;

	/**
	 * Returns the referenced table name and local foreign key column for a belongs-to relationship.
	 * Example:
	 *     (book, author)      returns [author, author_id]
	 *     (book, translator)  returns [author, translator_id]
	 *
	 * @return ?array{string, string}
	 */
	function getBelongsToReference(string $table, string $key): ?array;
}


interface_exists(IConventions::class);
