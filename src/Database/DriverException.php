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
class DriverException extends \PDOException
{
	/** @var string */
	public $queryString;

	/** @var array */
	public $params;


	/**
	 * @return static
	 */
	public static function from(\PDOException $src)
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
	 * @return int|string|null  Driver-specific error code
	 */
	public function getDriverCode()
	{
		return $this->errorInfo[1] ?? null;
	}


	public function getSqlState(): ?string
	{
		return $this->errorInfo[0] ?? null;
	}


	public function getQueryString(): ?string
	{
		return $this->queryString;
	}


	public function getParameters(): ?array
	{
		return $this->params;
	}
}
