<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Reflection;

use Nette;


/**
 * Reflection of database table or view.
 */
final class Table
{
	use Nette\SmartObject;

	public function __construct(
		public string $name,
		public bool $view = false,
		public ?string $fullName = null,
	) {
	}
}
