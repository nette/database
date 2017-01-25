<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette;


/**
 * SQL literal value.
 */
class SqlLiteral
{
	use Nette\SmartObject;

	/** @var string */
	private $value;

	/** @var array */
	private $parameters;


	public function __construct(string $value, array $parameters = [])
	{
		$this->value = (string) $value;
		$this->parameters = $parameters;
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
