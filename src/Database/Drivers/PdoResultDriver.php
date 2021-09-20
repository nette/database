<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers;

use Nette;


/**
 * PDO-based result-set driver.
 */
class PdoResultDriver implements Nette\Database\ResultDriver
{
	use Nette\SmartObject;

	private \PDOStatement $result;

	private PdoDriver $driver;


	public function __construct(\PDOStatement $result, PdoDriver $driver)
	{
		$this->result = $result;
		$this->driver = $driver;
	}


	public function fetch(): ?array
	{
		$data = $this->result->fetch();
		if (!$data) {
			$this->result->closeCursor();
			return null;
		}
		return $data;
	}


	public function getColumnCount(): int
	{
		return $this->result->columnCount();
	}


	public function getRowCount(): int
	{
		return $this->result->rowCount();
	}


	public function getColumnTypes(): array
	{
		return $this->driver->getColumnTypes($this->result);
	}


	public function getColumnMeta(int $col): array
	{
		return $this->result->getColumnMeta($col);
	}


	public function getPdoStatement(): \PDOStatement
	{
		return $this->result;
	}
}
