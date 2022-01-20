<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Reflection;

use Nette;


/**
 * Reflection or index.
 */
final class Index
{
	use Nette\SmartObject;

	public function __construct(
		public string $name,
		public array $columns,
		public bool $unique = false,
		public bool $primary = false,
	) {
	}
}
