<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Reflection;


/**
 * Database table column metadata.
 */
final class Column
{
	/** @internal */
	public function __construct(
		public readonly string $name,
		public readonly ?Table $table = null,
		public readonly string $nativeType = '',
		public readonly ?int $size = null,
		public readonly bool $nullable = false,
		public readonly mixed $default = null,
		public readonly bool $autoIncrement = false,
		public readonly bool $primary = false,
		public readonly array $vendor = [],
	) {
	}


	public function __toString(): string
	{
		return $this->name;
	}
}
