<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;


/**
 * Allows to use dynamic password (token authorizations).
 */
interface CredentialProvider
{
	/**
	 * Returns currently valid password for initialization of connection
	 */
	function getPassword(): string;
}
