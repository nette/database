<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Mapping;


/**
 * @internal This is experimental feature
 */
interface Mapper
{
	function mapRow(array $row): mixed;
}
