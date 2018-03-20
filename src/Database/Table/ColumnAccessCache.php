<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database\Table;

use Nette\Caching\Cache;
use Nette\Caching\IStorage;


class ColumnAccessCache
{
	/** @var Selection */
	protected $selection;

	/** @var Cache */
	protected $cache;

	/** @var string */
	protected $generalCacheKey;

	/** @var string */
	protected $specificCacheKey;

	/** @var array of touched columns */
	protected $accessedColumns = [];

	/** @var array of earlier touched columns */
	protected $previousAccessedColumns;

	/** @var Selection instance of observed accessed columns */
	protected $observeCache;


	public function __construct(Selection $selection, IStorage $cacheStorage = null)
	{
		$this->selection = $selection;
		$this->cache = $cacheStorage ? new Cache($cacheStorage, 'Nette.Database.' . md5($selection->getConnection()->getDsn())) : null;
	}


	public function getStorage(): ?IStorage
	{
		return $this->cache ? $this->cache->getStorage() : null;
	}


	/**
	 * Returns general cache key independent on query parameters or sql limit
	 * Used e.g. for previously accessed columns caching
	 */
	public function getGeneralCacheKey(): string
	{
		if ($this->generalCacheKey) {
			return $this->generalCacheKey;
		}

		$key = [__CLASS__, $this->selection->getName(), $this->selection->getSqlBuilder()->getConditions()];
		$trace = [];
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $item) {
			$trace[] = isset($item['file'], $item['line']) ? $item['file'] . $item['line'] : null;
		}

		$key[] = $trace;
		return $this->generalCacheKey = md5(serialize($key));
	}


	public function setGeneralCacheKey(?string $key): void
	{
		$this->generalCacheKey = $key;
	}


	/**
	 * Returns object specific cache key dependent on query parameters
	 * Used e.g. for reference memory caching
	 */
	public function getSpecificCacheKey(): string
	{
		if ($this->specificCacheKey) {
			return $this->specificCacheKey;
		}

		return $this->specificCacheKey = $this->selection->getSqlBuilder()->getSelectQueryHash($this->getPreviousAccessedColumns());
	}


	public function setSpecificCacheKey(?string $key): void
	{
		$this->specificCacheKey = $key;
	}


	public function getAccessedColumns(): array
	{
		return $this->accessedColumns;
	}


	public function setAccessedColumns(array $accessedColumns): void
	{
		if ($this->cache) {
			$this->accessedColumns = $accessedColumns;
		}
	}


	public function setAccessedColumn(string $key, bool $value): void
	{
		if ($this->cache) {
			$this->accessedColumns[$key] = $value;
		}
	}


	/**
	 * Loads cache of previous accessed columns and returns it.
	 */
	public function getPreviousAccessedColumns(): array
	{
		if ($this->cache && $this->previousAccessedColumns === null) {
			$this->accessedColumns = $this->previousAccessedColumns = $this->cache->load($this->getGeneralCacheKey()) ?: [];
		}

		return array_keys(array_filter((array) $this->previousAccessedColumns));
	}


	public function setPreviousAccessedColumns(array $previousAccessedColumns): void
	{
		$this->previousAccessedColumns = $previousAccessedColumns;
	}


	public function clearPreviousAccessedColumns(): void
	{
		$this->previousAccessedColumns = null;
	}


	public function saveState(): void
	{
		if ($this->observeCache === $this->selection && $this->cache && !$this->selection->getSqlBuilder()->getSelect() && $this->accessedColumns !== $this->previousAccessedColumns) {
			$previousAccessed = $this->cache->load($this->getGeneralCacheKey());
			$accessed = $this->accessedColumns;
			$needSave = is_array($previousAccessed)
				? array_intersect_key($accessed, $previousAccessed) !== $accessed
				: $accessed !== $previousAccessed;

			if ($needSave) {
				$save = is_array($previousAccessed) ? $previousAccessed + $accessed : $accessed;
				$this->cache->save($this->getGeneralCacheKey(), $save);
				$this->previousAccessedColumns = null;
			}
		}
	}


	public function &loadFromRefCache(&$referencing): string
	{
		$hash = $this->getSpecificCacheKey();
		$this->observeCache = &$referencing['observeCache'];
		$this->accessedColumns = &$referencing[$hash]['accessed'];
		$this->specificCacheKey = &$referencing[$hash]['specificCacheKey'];

		if ($this->accessedColumns === null) {
			$this->accessedColumns = [];
		}

		return $hash;
	}


	public function setObserveCache(Selection $observeCache): void
	{
		$this->observeCache = $observeCache;
	}


	/**
	 * @internal
	 */
	public function setSelection(Selection $selection): void
	{
		$this->selection = $selection;
	}
}
