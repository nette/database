<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;


/**
 * Base class for all errors in the driver or SQL server.
 */
class DriverException extends \PDOException
{
	/** @var string */
	public $queryString;


	/**
	 * @return static
	 */
	public static function from(\PDOException $src)
	{
		$e = new static($src->message, null, $src);
		if (!$src->errorInfo && preg_match('#SQLSTATE\[(.*?)\] \[(.*?)\] (.*)#A', $src->message, $m)) {
			$m[2] = (int) $m[2];
			$e->errorInfo = array_slice($m, 1);
			$e->code = $m[1];
		} else {
			$e->errorInfo = $src->errorInfo;
			$e->code = $src->code;
		}
		return $e;
	}


	/**
	 * @return int|string|null  Driver-specific error code
	 */
	public function getDriverCode()
	{
		return isset($this->errorInfo[1]) ? $this->errorInfo[1] : null;
	}


	/**
	 * @return string|null  SQLSTATE error code
	 */
	public function getSqlState()
	{
		return isset($this->errorInfo[0]) ? $this->errorInfo[0] : null;
	}


	/**
	 * @return string|null  SQL command
	 */
	public function getQueryString()
	{
		return $this->queryString;
	}
}
