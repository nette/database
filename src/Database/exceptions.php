<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


/**
 * Server connection related errors.
 */
class ConnectionException extends DriverException
{
}


/**
 * Base class for all constraint violation related exceptions.
 */
class ConstraintViolationException extends DriverException
{
}


/**
 * Exception for a foreign key constraint violation.
 */
class ForeignKeyConstraintViolationException extends ConstraintViolationException
{
}


/**
 * Exception for a NOT NULL constraint violation.
 */
class NotNullConstraintViolationException extends ConstraintViolationException
{
}


/**
 * Exception for a unique constraint violation.
 */
class UniqueConstraintViolationException extends ConstraintViolationException
{
	/** @var string */
	private $entry;

	/** @var string */
	private $key;


	/**
	 * @returns self
	 */
	public static function from(\PDOException $src)
	{
		$e = parent::from($src);

		preg_match("/Duplicate entry '([^']+)' for key '([^']+)'/", $e->errorInfo[2], $m);
		$e->entry = $m[1];
		$e->key = $m[2];

		return $e;
	}


	/**
	 * @return string
	 */
	public function getEntry()
	{
		return $this->entry;
	}


	/**
	 * @return string
	 */
	public function getKey()
	{
		return $this->key;
	}

}
