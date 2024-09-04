<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


/**
 * SQL literal that will not be escaped.
 */
class SqlLiteral
{
	public function __construct(
		private readonly string $value,
		private readonly array $parameters = [],
	) {
	}


	public function getSql(): string
	{
		return $this->value;
	}


	public function getParameters(): array
	{
		return $this->parameters;
	}


	public function __toString(): string
	{
		return $this->value;
	}
}
