<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette\Database;


/**
 * Database platform specific operations and reflection capabilities.
 */
interface Engine
{
	public const
		SupportSequence = 'sequence',
		SupportSelectUngroupedColumns = 'ungrouped_cols',
		SupportMultiInsertAsSelect = 'insert_as_select',
		SupportMultiColumnAsOrCondition = 'multi_column_as_or',
		SupportSchema = 'schema';

	/**
	 * Checks if the engine supports a specific feature.
	 * @param  self::Support*  $feature
	 */
	function isSupported(string $feature): bool;

	/**
	 * Converts PDOException to DriverException or its descendant.
	 */
	function convertException(\PDOException $e): Database\DriverException;

	/********************* SQL utilities ****************d*g**/

	/** Adds delimiters around database identifier. */
	function delimite(string $name): string;

	/** Formats a date-time value for use in an SQL statement. */
	function formatDateTime(\DateTimeInterface $value): string;

	/** Formats a date-time interval for use in an SQL statement. */
	function formatDateInterval(\DateInterval $value): string;

	/** Applies LIMIT and OFFSET clauses to an SQL query. */
	function applyLimit(string &$sql, ?int $limit, ?int $offset): void;

	/********************* reflection ****************d*g**/

	/**
	 * Returns a list of all tables in the database.
	 * @return list<array{name: string, fullName: ?string, view: bool, comment: ?string}>
	 */
	function getTables(): array;

	/**
	 * Returns metadata for all columns in a table.
	 * @return list<array{name: string, table: string, nativeType: string, size: ?int, scale: ?int, nullable: bool, default: mixed, autoIncrement: bool, primary: bool, comment: ?string, vendor: array}>
	 */
	function getColumns(string $table): array;

	/**
	 * Returns metadata for all indexes in a table.
	 * @return list<array{name: string, columns: list<string>, unique: bool, primary: bool}>
	 */
	function getIndexes(string $table): array;

	/**
	 * Returns metadata for all foreign keys in a table.
	 * @return list<array{name: string, local: string, table: string, foreign: string}>
	 */
	function getForeignKeys(string $table): array;

	/**
	 * Returns associative array of detected types (IStructure::FIELD_*) in result set.
	 * @return array<string, string>
	 */
	function getColumnTypes(\PDOStatement $statement): array;
}
