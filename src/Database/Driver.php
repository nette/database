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
interface Driver
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
	 */
	function applyLimit(string &$sql, ?int $limit, ?int $offset): void;

	/********************* reflection ****************d*g**/

	/** @return list<array{name: string, fullName: string, view: bool}> */
	function getTables(): array;

	/** @return list<array{name: string, table: string, nativetype: string, size: int|null, nullable: bool, default: mixed, autoincrement: bool, primary: bool, vendor: array}> */
	function getColumns(string $table): array;

	/** @return list<array{name: string, columns: list<string>, unique: bool, primary: bool}> */
	function getIndexes(string $table): array;

	/** @return list<array{name: string, local: string, table: string, foreign: string}> */
	function getForeignKeys(string $table): array;

	/**
	 * Returns associative array of detected types (IStructure::FIELD_*) in result set.
	 * @return array<string, string>
	 */
	function getColumnTypes(\PDOStatement $statement): array;

	/**
	 * Cheks if driver supports specific property
	 * @param  string  $item  self::SUPPORT_* property
	 */
	function isSupported(string $item): bool;
}


interface_exists(ISupplementalDriver::class);
