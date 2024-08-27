<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


/**
 * Base class for all errors in the driver or SQL server.
 */
class DriverException extends \Exception
{
	public function __construct(
		string $message,
		private readonly ?string $sqlState = null,
		int $code = 0,
		private readonly ?SqlLiteral $query = null,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, $code, $previous);
	}


	/** @deprecated use getCode() */
	public function getDriverCode(): int
	{
		return $this->getCode();
	}


	public function getSqlState(): ?string
	{
		return $this->sqlState;
	}


	public function getQuery(): ?SqlLiteral
	{
		return $this->query;
	}


	/** @deprecated use getQuery()->getSql() */
	public function getQueryString(): ?string
	{
		return $this->query?->getSql();
	}


	/** @deprecated use getQuery()->getParameters() */
	public function getParameters(): ?array
	{
		return $this->query?->getParameters();
	}
}
