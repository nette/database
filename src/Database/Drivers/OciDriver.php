<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette;


/**
 * Supplemental Oracle database driver.
 */
class OciDriver implements Nette\Database\Driver
{
	use Nette\SmartObject;

	private Nette\Database\Connection $connection;
	private string $fmtDateTime;


	public function initialize(Nette\Database\Connection $connection, array $options): void
	{
		$this->connection = $connection;
		$this->fmtDateTime = $options['formatDateTime'] ?? 'U';
	}


	public function convertException(\PDOException $e): Nette\Database\DriverException
	{
		$code = $e->errorInfo[1] ?? null;
		if (in_array($code, [1, 2299, 38911], strict: true)) {
			return Nette\Database\UniqueConstraintViolationException::from($e);

		} elseif (in_array($code, [1400], strict: true)) {
			return Nette\Database\NotNullConstraintViolationException::from($e);

		} elseif (in_array($code, [2266, 2291, 2292], strict: true)) {
			return Nette\Database\ForeignKeyConstraintViolationException::from($e);

		} else {
			return Nette\Database\DriverException::from($e);
		}
	}


	/********************* SQL ****************d*g**/


	public function delimite(string $name): string
	{
		// @see http://download.oracle.com/docs/cd/B10500_01/server.920/a96540/sql_elements9a.htm
		return '"' . str_replace('"', '""', $name) . '"';
	}


	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format($this->fmtDateTime);
	}


	public function formatDateInterval(\DateInterval $value): string
	{
		throw new Nette\NotSupportedException;
	}


	public function formatLike(string $value, int $pos): string
	{
		throw new Nette\NotImplementedException;
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
		foreach ($this->connection->query('SELECT * FROM cat') as $row) {
			if ($row[1] === 'TABLE' || $row[1] === 'VIEW') {
				$tables[] = [
					'name' => $row[0],
					'view' => $row[1] === 'VIEW',
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


	public function getColumnTypes(\PDOStatement $statement): array
	{
		return [];
	}


	public function isSupported(string $item): bool
	{
		return $item === self::SUPPORT_SEQUENCE || $item === self::SUPPORT_SUBSELECT;
	}
}
