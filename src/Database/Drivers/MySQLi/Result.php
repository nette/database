<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\MySQLi;

use Nette\Database\Drivers;


class Result implements Drivers\Result
{
	public function __construct(
		private \mysqli_result $result,
	) {
	}


	public function fetch(): ?array
	{
		return $this->result->fetch_assoc();
	}


	public function getColumnCount(): int
	{
		return $this->result->field_count;
	}


	public function getRowCount(): int
	{
		return $this->result->num_rows;
	}


	public function getColumnsInfo(): array
	{
		return [];
	}


	public function free(): void
	{
		$this->result->free_result();
	}
}
