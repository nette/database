<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette;
use PDO;
use PDOException;


/**
 * PDO-based driver.
 */
abstract class PdoDriver implements Nette\Database\Driver
{
	use Nette\SmartObject;

	protected ?PDO $pdo = null;


	public function connect(string $dsn, string $user = null, string $password = null, array $options = null): void
	{
		try {
			$this->pdo = new PDO($dsn, $user, $password, $options);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			throw Nette\Database\ConnectionException::from($e);
		}
	}


	public function getPdo(): ?PDO
	{
		return $this->pdo;
	}
}
