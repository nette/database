<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

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
}
