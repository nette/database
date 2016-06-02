<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;


/**
 * Row normalizer interface.
 */
interface IRowNormalizer
{
	/**
	 * Normalizes result row.
	 * @param array
	 * @param ResultSet
	 * @return array
	 */
	function normalizeRow($row, ResultSet $resultSet);
}
