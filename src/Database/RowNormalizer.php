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
			$value = $row[$key];
			if ($value === null || $value === false || $type === IStructure::FIELD_TEXT) {
				// do nothing
			} elseif ($type === IStructure::FIELD_INTEGER) {
				$row[$key] = is_float($tmp = $value * 1) ? $value : $tmp;

			} elseif ($type === IStructure::FIELD_FLOAT) {
				if (is_string($value) && ($pos = strpos($value, '.')) !== false) {
					$value = rtrim(rtrim($pos === 0 ? "0$value" : $value, '0'), '.');
				}

				$row[$key] = (float) $value;

			} elseif ($type === IStructure::FIELD_BOOL) {
				$row[$key] = ((bool) $value) && $value !== 'f' && $value !== 'F';

			} elseif (
				$type === IStructure::FIELD_DATETIME
				|| $type === IStructure::FIELD_DATE
				|| $type === IStructure::FIELD_TIME
			) {
				$row[$key] = new Nette\Utils\DateTime($value);

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
