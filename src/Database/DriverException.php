<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use function array_slice;


/**
 * Base class for all errors in the driver or SQL server.
 */
class DriverException extends \PDOException
{
	public ?string $queryString = null;

	/** @var array<mixed>|null */
	public ?array $params = null;


	/**
	 * Creates a DriverException from a PDOException, preserving error info and stack trace location.
	 */
	public static function from(\PDOException $src): static
	{
		$e = new static($src->message, 0, $src);
		$e->file = $src->file;
		$e->line = $src->line;
		if (!$src->errorInfo && preg_match('#SQLSTATE\[(.*?)\] \[(.*?)\] (.*)#A', $src->message, $m)) {
			$m[2] = (int) $m[2];
			$e->errorInfo = array_slice($m, 1);
			$e->code = $m[1];
		} else {
			$e->errorInfo = $src->errorInfo;
			$e->code = $src->code;
			$e->code = $e->errorInfo[0] ?? $src->code;
		}

		return $e;
	}


	/**
	 * Returns the driver-specific error code, or null if not available.
	 */
	public function getDriverCode(): int|string|null
	{
		return $this->errorInfo[1] ?? null;
	}


	/**
	 * Returns the SQLSTATE error code, or null if not available.
	 */
	public function getSqlState(): ?string
	{
		return $this->errorInfo[0] ?? null;
	}


	public function getQueryString(): ?string
	{
		return $this->queryString;
	}


	/** @return array<mixed>|null */
	public function getParameters(): ?array
	{
		return $this->params;
	}
}
