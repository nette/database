<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Database\Table;

interface IRowFactory
{

	/**
	 * @return ActiveRow
	 */
	public function create($tableName, array $row, Selection $table);

}
