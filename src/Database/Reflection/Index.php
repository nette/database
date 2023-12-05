<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Reflection;


/**
 * Index reflection.
 */
final class Index
{
	/** @internal */
	public function __construct(
		/** @var Column[] */
		public readonly array $columns,
		public readonly bool $unique = false,
		public readonly bool $primary = false,
		public readonly ?string $name = null,
	) {
	}


	public function __toString(): string
	{
		return (string) $this->name;
	}
}
