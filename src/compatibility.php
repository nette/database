<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

if (false) {
	/** @deprecated use Nette\Database\Explorer */
	class Context extends Explorer
	{
	}
} elseif (!class_exists(Context::class)) {
	class_alias(Explorer::class, Context::class);
}

if (false) {
	/** @deprecated use Nette\Database\Result */
	class ResultSet extends Result
	{
	}
} elseif (!class_exists(ResultSet::class)) {
	class_alias(Result::class, ResultSet::class);
}

if (false) {
	class Connection extends Explorer
	{
	}
} elseif (!class_exists(Connection::class)) {
	class_alias(Explorer::class, Connection::class);
}
