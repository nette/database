<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette;


/**
 * Date Time.
 */
final class DateTime extends \DateTimeImmutable implements \JsonSerializable
{
	use Nette\SmartObject;

	public function __construct(string|int $time = 'now')
	{
		if (is_numeric($time)) {
			$time = (new self('@' . $time))
				->setTimezone(new \DateTimeZone(date_default_timezone_get()))
				->format('Y-m-d H:i:s.u');
		}
		parent::__construct($time);
	}


	/**
	 * Returns JSON representation in ISO 8601 (used by JavaScript).
	 */
	public function jsonSerialize(): string
	{
		return $this->format('c');
	}


	/**
	 * Returns the date and time in the format 'Y-m-d H:i:s'.
	 */
	public function __toString(): string
	{
		return $this->format('Y-m-d H:i:s');
	}
}
