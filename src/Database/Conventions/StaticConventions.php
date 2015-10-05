<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database\Conventions;

use Nette\Database\IConventions;
use Nette\Object;


/**
 * Conventions based on static definition.
 */
class StaticConventions extends Object implements IConventions
{
	/** @var string */
	protected $primary;

	/** @var string */
	protected $foreign;

	/** @var string */
	protected $table;


	/**
	 * Create static conventional structure.
	 * @param  string %s stands for table name
	 * @param  string %1$s stands for key used after ->, %2$s for table name
	 * @param  string %1$s stands for key used after ->, %2$s for table name
	 */
	public function __construct($primary = 'id', $foreign = '%s_id', $table = '%s')
	{
		$this->primary = $primary;
		$this->foreign = $foreign;
		$this->table = $table;
	}


	public function getPrimary($table)
	{
		return sprintf($this->primary, $this->getColumnFromTable($table));
	}


	public function getHasManyReference($table, $key)
	{
		$table = $this->getColumnFromTable($table);
		return array(
			sprintf($this->table, $key, $table),
			sprintf($this->foreign, $table, $key),
		);
	}


	public function getBelongsToReference($table, $key)
	{
		$table = $this->getColumnFromTable($table);
		return array(
			sprintf($this->table, $key, $table),
			sprintf($this->foreign, $key, $table),
		);
	}


	protected function getColumnFromTable($name)
	{
		if ($this->table !== '%s' && preg_match('(^' . str_replace('%s', '(.*)', preg_quote($this->table)) . '\z)', $name, $match)) {
			return $match[1];
		}

		return $name;
	}

}
