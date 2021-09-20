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
	private int|string|null $driverCode = null;
	private string|null $sqlState = null;


	public function __construct(string $message = '', $code = 0, \Throwable $previous = null)
	{
		parent::__construct($message, 0, $previous);
		$this->code = $code;
		if ($previous) {
			$this->file = $previous->file;
			$this->line = $previous->line;
		}
	}


	/** @internal */
	public function setDriverCode(string $state, int|string $code): void
	{
		$this->sqlState = $state;
		$this->driverCode = $code;
	}


	public function getDriverCode(): int|string|null
	{
		return $this->driverCode;
	}


	public function getSqlState(): ?string
	{
		return $this->sqlState;
	}
}
