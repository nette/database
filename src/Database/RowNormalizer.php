<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;


/**
 * Default implementation for row normalization.
 */
class RowNormalizer implements IRowNormalizer
{
	use Nette\SmartObject;

	/** @var ISupplementalDriver */
	private $supplementalDriver;


	/**
	 * @inheritdoc
	 */
	public function normalizeRow($row, ResultSet $resultSet)
	{
		foreach ($resultSet->getColumnTypes() as $key => $type) {
			$value = $row[$key];
			if ($value === NULL || $value === FALSE || $type === IStructure::FIELD_TEXT) {

			} elseif ($type === IStructure::FIELD_INTEGER) {
				$row[$key] = is_float($tmp = $value * 1) ? $value : $tmp;

			} elseif ($type === IStructure::FIELD_FLOAT) {
				if (($pos = strpos($value, '.')) !== FALSE) {
					$value = rtrim(rtrim($pos === 0 ? "0$value" : $value, '0'), '.');
				}
				$float = (float) $value;
				$row[$key] = (string) $float === $value ? $float : $value;

			} elseif ($type === IStructure::FIELD_BOOL) {
				$row[$key] = ((bool) $value) && $value !== 'f' && $value !== 'F';

			} elseif ($type === IStructure::FIELD_DATETIME || $type === IStructure::FIELD_DATE || $type === IStructure::FIELD_TIME) {
				$row[$key] = new Nette\Utils\DateTime($value);

			} elseif ($type === IStructure::FIELD_TIME_INTERVAL) {
				preg_match('#^(-?)(\d+)\D(\d+)\D(\d+)\z#', $value, $m);
				$row[$key] = new \DateInterval("PT$m[2]H$m[3]M$m[4]S");
				$row[$key]->invert = (int) (bool) $m[1];

			} elseif ($type === IStructure::FIELD_UNIX_TIMESTAMP) {
				$row[$key] = Nette\Utils\DateTime::from($value);
			}
		}

		if ($this->supplementalDriver === NULL) {
			$this->supplementalDriver = $resultSet->getConnection()->getSupplementalDriver();
		}

		return $this->supplementalDriver->normalizeRow($row);
	}
}
