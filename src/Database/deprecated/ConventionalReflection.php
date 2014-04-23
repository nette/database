<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Database\Reflection;

use Nette;


/** @deprecated */
class ConventionalReflection extends Nette\Database\Conventions\StaticConventions
{

	public function __construct($primary = 'id', $foreign = '%s_id', $table = '%s')
	{
		parent::__construct($primary, $foreign, $table);
		trigger_error(__CLASS__ . '() is deprecated; use Nette\Database\Conventions\StaticConventions instead.', E_USER_DEPRECATED);
	}

}
