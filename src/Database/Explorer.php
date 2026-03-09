<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use Closure;


/**
 * Manages database connection and executes SQL queries.
 */
class Explorer extends Database
{
	/** @var ?(Closure(string $table): class-string<Table\ActiveRow>) */
	private ?Closure $rowMapping = null;


	public function setRowMapping(?Closure $factory): void
	{
		$this->rowMapping = $factory;
	}


	/** @internal */
	public function createActiveRow(Table\Selection $selection, array $row): Table\ActiveRow
	{
		$class = $this->rowMapping
			? ($this->rowMapping)($selection->getName())
			: Table\ActiveRow::class;
		return new $class($row, $selection);
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
