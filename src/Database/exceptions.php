<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


/**
 * Failed to connect to the database server.
 */
class ConnectionException extends DriverException
{
}


/**
 * A database constraint was violated.
 */
class ConstraintViolationException extends DriverException
{
}


/**
 * The foreign key constraint check failed.
 */
class ForeignKeyConstraintViolationException extends ConstraintViolationException
{
}


/**
 * The NOT NULL constraint check failed.
 */
class NotNullConstraintViolationException extends ConstraintViolationException
{
}


/**
 * The unique constraint check failed.
 */
class UniqueConstraintViolationException extends ConstraintViolationException
{
}
