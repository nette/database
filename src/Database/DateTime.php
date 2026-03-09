<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;


/**
 * Immutable date-time value with JSON and string serialization support.
 */
final class DateTime extends \DateTimeImmutable implements \JsonSerializable
{
	/**
	 * Returns JSON representation in ISO 8601 (used by JavaScript).
	 */
	public function jsonSerialize(): string
	{
		return $this->format('c');
	}


	/**
	 * Returns the date and time in the format 'Y-m-d H:i:s.u'.
	 */
	public function __toString(): string
	{
		return $this->format('Y-m-d H:i:s.u');
	}
}
