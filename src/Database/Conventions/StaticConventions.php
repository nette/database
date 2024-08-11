<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Conventions;

use Nette\Database\Conventions;


/**
 * Conventions based on static definition.
 */
class StaticConventions implements Conventions
{
	/**
	 * Create static conventional structure.
	 * @param  string  $primary  %s stands for table name
	 * @param  string  $foreign  %1$s stands for key used after ->, %2$s for table name
	 * @param  string  $table  %1$s stands for key used after ->, %2$s for table name
	 */
	public function __construct(
		protected readonly string $primary = 'id',
		protected readonly string $foreign = '%s_id',
		protected readonly string $table = '%s',
	) {
	}


	public function getPrimary(string $table): string
	{
		return sprintf($this->primary, $this->getColumnFromTable($table));
	}


	public function getHasManyReference(string $table, string $key): ?array
	{
		$table = $this->getColumnFromTable($table);
		return [
			sprintf($this->table, $key, $table),
			sprintf($this->foreign, $table, $key),
		];
	}


	public function getBelongsToReference(string $table, string $key): ?array
	{
		$table = $this->getColumnFromTable($table);
		return [
			sprintf($this->table, $key, $table),
			sprintf($this->foreign, $key, $table),
		];
	}


	protected function getColumnFromTable(string $name): string
	{
		if (
			$this->table !== '%s'
			&& preg_match('(^' . str_replace('%s', '(.*)', preg_quote($this->table)) . '$)D', $name, $match)
		) {
			return $match[1];
		}

		return $name;
	}
}
