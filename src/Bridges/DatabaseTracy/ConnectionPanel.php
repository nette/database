<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\DatabaseTracy;

use Nette;
use Nette\Database\DriverException;
use Nette\Database\Explorer;
use Nette\Database\Helpers;
use Nette\Database\Result;
use Tracy;


/**
 * Debug panel for Nette\Database.
 */
class ConnectionPanel implements Tracy\IBarPanel
{
	public int $maxQueries = 100;
	public string $name;
	public bool|string $explain = true;
	public bool $disabled = false;
	public float $performanceScale = 0.25;
	private float $totalTime = 0;
	private int $count = 0;
	private array $events = [];
	private Tracy\BlueScreen $blueScreen;


	public static function initialize(
		Explorer $explorer,
		bool $addBarPanel = true,
		string $name = '',
		bool $explain = true,
		?Tracy\Bar $bar = null,
		?Tracy\BlueScreen $blueScreen = null,
	): ?self
	{
		$blueScreen ??= Tracy\Debugger::getBlueScreen();
		$blueScreen->addPanel(self::renderException(...));

		if ($addBarPanel) {
			$panel = new self($explorer, $blueScreen);
			$panel->explain = $explain;
			$panel->name = $name;
			$bar ??= Tracy\Debugger::getBar();
			$bar->addPanel($panel);
		}

		return $panel ?? null;
	}


	public function __construct(Explorer $explorer, Tracy\BlueScreen $blueScreen)
	{
		$explorer->onQuery[] = $this->logQuery(...);
		$this->blueScreen = $blueScreen;
	}


	private function logQuery(Explorer $connection, $result): void
	{
		if ($this->disabled) {
			return;
		}

		$this->count++;

		$source = null;
		$trace = $result instanceof DriverException
			? $result->getTrace()
			: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		foreach ($trace as $row) {
			if (
				(isset($row['file'])
				&& preg_match('~\.(php.?|phtml)$~', $row['file'])
				&& !$this->blueScreen->isCollapsed($row['file']))
				&& ($row['class'] ?? '') !== self::class
				&& !is_a($row['class'] ?? '', Explorer::class, allow_string: true)
			) {
				$source = [$row['file'], (int) $row['line']];
				break;
			}
		}

		if ($result instanceof Result) {
			$this->totalTime += $result->getTime();
			if ($this->count < $this->maxQueries) {
				$this->events[] = [$connection, $result->getQuery(), $source, $result->getTime(), $result->getRowCount(), null];
			}
		} elseif ($result instanceof DriverException && $this->count < $this->maxQueries) {
			$this->events[] = [$connection, $result->getQuery(), $source, null, null, $result->getMessage()];
		}
	}


	public static function renderException(?\Throwable $e): ?array
	{
		if (!$e instanceof DriverException) {
			return null;
		}

		return $e->getQuery() ? [
			'tab' => 'SQL',
			'panel' => Helpers::dumpSql($e->getQuery()),
		] : null;
	}


	public function getTab(): string
	{
		return Nette\Utils\Helpers::capture(function () {
			$name = $this->name;
			$count = $this->count;
			$totalTime = $this->totalTime;
			require __DIR__ . '/templates/ConnectionPanel.tab.phtml';
		});
	}


	public function getPanel(): ?string
	{
		if (!$this->count) {
			return null;
		}

		$events = [];
		foreach ($this->events as $event) {
			[$connection, $query, , , , $error] = $event;
			$explain = null;
			$sql = $query->getSql();
			$command = preg_match('#\s*\(?\s*(SELECT|INSERT|UPDATE|DELETE)\s#iA', $sql, $m)
				? strtolower($m[1])
				: null;
			if (!$error && $this->explain && $command === 'select') {
				try {
					$cmd = is_string($this->explain)
						? $this->explain
						: 'EXPLAIN';
					$rows = $connection->getConnection()->query("$cmd $sql", $query->getParameters());
					for ($explain = []; $row = $rows->fetch(); $explain[] = $row);
				} catch (DriverException) {
				}
			}

			$event[] = $command;
			$event[] = $explain;
			$events[] = $event;
		}

		return Nette\Utils\Helpers::capture(function () use ($events, $connection) {
			$name = $this->name;
			$count = $this->count;
			$totalTime = $this->totalTime;
			$performanceScale = $this->performanceScale;
			require __DIR__ . '/templates/ConnectionPanel.panel.phtml';
		});
	}
}
