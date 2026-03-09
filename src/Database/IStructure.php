<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;


/**
 * Provides cached reflection for database structure.
 */
interface IStructure
{
	public const
		FIELD_TEXT = 'string',
		FIELD_BINARY = 'bin',
		FIELD_BOOL = 'bool',
		FIELD_INTEGER = 'int',
		FIELD_FLOAT = 'float',
		FIELD_DECIMAL = 'decimal',
		FIELD_DATE = 'date',
		FIELD_TIME = 'time',
		FIELD_DATETIME = 'datetime',
		FIELD_UNIX_TIMESTAMP = 'timestamp',
		FIELD_TIME_INTERVAL = 'timeint';

	/**
	 * Returns all tables in the database.
	 * @return list<array{name: string, fullName?: string, view: bool}>
	 */
	function getTables(): array;

	/**
	 * Returns all columns in a table.
	 * @return list<array{name: string, table: string, nativetype: string, size: ?int, nullable: bool, default: mixed, autoincrement: bool, primary: bool, vendor: array<string, mixed>}>
	 */
	function getColumns(string $table): array;

	/**
	 * Returns table primary key.
	 * @return string|list<string>|null
	 */
	function getPrimaryKey(string $table): string|array|null;

	/**
	 * Returns autoincrement primary key name.
	 */
	function getPrimaryAutoincrementKey(string $table): ?string;

	/**
	 * Returns table primary key sequence.
	 */
	function getPrimaryKeySequence(string $table): ?string;

	/**
	 * Returns tables referencing the given table via foreign key, or null if unknown.
	 * @return array<string, list<string>>|null  referencing table name => list of referencing columns
	 */
	function getHasManyReference(string $table): ?array;

	/**
	 * Returns foreign key columns in the given table mapped to their referenced tables, or null if unknown.
	 * @return ?array<string, string>  local column name => referenced table name
	 */
	function getBelongsToReference(string $table): ?array;

	/**
	 * Rebuilds database structure cache.
	 */
	function rebuild(): void;

	/**
	 * Checks whether the structure has been rebuilt from the database during this request.
	 */
	function isRebuilt(): bool;
}
