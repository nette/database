<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
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
	 * @returns self
	 */
	public static function from(\PDOException $src)
	{
		$e = new static($src->message, NULL, $src);
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
	 * @returns int|string|NULL  Driver-specific error code
	 */
	public function getDriverCode()
	{
		return isset($this->errorInfo[1]) ? $this->errorInfo[1] : NULL;
	}


	/**
	 * @returns string|NULL  SQLSTATE error code
	 */
	public function getSqlState()
	{
		return isset($this->errorInfo[0]) ? $this->errorInfo[0] : NULL;
	}


	/**
	 * @returns string|NULL  SQL command
	 */
	public function getQueryString()
	{
		return $this->queryString;
	}

}
