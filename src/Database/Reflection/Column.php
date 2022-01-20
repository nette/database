<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Reflection;

use Nette;


/**
 * Reflection of table or result set column.
 */
final class Column
{
	use Nette\SmartObject;

	public function __construct(
		public string $name,
		public string $nativeType = '',
		public ?string $table = null,
		public ?int $size = null,
		public bool $nullable = false,
		public mixed $default = null,
		public bool $autoIncrement = false,
		public bool $primary = false,
		public array $vendor = [],
	) {
	}
}
