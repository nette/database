<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


/**
 * Provides database-specific functionality.
 */
interface Driver
{
	public const
		SupportSequence = 'sequence',
		SupportSelectUngroupedColumns = 'ungrouped_cols',
		SupportMultiInsertAsSelect = 'insert_as_select',
		SupportMultiColumnAsOrCondition = 'multi_column_as_or',
		SupportSchema = 'schema';

	/** @deprecated use Driver::Support* */
	public const
		SUPPORT_SEQUENCE = 'sequence',
		SUPPORT_SELECT_UNGROUPED_COLUMNS = 'ungrouped_cols',
		SUPPORT_MULTI_INSERT_AS_SELECT = 'insert_as_select',
		SUPPORT_MULTI_COLUMN_AS_OR_COND = 'multi_column_as_or',
		SUPPORT_SUBSELECT = 'subselect',
		SUPPORT_SCHEMA = 'schema',
		SupportSubselect = 'subselect';

	/**
	 * Checks if the engine supports a specific feature.
	 * @param  self::Support*  $feature
	 */
	function isSupported(string $feature): bool;

	/**
	 * Initializes connection.
	 */
	function initialize(Connection $connection, array $options): void;

	/**
	 * Converts PDOException to DriverException or its descendant.
	 */
	function convertException(\PDOException $e): DriverException;

	/********************* SQL utilities ****************d*g**/

	/** Adds delimiters around database identifier. */
	function delimite(string $name): string;

	/** Formats a date-time value for use in an SQL statement. */
	function formatDateTime(\DateTimeInterface $value): string;

	/** Formats a date-time interval for use in an SQL statement. */
	function formatDateInterval(\DateInterval $value): string;

	/** Encodes string for use in a LIKE statement. */
	function formatLike(string $value, int $pos): string;

	/** Applies LIMIT and OFFSET clauses to an SQL query. */
	function applyLimit(string &$sql, ?int $limit, ?int $offset): void;

	/********************* reflection ****************d*g**/

	/**
	 * Returns a list of all tables in the database.
	 * @return list<array{name: string, fullName: string, view: bool}>
	 */
	function getTables(): array;

	/**
	 * Returns metadata for all columns in a table.
	 * @return list<array{name: string, table: string, nativetype: string, size: int|null, nullable: bool, default: mixed, autoincrement: bool, primary: bool, vendor: array}>
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


interface_exists(ISupplementalDriver::class);
