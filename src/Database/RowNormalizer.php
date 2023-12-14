<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette;


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


	public function __invoke(array $row, ResultSet $resultSet): array
	{
		foreach ($resultSet->getColumnTypes() as $key => $type) {
			$value = $row[$key];
			if ($value === null || $value === false || $type === IStructure::FIELD_TEXT) {
				// do nothing
			} elseif ($type === IStructure::FIELD_FLOAT || $type === IStructure::FIELD_DECIMAL) {
				$row[$key] = is_float($tmp = $value * 1) ? $value : $tmp;

			} elseif ($type === IStructure::FIELD_FLOAT) {
				if (is_string($value) && ($pos = strpos($value, '.')) !== false) {
					$value = rtrim(rtrim($pos === 0 ? "0$value" : $value, '0'), '.');
				}

				$row[$key] = (float) $value;

			} elseif ($type === IStructure::FIELD_BOOL) {
				$row[$key] = $value && $value !== 'f' && $value !== 'F';

			} elseif ($type === IStructure::FIELD_DATETIME || $type === IStructure::FIELD_DATE) {
				$row[$key] = str_starts_with($value, '0000-00')
					? null
					: new Nette\Utils\DateTime($value);

			} elseif ($type === IStructure::FIELD_TIME) {
				$row[$key] = (new Nette\Utils\DateTime($value))->setDate(1, 1, 1);

			} elseif ($type === IStructure::FIELD_TIME_INTERVAL) {
				preg_match('#^(-?)(\d+)\D(\d+)\D(\d+)(\.\d+)?$#D', $value, $m);
				$row[$key] = new \DateInterval("PT$m[2]H$m[3]M$m[4]S");
				$row[$key]->f = isset($m[5]) ? (float) $m[5] : 0.0;
				$row[$key]->invert = (int) (bool) $m[1];

			} elseif ($type === IStructure::FIELD_UNIX_TIMESTAMP) {
				$row[$key] = Nette\Utils\DateTime::from($value);
			}
		}

		return $row;
	}
}
