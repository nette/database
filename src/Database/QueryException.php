<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


class QueryException extends DriverException
{
	public string $queryString;
	private array $params;


	/** @internal */
	public function setQueryString(string $queryString, array $params): void
	{
		$this->queryString = $queryString;
		$this->params = $params;
	}


	public function getQueryString(): string
	{
		return $this->queryString;
	}


	public function getParameters(): array
	{
		return $this->params;
	}
}
