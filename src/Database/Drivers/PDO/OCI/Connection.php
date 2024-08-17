<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\OCI;

use Nette\Database\Drivers;


/**
 * PDO Oracle database driver connection.
 */
class Connection extends Drivers\PDO\Connection
{
	public function getDatabaseEngine(): Drivers\Engines\OracleEngine
	{
		return new Drivers\Engines\OracleEngine($this);
	}


	protected function initialize(array $options): void
	{
		if (isset($options['formatDateTime'])) {
			$this->engine->formatDateTime = $options['formatDateTime'];
		}
	}
}
