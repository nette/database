<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Reflection;


/**
 * Database foreign key relationship.
 */
final class ForeignKey
{
	/** @internal */
	public function __construct(
		public readonly Table $foreignTable,
		/** @var Column[] */
		public readonly array $localColumns,
		/** @var Column[] */
		public readonly array $foreignColumns,
		public readonly ?string $name = null,
	) {
	}


	public function __toString(): string
	{
		return (string) $this->name;
	}
}
