<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette;


/**
 * PDO-based driver.
 */
abstract class PdoDriver implements Nette\Database\Driver
{
	use Nette\SmartObject;
}
