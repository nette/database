<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO\PgSQL;

use Nette\Database\Drivers;


class Result extends Drivers\PDO\Result
{
	private static array $columnsCache = [];


	protected function collectColumnsInfo(): array
	{
		return self::$columnsCache[$this->result->queryString] ??= parent::collectColumnsInfo();
	}
}
