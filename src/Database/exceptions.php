<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;


/**
 * Failed to connect to the database server.
 */
class ConnectionException extends DriverException
{
}


/**
 * The connection to the server was lost during an operation (e.g. server
 * restart, network failure, idle-timeout). A reconnect is needed before
 * the connection can be used again.
 */
class ConnectionLostException extends ConnectionException
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


/**
 * A CHECK constraint check failed.
 */
class CheckConstraintViolationException extends ConstraintViolationException
{
}


/**
 * Deadlock or serialization failure detected by the server; the transaction
 * was rolled back and can be retried.
 */
class DeadlockException extends DriverException
{
}


/**
 * A lock wait exceeded the configured timeout. The statement was aborted,
 * typically leaving the surrounding transaction alive.
 */
class LockTimeoutException extends DriverException
{
}
