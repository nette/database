<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Database;

use Nette\Database\Conventions\AmbiguousReferenceKeyException;


interface IConventions
{

	/**
	 * Returns primary key for table.
	 * @param  string
	 * @return string
	 */
	function getPrimary($table);

	/**
	 * Returns referenced table & referenced column.
	 * Example:
	 *     (author, book) returns array(book, author_id)
	 *
	 * @param  string  source table
	 * @param  string  referencing key
	 * @return array   array(referenced table, referenced column)
	 * @throws AmbiguousReferenceKeyException
	 */
	function getHasManyReference($table, $key);

	/**
	 * Returns referenced table & referencing column.
	 * Example
	 *     (book, author)      returns array(author, author_id)
	 *     (book, translator)  returns array(author, translator_id)
	 *
	 * @param  string  source table
	 * @param  string  referencing key
	 * @return array   array(referenced table, referencing column)
	 */
	function getBelongsToReference($table, $key);

}
