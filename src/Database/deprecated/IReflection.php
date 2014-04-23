<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;


/** @deprecated */
interface IReflection extends Nette\Database\IConventions
{
	/** @deprecated */
	const
		FIELD_TEXT = 'string',
		FIELD_BINARY = 'bin',
		FIELD_BOOL = 'bool',
		FIELD_INTEGER = 'int',
		FIELD_FLOAT = 'float',
		FIELD_DATE = 'date',
		FIELD_TIME = 'time',
		FIELD_DATETIME = 'datetime',
		FIELD_UNIX_TIMESTAMP = 'timestamp',
		FIELD_TIME_INTERVAL = 'timeint';

}
