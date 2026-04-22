<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\DatabaseTracy;

use Nette;
use Nette\Database\Connection;
use Nette\Database\Helpers;
use Tracy;
use function is_string;


/**
 * Tracy Bar panel showing executed SQL queries with timing and EXPLAIN support.
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

	/** @var list<array{Connection, string, ?array<mixed>, list<array<string, mixed>>, ?float, ?int, ?string}> */
	private array $queries = [];
	private Tracy\BlueScreen $blueScreen;


	/**
	 * Registers the panel with Tracy. Optionally adds it to the Tracy Bar.
	 */
	public static function initialize(
		Connection $connection,
		bool $addBarPanel = false,
		string $name = '',
		bool $explain = true,
		?Tracy\Bar $bar = null,
		?Tracy\BlueScreen $blueScreen = null,
	): ?self
	{
		$blueScreen ??= Tracy\Debugger::getBlueScreen();
		$blueScreen->addPanel(self::renderException(...));

		if ($addBarPanel) {
			$panel = new self($connection, $blueScreen);
			$panel->explain = $explain;
			$panel->name = $name;
			$bar ??= Tracy\Debugger::getBar();
			$bar->addPanel($panel);
		}

		return $panel ?? null;
	}


	public function __construct(Connection $connection, Tracy\BlueScreen $blueScreen)
	{
		$connection->onQuery[] = $this->logQuery(...);
		$this->blueScreen = $blueScreen;
	}


	private function logQuery(Connection $connection, Nette\Database\ResultSet|\PDOException $result): void
	{
		if ($this->disabled) {
			return;
		}

		$this->count++;

		$trace = $result instanceof \PDOException
			? array_map(fn($row) => array_diff_key($row, ['args' => null]), $result->getTrace())
			: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

		foreach ($trace as $row) {
			$file = $row['file'] ?? null;
			if (is_string($file)
				&& preg_match('~\.(php.?|phtml)$~', $file)
				&& !$this->blueScreen->isCollapsed($file)
			) {
				break;
			}

			array_shift($trace);
		}

		if ($result instanceof Nette\Database\ResultSet) {
			$this->totalTime += $result->getTime();
			if ($this->count < $this->maxQueries) {
				$this->queries[] = [$connection, $result->getQueryString(), $result->getParameters(), $trace, $result->getTime(), $result->getRowCount(), null];
			}
		} elseif ($result instanceof \PDOException && $this->count < $this->maxQueries) {
			$this->queries[] = [$connection, $result->queryString, null, $trace, null, null, $result->getMessage()];
		}
	}


	/** @return array{tab: string, panel: string}|null */
	public static function renderException(?\Throwable $e): ?array
	{
		if (!$e instanceof \PDOException) {
			return null;
		}

		if (isset($e->queryString)) {
			$sql = $e->queryString;

		} elseif (($item = Tracy\Helpers::findTrace($e->getTrace(), 'PDO::prepare')) && isset($item['args'][0])) {
			$sql = $item['args'][0];
		}

		return isset($sql) ? [
			'tab' => 'SQL',
			'panel' => Helpers::dumpSql($sql, $e->params ?? []),
		] : null;
	}


	public function getTab(): string
	{
		return Nette\Utils\Helpers::capture(function () {
			$name = $this->name;
			$count = $this->count;
			$totalTime = $this->totalTime;
			require __DIR__ . '/dist/tab.phtml';
		});
	}


	public function getPanel(): ?string
	{
		if (!$this->count) {
			return null;
		}

		$queries = [];
		$connection = null;
		foreach ($this->queries as $query) {
			[$connection, $sql, $params, , , , $error] = $query;
			$explain = null;
			$command = preg_match('#\s*\(?\s*(SELECT|INSERT|UPDATE|DELETE)\s#iA', $sql, $m)
				? strtolower($m[1])
				: null;
			if (!$error && $this->explain && $command === 'select') {
				try {
					$cmd = is_string($this->explain)
						? $this->explain
						: 'EXPLAIN';
					$explain = (new Nette\Database\ResultSet($connection, "$cmd $sql", $params ?? []))->fetchAll();
				} catch (\PDOException) {
				}
			}

			$query[] = $command;
			$query[] = $explain;
			$queries[] = $query;
		}

		return Nette\Utils\Helpers::capture(function () use ($queries, $connection) {
			$name = $this->name;
			$count = $this->count;
			$totalTime = $this->totalTime;
			$performanceScale = $this->performanceScale;
			require __DIR__ . '/dist/panel.phtml';
		});
	}
}
