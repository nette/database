<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO;

use Nette\Database\DriverException;
use Nette\Database\Drivers;


class Result implements Drivers\Result
{
	private array $columns;


	public function __construct(
		protected readonly \PDOStatement $result,
		protected readonly Connection $connection,
	) {
	}


	public function fetch(): ?array
	{
		$row = $this->result->fetch(\PDO::FETCH_NUM);
		if ($row) {
			return $this->toAssociative($row);
		}
		$this->free();
		return null;
	}


	private function toAssociative(array $row): array
	{
		$res = [];
		foreach ($this->getColumnsInfo() as $i => $meta) {
			$res[$meta['name']] = $row[$i];
		}
		return $res;
	}


	public function getColumnCount(): int
	{
		return $this->result->columnCount();
	}


	public function getRowCount(): int
	{
		return $this->result->rowCount();
	}


	public function getColumnsInfo(): array
	{
		return $this->columns ??= $this->collectColumnsInfo();
	}


	protected function collectColumnsInfo(): array
	{
		$res = [];
		$metaTypeKey = $this->connection->getMetaTypeKey();
		$count = $this->result->columnCount();
		for ($i = 0; $i < $count; $i++) {
			$meta = $this->result->getColumnMeta($i) ?: throw new DriverException('Cannot fetch column metadata');
			$res[] = [
				'name' => $meta['name'],
				'nativeType' => $meta[$metaTypeKey] ?? null,
				'length' => $meta['len'],
				'precision' => $meta['precision'],
			];
		}
		return $res;
	}


	public function free(): void
	{
		$this->result->closeCursor();
	}
}
