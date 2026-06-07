<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Reflection;


/**
 * Database table column metadata.
 */
final readonly class Column
{
	/** @internal */
	public function __construct(
		public string $name,
		public ?Table $table = null,
		public string $nativeType = '',
		public ?int $size = null,
		public bool $nullable = false,
		public mixed $default = null,
		public bool $autoIncrement = false,
		public bool $primary = false,
		public ?string $comment = null,
		/** @var array<string, mixed> */
		public array $vendor = [],
	) {
	}


	public function __toString(): string
	{
		return $this->name;
	}
}
