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
			$row[$key] = $this->normalizeField($row[$key], $type);
		}

		return $row;
	}


	private function normalizeField(mixed $value, string $type): mixed
	{
		if ($value === null || $value === false) {
			return $value;
		}

		switch ($type) {
			case Type::Integer:
				return is_float($tmp = $value * 1) ? $value : $tmp;

			case Type::Float:
			case Type::Decimal:
				if (is_string($value) && ($pos = strpos($value, '.')) !== false) {
					$value = rtrim(rtrim($pos === 0 ? "0$value" : $value, '0'), '.');
				}

				return (float) $value;

			case Type::Bool:
				return $value && $value !== 'f' && $value !== 'F';

			case Type::DateTime:
			case Type::Date:
				return str_starts_with($value, '0000-00')
					? null
					: new DateTime($value);

			case Type::Time:
				return (new DateTime($value))->setDate(1, 1, 1);

			case Type::TimeInterval:
				preg_match('#^(-?)(\d+)\D(\d+)\D(\d+)(\.\d+)?$#D', $value, $m);
				$di = new \DateInterval("PT$m[2]H$m[3]M$m[4]S");
				$di->f = isset($m[5]) ? (float) $m[5] : 0.0;
				$di->invert = (int) (bool) $m[1];
				return $di;

			case Type::UnixTimestamp:
				return (new DateTime)->setTimestamp($value);

			default:
				return $value;
		}
	}
}
