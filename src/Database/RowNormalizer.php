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
		'^_' => Type::Text, // PostgreSQL arrays
		'(TINY|SMALL|SHORT|MEDIUM|BIG|LONG)(INT)?|INT(EGER|\d+| IDENTITY| UNSIGNED)?|(SMALL|BIG|)SERIAL\d*|COUNTER|YEAR|BYTE|LONGLONG|UNSIGNED BIG INT' => Type::Integer,
		'(NEW)?DEC(IMAL)?(\(.*)?|NUMERIC|(SMALL)?MONEY|CURRENCY|NUMBER' => Type::Decimal,
		'REAL|DOUBLE( PRECISION)?|FLOAT\d*' => Type::Float,
		'BOOL(EAN)?' => Type::Bool,
		'TIME' => Type::Time,
		'DATE' => Type::Date,
		'(SMALL)?DATETIME(OFFSET)?\d*|TIME(STAMP.*)?' => Type::DateTime,
		'BYTEA|(TINY|MEDIUM|LONG|)BLOB|(LONG )?(VAR)?BINARY|IMAGE' => Type::Binary,
	];


	/**
	 * Heuristic column type detection.
	 * @return Type::*
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
					: new DateTime($value);

			} elseif ($type === IStructure::FIELD_TIME) {
				$row[$key] = (new DateTime($value))->setDate(1, 1, 1);

			} elseif ($type === IStructure::FIELD_TIME_INTERVAL) {
				preg_match('#^(-?)(\d+)\D(\d+)\D(\d+)(\.\d+)?$#D', $value, $m);
				$row[$key] = new \DateInterval("PT$m[2]H$m[3]M$m[4]S");
				$row[$key]->f = isset($m[5]) ? (float) $m[5] : 0.0;
				$row[$key]->invert = (int) (bool) $m[1];

			} elseif ($type === IStructure::FIELD_UNIX_TIMESTAMP) {
				$row[$key] = (new DateTime)->setTimestamp($value);
			}
		}

		return $row;
	}
}
