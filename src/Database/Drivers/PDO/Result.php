<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Drivers\PDO;

use Nette\Database\DriverException;
use Nette\Database\Drivers;
use PDOException;


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
		$row = $this->fetchList();
		if (!$row) {
			return null;
		}

		$res = [];
		foreach ($this->getColumnsInfo() as $i => $meta) {
			$res[$meta['name']] = $row[$i];
		}
		return $res;
	}


	public function fetchList(): ?array
	{
		try {
			$row = $this->result->fetch(\PDO::FETCH_NUM);
			if (!$row) {
				$this->free();
				return null;
			}
			return $row;

		} catch (PDOException $e) {
			throw new ($this->connection->convertException($e, $args))(...$args);
		}
	}


	public function getColumnCount(): int
	{
		try {
			return $this->result->columnCount();
		} catch (PDOException $e) {
			throw new ($this->connection->convertException($e, $args))(...$args);
		}
	}


	public function getRowCount(): int
	{
		try {
			return $this->result->rowCount();
		} catch (PDOException $e) {
			throw new ($this->connection->convertException($e, $args))(...$args);
		}
	}


	public function getColumnsInfo(): array
	{
		return $this->columns ??= $this->collectColumnsInfo();
	}


	protected function collectColumnsInfo(): array
	{
		$res = [];
		$count = $this->result->columnCount();
		for ($i = 0; $i < $count; $i++) {
			$meta = $this->result->getColumnMeta($i) ?: throw new DriverException('Cannot fetch column metadata');
			$res[] = [
				'name' => $meta['name'],
				'nativeType' => $meta[$this->connection->metaTypeKey] ?? null,
				'size' => $meta['len'],
				'scale' => $meta['precision'],
			];
		}
		return $res;
	}


	public function free(): void
	{
		$this->result->closeCursor();
	}
}
