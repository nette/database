<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Table;


class ReferenceCache
{
	/** @var array */
	protected $referencingPrototype = [];

	/** @var array */
	protected $referencing = [];

	/** @var array */
	protected $referenced = [];


	public function &getReferencingPrototype(string $specificCacheKey, string $table, string $column)
	{
		return $this->referencingPrototype[$specificCacheKey]["$table.$column"];
	}


	public function clearReferencingPrototype(): void
	{
		$this->referencingPrototype = [];
	}


	public function &getReferencing(string $generalCacheKey): ?array
	{
		return $this->referencing[$generalCacheKey];
	}


	public function unsetReferencing(string $generalCacheKey, string $specificCacheKey): void
	{
		unset($this->referencing[$generalCacheKey][$specificCacheKey]);
	}


	public function &getReferenced(string $specificCacheKey, string $table, string $column): ?array
	{
		return $this->referenced[$specificCacheKey]["$table.$column"];
	}


	public function clearReferenced(): void
	{
		$this->referenced = [];
	}
}
