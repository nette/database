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
	use Nette\SmartObject;

	public function __invoke(array $row, ResultSet $resultSet): array
	{
		foreach ($resultSet->getColumnTypes() as $key => $type) {
			$row[$key] = $this->normalizeField($row[$key], $type);
		}

		return $row;
	}


	public function normalizeField(mixed $value, string $type): mixed
	{
		if ($value === null || $value === false) {
			return $value;
		}

		switch ($type) {
			case IStructure::FIELD_INTEGER:
				return is_float($tmp = $value * 1) ? $value : $tmp;

			case IStructure::FIELD_FLOAT:
			case IStructure::FIELD_FIXED:
				if (is_string($value) && ($pos = strpos($value, '.')) !== false) {
					$value = rtrim(rtrim($pos === 0 ? "0$value" : $value, '0'), '.');
				}

				return (float) $value;

			case IStructure::FIELD_BOOL:
				return $value && $value !== 'f' && $value !== 'F';

			case IStructure::FIELD_DATETIME:
			case IStructure::FIELD_DATE:
			case IStructure::FIELD_TIME:
				return $value && !str_starts_with((string) $value, '0000-00')
					? new DateTime($value)
					: null;

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
