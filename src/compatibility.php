<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

if (false) {
	/** @deprecated use Nette\Database\Explorer */
	class Context
	{
	}

	class Context
	{
	}
} elseif (!class_exists(Context::class)) {
	class_alias(Explorer::class, Context::class);
}
