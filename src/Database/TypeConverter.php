<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


final class TypeConverter
{
	public static array $typePatterns = [
		'^_' => IStructure::FIELD_TEXT, // PostgreSQL arrays
		'(TINY|SMALL|SHORT|MEDIUM|BIG|LONG)(INT)?|INT(EGER|\d+| IDENTITY| UNSIGNED)?|(SMALL|BIG|)SERIAL\d*|COUNTER|YEAR|BYTE|LONGLONG|UNSIGNED BIG INT' => IStructure::FIELD_INTEGER,
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
	 * @return Type::*
	 */
	public static function detectType(string $type): string
	{
		static $cache;
		if (!isset($cache[$type])) {
			$cache[$type] = IStructure::FIELD_TEXT;
			foreach (self::$typePatterns as $s => $val) {
				if (preg_match("#^($s)$#i", $type)) {
					return $cache[$type] = $val;
				}
			}
		}

		return $cache[$type];
	}
}
