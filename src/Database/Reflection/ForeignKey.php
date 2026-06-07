<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Reflection;


/**
 * Database foreign key relationship.
 */
final readonly class ForeignKey
{
	/** @internal */
	public function __construct(
		public Table $foreignTable,
		/** @var list<Column> */
		public array $localColumns,
		/** @var list<Column> */
		public array $foreignColumns,
		public string $name,
	) {
	}


	public function __toString(): string
	{
		return $this->name;
	}
}
