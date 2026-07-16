<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Table;

use Nette;


/**
 * Contract of a database-attached row with support for relations.
 *
 * It is designed to be implemented solely by composing the RowBehavior trait, as ActiveRow
 * does. Only that combination is covered by the backward compatibility promise, which lets
 * this interface gain methods even in minor releases; do not implement it by hand.
 */
interface Row
{
	/**
	 * @internal
	 * @param Selection<ActiveRow> $table
	 */
	function setTable(Selection $table): void;

	/**
	 * @internal
	 * @return Selection<ActiveRow>
	 */
	function getTable(): Selection;

	function getExplorer(): Nette\Database\Explorer;

	/** @return array<string, mixed> */
	function toArray(): array;

	/**
	 * Returns primary key value, or an array of values for composite primary keys.
	 */
	function getPrimary(bool $throw = true): mixed;

	/**
	 * Returns row signature (composition of primary keys).
	 */
	function getSignature(bool $throw = true): string;

	/**
	 * Returns referenced row, or null if the row does not exist.
	 */
	function ref(string $key, ?string $throughColumn = null): ?ActiveRow;

	/**
	 * Returns referencing rows collection.
	 * @return GroupedSelection<ActiveRow>
	 */
	function related(string $key, ?string $throughColumn = null): GroupedSelection;

	/**
	 * Updates row data and refreshes the instance from database. Returns true if the row was changed.
	 * @param  iterable<string, mixed>  $data
	 */
	function update(iterable $data): bool;

	/**
	 * Deletes the row from database.
	 * @return int  number of affected rows
	 */
	function delete(): int;

	/** @internal */
	function accessColumn(?string $key, bool $selectColumn = true): bool;
}
