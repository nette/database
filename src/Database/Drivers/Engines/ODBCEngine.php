<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\Engines;

use Nette;
use Nette\Database\Drivers\Engine;
use Nette\Database\TypeConverter;
use function preg_replace, str_replace;


/**
 * Microsoft ODBC database platform.
 */
class ODBCEngine implements Engine
{
	public function isSupported(string $feature): bool
	{
		return false;
	}


	public function classifyException(Nette\Database\DriverException $e): ?string
	{
		return null;
	}


	/********************* SQL ****************d*g**/


	public function delimit(string $name): string
	{
		return '[' . str_replace(['[', ']'], ['[[', ']]'], $name) . ']';
	}


	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format('#m/d/Y H:i:s#');
	}


	public function formatDateInterval(\DateInterval $value): string
	{
		throw new Nette\NotSupportedException;
	}


	public function applyLimit(string $sql, ?int $limit, ?int $offset): string
	{
		if ($offset) {
			throw new Nette\NotSupportedException('Offset is not supported by this database.');

		} elseif ($limit < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');

		} elseif ($limit !== null) {
			$sql = preg_replace('#^\s*(SELECT(\s+DISTINCT|\s+ALL)?|UPDATE|DELETE)#i', '$0 TOP ' . $limit, $sql, 1, $count);
			if (!$count) {
				throw new Nette\InvalidArgumentException('SQL query must begin with SELECT, UPDATE or DELETE command.');
			}
		}

		return $sql;
	}


	/********************* reflection ****************d*g**/


	public function getTables(): array
	{
		throw new Nette\NotImplementedException;
	}


	public function getColumns(string $table): array
	{
		throw new Nette\NotImplementedException;
	}


	public function getIndexes(string $table): array
	{
		throw new Nette\NotImplementedException;
	}


	public function getForeignKeys(string $table): array
	{
		throw new Nette\NotImplementedException;
	}


	public function convertToPhp(mixed $value, array $meta, TypeConverter $converter): mixed
	{
		return $converter->convertToPhp($value, $meta);
	}
}
