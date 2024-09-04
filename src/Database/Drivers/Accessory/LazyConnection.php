<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\Accessory;

use Nette\Database\Drivers;


final class LazyConnection implements Drivers\Connection
{
	public function __construct(
		private \Closure $callback,
	) {
	}


	private function getConnection(): Drivers\Connection
	{
		return ($this->callback)();
	}


	public function query(string $sql, array $params = [])
	{
		return $this->getConnection()->query($sql, $params);
	}


	public function execute(string $sql): int
	{
		return $this->getConnection()->execute($sql);
	}


	public function getNativeConnection(): mixed
	{
		return $this->getConnection()->getNativeConnection();
	}


	public function beginTransaction(): void
	{
		$this->getConnection()->beginTransaction();
	}


	public function commit(): void
	{
		$this->getConnection()->commit();
	}


	public function rollBack(): void
	{
		$this->getConnection()->rollBack();
	}


	public function getInsertId(?string $sequence = null): int|string
	{
		return $this->getConnection()->getInsertId($sequence);
	}


	public function quote(string $string): string
	{
		return $this->getConnection()->quote($string);
	}


	public function getServerVersion(): string
	{
		return $this->getConnection()->getServerVersion();
	}
}
