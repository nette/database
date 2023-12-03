<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


/**
 * Supplemental database driver.
 */
interface Driver
{
	public const
		SupportSequence = 'sequence',
		SupportSelectUngroupedColumns = 'ungrouped_cols',
		SupportMultiInsertAsSelect = 'insert_as_select',
		SupportMultiColumnAsOrCond = 'multi_column_as_or',
		SupportSubselect = 'subselect',
		SupportSchema = 'schema';

	/**
	 * Initializes connection.
	 * @throws ConnectionException
	 */
	function connect(string $dsn, ?string $user = null, ?string $password = null, ?array $options = null): void;

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
	 * Returns associative array of detected types in result set.
	 * @return array<string, Type::*>
	 */
	function getColumnTypes(\PDOStatement $statement): array;

	/**
	 * Cheks if driver supports specific property
	 * @param  self::Support*  $item
	 */
	function isSupported(string $item): bool;
}


interface_exists(ISupplementalDriver::class);
