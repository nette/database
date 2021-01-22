<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

if (false) {
	/** @deprecated use Nette\Database\Driver */
	interface ISupplementalDriver extends Driver
	{
	}
	/** @deprecated use Nette\Database\Conventions */
	interface IConventions extends Conventions
	{
	}
} elseif (!interface_exists(ISupplementalDriver::class)) {
	class_alias(Driver::class, ISupplementalDriver::class);
	class_alias(Conventions::class, IConventions::class);
}
