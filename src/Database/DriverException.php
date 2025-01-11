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
	public static function from(self $e): static
	{
		return new static($e->getMessage(), $e->sqlState, $e->getDriverCode() ?? 0, $e->query, $e);
	}


	public function __construct(
		string $message,
		private readonly ?string $sqlState = null,
		private int $driverCode = 0,
		private readonly ?SqlLiteral $query = null,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
		$this->code = $sqlState ?: null;
	}


	public function getDriverCode(): int|string|null
	{
		return $this->driverCode ?: null;
	}


	public function getSqlState(): ?string
	{
		return $this->sqlState;
	}


	public function getQueryString(): ?string
	{
		return $this->query?->getSql();
	}


	public function getParameters(): ?array
	{
		return $this->query?->getParameters();
	}
}
