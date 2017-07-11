<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Table;

use Nette\Database;


/**
 * Container of database result fetched into IRow objects.
 *
 * @method     IRow|null  fetch() Fetches single row object.
 * @method     IRow[]     fetchAll() Fetches all rows.
 */
interface IRowContainer extends Database\IRowContainer
{
}
