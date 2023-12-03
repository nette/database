<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


/**
 * Normalizes fields in row.
 */
final class RowNormalizer
{
	private const TypePatterns = [
		'^_' => IStructure::FIELD_TEXT, // PostgreSQL arrays
		'(TINY|SMALL|SHORT|MEDIUM|BIG|LONG)(INT)?|INT(EGER|\d+| IDENTITY)?|(SMALL|BIG|)SERIAL\d*|COUNTER|YEAR|BYTE|LONGLONG|UNSIGNED BIG INT' => IStructure::FIELD_INTEGER,
		'(NEW)?DEC(IMAL)?(\(.*)?|NUMERIC|(SMALL)?MONEY|CURRENCY|NUMBER' => IStructure::FIELD_DECIMAL,
		'REAL|DOUBLE( PRECISION)?|FLOAT\d*' => IStructure::FIELD_FLOAT,
		'BOOL(EAN)?' => IStructure::FIELD_BOOL,
		'TIME' => IStructure::FIELD_TIME,
		'DATE' => IStructure::FIELD_DATE,
		'(SMALL)?DATETIME(OFFSET)?\d*|TIME(STAMP.*)?' => IStructure::FIELD_DATETIME,
		'BYTEA|(TINY|MEDIUM|LONG|)BLOB|(LONG )?(VAR)?BINARY|IMAGE' => IStructure::FIELD_BINARY,
	];


	private $skipped = [];


	/**
	 * Heuristic column type detection.
	 * @internal
	 */
	public static function detectType(string $type): string
	{
		static $cache;
		if (!isset($cache[$type])) {
			$cache[$type] = 'string';
			foreach (self::TypePatterns as $s => $val) {
				if (preg_match("#^($s)$#i", $type)) {
					return $cache[$type] = $val;
				}
			}
		}

		return $cache[$type];
	}


	public function skipNumeric(): static
	{
		$this->skipped[IStructure::FIELD_DECIMAL] = true;
		return $this;
	}


	public function skipDateTime(): static
	{
		$this->skipped[IStructure::FIELD_DATETIME] = true;
		$this->skipped[IStructure::FIELD_DATE] = true;
		$this->skipped[IStructure::FIELD_TIME] = true;
		$this->skipped[IStructure::FIELD_UNIX_TIMESTAMP] = true;
		return $this;
	}


	public function skipInterval(): static
	{
		$this->skipped[IStructure::FIELD_TIME_INTERVAL] = true;
		return $this;
	}


	public function __invoke(array $row, ResultSet $resultSet): array
	{
		foreach ($resultSet->getColumnTypes() as $key => $type) {
			if (!isset($this->skipped[$type])) {
				$row[$key] = $this->normalizeField($row[$key], $type);
			}
		}

		return $row;
	}


	private function normalizeField(mixed $value, string $type): mixed
	{
		if ($value === null || $value === false) {
			return $value;
		}

		switch ($type) {
			case IStructure::FIELD_INTEGER:
				return is_float($tmp = $value * 1) ? $value : $tmp;

			case IStructure::FIELD_FLOAT:
			case IStructure::FIELD_DECIMAL:
				if (is_string($value) && ($pos = strpos($value, '.')) !== false) {
					$value = rtrim(rtrim($pos === 0 ? "0$value" : $value, '0'), '.');
				}

				return (float) $value;

			case IStructure::FIELD_BOOL:
				return $value && $value !== 'f' && $value !== 'F';

			case IStructure::FIELD_DATETIME:
			case IStructure::FIELD_DATE:
				return str_starts_with($value, '0000-00')
					? null
					: new DateTime($value);

			case IStructure::FIELD_TIME:
				return (new DateTime($value))->setDate(1, 1, 1);

			case IStructure::FIELD_TIME_INTERVAL:
				preg_match('#^(-?)(\d+)\D(\d+)\D(\d+)(\.\d+)?$#D', $value, $m);
				$di = new \DateInterval("PT$m[2]H$m[3]M$m[4]S");
				$di->f = isset($m[5]) ? (float) $m[5] : 0.0;
				$di->invert = (int) (bool) $m[1];
				return $di;

			case IStructure::FIELD_UNIX_TIMESTAMP:
				return new DateTime($value);

			default:
				return $value;
		}
	}
}
