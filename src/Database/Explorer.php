<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;


/**
 * Manages database connection and executes SQL queries.
 */
class Explorer extends Database
{
	/** @internal */
	public function createActiveRow(Table\Selection $selection, array $row): Table\ActiveRow
	{
		return new Table\ActiveRow($row, $selection);
	}


	/** @internal */
	public function createGroupedSelectionInstance(
		Table\Selection $selection,
		string $table,
		string $column,
	): Table\GroupedSelection
	{
		return new Table\GroupedSelection($this, $table, $column, $selection);
	}
}


class_exists(Connection::class);
