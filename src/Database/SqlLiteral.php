<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

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


	public function __construct($value, array $parameters = [])
	{
		$this->value = (string) $value;
		$this->parameters = $parameters;
	}


	/**
	 * @return array
	 */
	public function getParameters()
	{
		return $this->parameters;
	}


	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->value;
	}
}
