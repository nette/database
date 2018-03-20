<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


/**
 * Supplemental PDO database driver.
 */
interface ISupplementalDriver
{
	public const
		SUPPORT_SEQUENCE = 'sequence',
		SUPPORT_SELECT_UNGROUPED_COLUMNS = 'ungrouped_cols',
		SUPPORT_MULTI_INSERT_AS_SELECT = 'insert_as_select',
		SUPPORT_MULTI_COLUMN_AS_OR_COND = 'multi_column_as_or',
		SUPPORT_SUBSELECT = 'subselect',
		SUPPORT_SCHEMA = 'schema';

	/**
	 * Initializes connection.
	 */
	function initialize(Connection $connection, array $options): void;

	/**
	 * Converts PDOException to DriverException or its descendant.
	 */
	function convertException(\PDOException $e): DriverException;

	/**
	 * Delimites identifier for use in a SQL statement.
	 */
	function delimite(string $name): string;

	/**
	 * Formats date-time for use in a SQL statement.
	 */
	function formatDateTime(\DateTimeInterface $value): string;

	/**
	 * Formats date-time interval for use in a SQL statement.
	 */
	function formatDateInterval(\DateInterval $value): string;

	/**
	 * Encodes string for use in a LIKE statement.
	 */
	function formatLike(string $value, int $pos): string;

	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 * @param  string  $sql query that will be modified.
	 */
	function applyLimit(string &$sql, ?int $limit, ?int $offset): void;

	/********************* reflection ****************d*g**/

	/**
	 * Returns list of tables as tuples [(string) name, (bool) view, [(string) fullName]]
	 */
	function getTables(): array;

	/**
	 * Returns metadata for all columns in a table.
	 * As tuples [(string) name, (string) table, (string) nativetype, (int) size, (bool) nullable, (mixed) default, (bool) autoincrement, (bool) primary, (array) vendor]]
	 */
	function getColumns(string $table): array;

	/**
	 * Returns metadata for all indexes in a table.
	 * As tuples [(string) name, (string[]) columns, (bool) unique, (bool) primary]
	 */
	function getIndexes(string $table): array;

	/**
	 * Returns metadata for all foreign keys in a table.
	 * As tuples [(string) name, (string) local, (string) table, (string) foreign]
	 */
	function getForeignKeys(string $table): array;

	/**
	 * Returns associative array of detected types (IStructure::FIELD_*) in result set.
	 */
	function getColumnTypes(\PDOStatement $statement): array;

	/**
	 * Cheks if driver supports specific property
	 * @param  string  $item  self::SUPPORT_* property
	 */
	function isSupported(string $item): bool;
}
