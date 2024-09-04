<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


final class TypeConverter
{
	private const
		Binary = 1,
		Boolean = 2,
		Date = 3,
		DateTime = 4,
		Decimal = 5,
		Float = 6,
		Integer = 7,
		Interval = 8,
		Text = 9,
		Time = 10;

	private const Patterns = [
		'^_' => self::Text, // PostgreSQL arrays
		'(TINY|SMALL|SHORT|MEDIUM|BIG|LONG)(INT)?|INT(EGER|\d+| IDENTITY| UNSIGNED)?|(SMALL|BIG|)SERIAL\d*|COUNTER|YEAR|BYTE|LONGLONG|UNSIGNED BIG INT' => self::Integer,
		'(NEW)?DEC(IMAL)?(\(.*)?|NUMERIC|(SMALL)?MONEY|CURRENCY|NUMBER' => self::Decimal,
		'REAL|DOUBLE( PRECISION)?|FLOAT\d*' => self::Float,
		'BOOL(EAN)?' => self::Boolean,
		'TIME' => self::Time,
		'DATE' => self::Date,
		'(SMALL)?DATETIME(OFFSET)?\d*|TIME(STAMP.*)?' => self::DateTime,
		'BYTEA|(TINY|MEDIUM|LONG|)BLOB|(LONG )?(VAR)?BINARY|IMAGE' => self::Binary,
	];

	public bool $convertBoolean = true;
	public bool $newDateTime = true;


	/**
	 * Heuristic column type detection.
	 */
	private function detectType(string $nativeType): int
	{
		static $cache;
		if (!isset($cache[$nativeType])) {
			$cache[$nativeType] = self::Text;
			foreach (self::Patterns as $s => $val) {
				if (preg_match("#^($s)$#i", $nativeType)) {
					return $cache[$nativeType] = $val;
				}
			}
		}

		return $cache[$nativeType];
	}


	public function convertToPhp(mixed $value, array $meta): mixed
	{
		return match ($this->detectType($meta['nativeType'])) {
			self::Integer => $this->toInt($value),
			self::Float,
			self::Decimal => $this->toFloat($value),
			self::Boolean => $this->convertBoolean ? $this->toBool($value) : $value,
			self::DateTime, self::Date => $this->toDateTime($value),
			self::Time => $this->toTime($value),
			self::Interval => self::toInterval($value),
			default => $value,
		};
	}


	public function toInt(int|string $value): int|float
	{
		return is_float($tmp = $value * 1) ? $value : $tmp;
	}


	public function toFloat(float|string $value): float
	{
		return (float) $value;
	}


	public function toBool(bool|int|string $value): bool
	{
		return (bool) $value;
	}


	public function toDateTime(string $value): \DateTimeInterface
	{
		return $this->newDateTime ? new DateTime($value) : new \Nette\Utils\DateTime($value);
	}


	public function toTime(string $value): \DateTimeInterface
	{
		return $this->toDateTime($value)->setDate(1, 1, 1);
	}


	public function toInterval(string $value): \DateInterval
	{
		preg_match('#^(-?)(\d+)\D(\d+)\D(\d+)(\.\d+)?$#D', $value, $m);
		$interval = new \DateInterval("PT$m[2]H$m[3]M$m[4]S");
		$interval->f = isset($m[5]) ? (float) $m[5] : 0.0;
		$interval->invert = (int) (bool) $m[1];
		return $interval;
	}
}
