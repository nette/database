<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Reflection;


/**
 * Database foreign key relationship.
 */
final class ForeignKey
{
	/** @internal */
	public function __construct(
		public readonly Table $foreignTable,
		/** @var list<Column> */
		public readonly array $localColumns,
		/** @var list<Column> */
		public readonly array $foreignColumns,
		public readonly string $name,
	) {
	}


	public function __toString(): string
	{
		return $this->name;
	}
}
