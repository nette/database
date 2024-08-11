<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\SQLSrv;

use Nette\Database\Drivers;


/**
 * PDO SQL Server database driver connection.
 * Driver options:
 *    - convertBoolean => converts BIT to boolean
 */
class Connection extends Drivers\PDO\Connection
{
	public function getDatabaseEngine(): Drivers\Engines\SQLServerEngine
	{
		return new Drivers\Engines\SQLServerEngine($this);
	}


	protected function initialize(array $options): void
	{
		$this->pdo->setAttribute(\PDO::SQLSRV_ATTR_FORMAT_DECIMALS, true);
	}


	public function getMetaTypeKey(): string
	{
		return 'sqlsrv:decl_type';
	}
}
