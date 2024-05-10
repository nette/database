<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;



/**
 * Column types
 */
final class Type
{
	public const
		Binary = 'bin',
		Bool = 'bool',
		Date = 'date',
		DateTime = 'datetime',
		Decimal = 'decimal',
		Float = 'float',
		Integer = 'int',
		Text = 'string',
		Time = 'time',
		TimeInterval = 'timeint',
		UnixTimestamp = 'timestamp';
}
