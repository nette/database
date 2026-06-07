<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Reflection;


/**
 * Database table index.
 */
final readonly class Index
{
	/** @internal */
	public function __construct(
		/** @var list<Column> */
		public array $columns,
		public bool $unique = false,
		public bool $primary = false,
		public ?string $name = null,
	) {
	}


	public function __toString(): string
	{
		return (string) $this->name;
	}
}
