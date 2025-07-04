<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette;
use function array_keys, array_map, array_slice, current, is_int;


/**
 * Represents a single database table row.
 */
class Row extends Nette\Utils\ArrayHash
{
	public function __get(mixed $key): mixed
	{
		$hint = Nette\Utils\Helpers::getSuggestion(array_map('strval', array_keys((array) $this)), $key);
		throw new Nette\MemberAccessException("Cannot read an undeclared column '$key'" . ($hint ? ", did you mean '$hint'?" : '.'));
	}


	public function __isset(string $key): bool
	{
		return isset($this->key);
	}


	/**
	 * Returns a item.
	 * @param  string|int  $key  key or index
	 */
	public function offsetGet($key): mixed
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
	 * @param  string|int  $key  key or index
	 */
	public function offsetExists($key): bool
	{
		if (is_int($key)) {
			return (bool) current(array_slice((array) $this, $key, 1));
		}

		return parent::offsetExists($key);
	}
}


class_alias(Row::class, IRow::class);
