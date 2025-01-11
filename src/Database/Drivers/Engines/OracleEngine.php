<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\Engines;

use Nette;
use Nette\Database\Drivers\Connection;
use Nette\Database\Drivers\Engine;
use Nette\Database\TypeConverter;
use function in_array, str_replace;


/**
 * Oracle database platform.
 */
class OracleEngine implements Engine
{
	public string $formatDateTime = 'U';


	public function __construct(
		private readonly Connection $connection,
	) {
	}


	public function isSupported(string $feature): bool
	{
		return $feature === self::SupportSequence;
	}


	public function classifyException(Nette\Database\DriverException $e): ?string
	{
		$code = $e->getDriverCode();
		return match (true) {
			in_array($code, [1, 2299, 38911], strict: true) => Nette\Database\UniqueConstraintViolationException::class,
			in_array($code, [1400], strict: true) => Nette\Database\NotNullConstraintViolationException::class,
			in_array($code, [2266, 2291, 2292], strict: true) => Nette\Database\ForeignKeyConstraintViolationException::class,
			default => null,
		};
	}


	/********************* SQL ****************d*g**/


	public function delimite(string $name): string
	{
		// @see http://download.oracle.com/docs/cd/B10500_01/server.920/a96540/sql_elements9a.htm
		return '"' . str_replace('"', '""', $name) . '"';
	}


	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format($this->formatDateTime);
	}


	public function formatDateInterval(\DateInterval $value): string
	{
		throw new Nette\NotSupportedException;
	}


	public function applyLimit(string &$sql, ?int $limit, ?int $offset): void
	{
		if ($limit < 0 || $offset < 0) {
			throw new Nette\InvalidArgumentException('Negative offset or limit.');

		} elseif ($offset) {
			// see http://www.oracle.com/technology/oramag/oracle/06-sep/o56asktom.html
			$sql = 'SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (' . $sql . ') t '
				. ($limit !== null ? 'WHERE ROWNUM <= ' . ($offset + $limit) : '')
				. ') WHERE "__rnum" > ' . $offset;

		} elseif ($limit !== null) {
			$sql = 'SELECT * FROM (' . $sql . ') WHERE ROWNUM <= ' . $limit;
		}
	}


	/********************* reflection ****************d*g**/


	public function getTables(): array
	{
		$tables = [];
		$rows = $this->connection->query('SELECT * FROM cat');
		while ($row = $rows->fetch()) {
			$row = array_values($row);
			if ($row[1] === 'TABLE' || $row[1] === 'VIEW') {
				$tables[] = [
					'name' => $row[0],
					'view' => $row[1] === 'VIEW',
					'comment' => null,
				];
			}
		}

		return $tables;
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
