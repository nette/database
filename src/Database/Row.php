<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;


/**
 * Represents a single table row.
 */
class Row extends Nette\Utils\ArrayHash implements IRow
{
	public function __get($key)
	{
		$hint = Nette\Utils\ObjectMixin::getSuggestion(array_keys((array) $this), $key);
		throw new Nette\MemberAccessException("Cannot read an undeclared column '$key'" . ($hint ? ", did you mean '$hint'?" : '.'));
	}


	/**
	 * Returns a item.
	 * @param  mixed  key or index
	 * @return mixed
	 */
	public function offsetGet($key)
	{
		if (is_int($key)) {
			$arr = array_slice((array) $this, $key, 1);
			if (!$arr) {
				throw new Nette\MemberAccessException("Cannot read an undeclared column '$key'.");
			}
			return current($arr);
		}
		return $this->$key;
	}


	/**
	 * Checks if $key exists.
	 * @param  mixed  key or index
	 * @return bool
	 */
	public function offsetExists($key)
	{
		if (is_int($key)) {
			return (bool) current(array_slice((array) $this, $key, 1));
		}
		return parent::offsetExists($key);
	}
}
