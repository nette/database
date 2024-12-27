<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

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
	 * Returns tables list.
	 */
	function getTables(): array;

	/**
	 * Returns table columns list.
	 */
	function getColumns(string $table): array;

	/**
	 * Returns table primary key.
	 * @return string|string[]|null
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
	 * Returns hasMany reference.
	 * If a targetTable is not provided, returns references for all tables.
	 */
	function getHasManyReference(string $table): ?array;

	/**
	 * Returns belongsTo reference.
	 * If a column is not provided, returns references for all columns.
	 */
	function getBelongsToReference(string $table): ?array;

	/**
	 * Rebuilds database structure cache.
	 */
	function rebuild(): void;

	/**
	 * Returns true if database cached structure has been rebuilt.
	 */
	function isRebuilt(): bool;
}
