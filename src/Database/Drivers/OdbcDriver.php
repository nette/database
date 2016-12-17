<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette;


/**
 * Supplemental ODBC database driver.
 */
class OdbcDriver implements Nette\Database\ISupplementalDriver
{
	use Nette\SmartObject;

	public function initialize(Nette\Database\Connection $connection, array $options): void
	{
	}


	public function convertException(\PDOException $e): Nette\Database\DriverException
	{
		return Nette\Database\DriverException::from($e);
	}


	/********************* SQL ****************d*g**/


	/**
	 * Delimites identifier for use in a SQL statement.
	 */
	public function delimite(string $name): string
	{
		return '[' . str_replace(['[', ']'], ['[[', ']]'], $name) . ']';
	}


	/**
	 * Formats date-time for use in a SQL statement.
	 */
	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format('#m/d/Y H:i:s#');
	}


	/**
	 * Formats date-time interval for use in a SQL statement.
	 */
	public function formatDateInterval(\DateInterval $value): string
	{
		throw new Nette\NotSupportedException;
	}


	/**
	 * Encodes string for use in a LIKE statement.
	 */
	public function formatLike(string $value, int $pos): string
	{
		$value = strtr($value, ["'" => "''", '%' => '[%]', '_' => '[_]', '[' => '[[]']);
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}


	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 */
	public function applyLimit(string &$sql, ?int $limit, ?int $offset): void
	{
		if ($offset) {
			throw new Nette\NotSupportedException('Offset is not supported by this database.');

		} elseif ($limit < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');

		} elseif ($limit !== NULL) {
			$sql = preg_replace('#^\s*(SELECT(\s+DISTINCT|\s+ALL)?|UPDATE|DELETE)#i', '$0 TOP ' . $limit, $sql, 1, $count);
			if (!$count) {
				throw new Nette\InvalidArgumentException('SQL query must begin with SELECT, UPDATE or DELETE command.');
			}
		}
	}


	/**
	 * Normalizes result row.
	 */
	public function normalizeRow(array $row): array
	{
		return $row;
	}


	/********************* reflection ****************d*g**/


	/**
	 * Returns list of tables.
	 */
	public function getTables(): array
	{
		throw new Nette\NotImplementedException;
	}


	/**
	 * Returns metadata for all columns in a table.
	 */
	public function getColumns(string $table): array
	{
		throw new Nette\NotImplementedException;
	}


	/**
	 * Returns metadata for all indexes in a table.
	 */
	public function getIndexes(string $table): array
	{
		throw new Nette\NotImplementedException;
	}


	/**
	 * Returns metadata for all foreign keys in a table.
	 */
	public function getForeignKeys(string $table): array
	{
		throw new Nette\NotImplementedException;
	}


	/**
	 * Returns associative array of detected types (IReflection::FIELD_*) in result set.
	 */
	public function getColumnTypes(\PDOStatement $statement): array
	{
		return Nette\Database\Helpers::detectTypes($statement);
	}


	public function isSupported(string $item): bool
	{
		return $item === self::SUPPORT_SUBSELECT;
	}

}
