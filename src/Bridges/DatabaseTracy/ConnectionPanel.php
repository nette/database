<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Bridges\DatabaseTracy;

use Nette;
use Nette\Database\Helpers;
use Tracy;


/**
 * Debug panel for Nette\Database.
 */
class ConnectionPanel extends Nette\Object implements Tracy\IBarPanel
{
	/** @var int */
	public $maxQueries = 100;

	/** @var int logged time */
	private $totalTime = 0;

	/** @var int */
	private $count = 0;

	/** @var array */
	private $queries = array();

	/** @var string */
	public $name;

	/** @var bool|string explain queries? */
	public $explain = TRUE;

	/** @var bool */
	public $disabled = FALSE;


	public function __construct(Nette\Database\Connection $connection)
	{
		$connection->onQuery[] = array($this, 'logQuery');
	}


	public function logQuery(Nette\Database\Connection $connection, $result)
	{
		if ($this->disabled) {
			return;
		}
		$this->count++;

		$source = NULL;
		$trace = $result instanceof \PDOException ? $result->getTrace() : debug_backtrace(PHP_VERSION_ID >= 50306 ? DEBUG_BACKTRACE_IGNORE_ARGS : FALSE);
		foreach ($trace as $row) {
			if (isset($row['file']) && is_file($row['file']) && !Tracy\Debugger::getBluescreen()->isCollapsed($row['file'])) {
				if ((isset($row['function']) && strpos($row['function'], 'call_user_func') === 0)
					|| (isset($row['class']) && is_subclass_of($row['class'], '\\Nette\\Database\\Connection'))
				) {
					continue;
				}
				$source = array($row['file'], (int) $row['line']);
				break;
			}
		}
		if ($result instanceof Nette\Database\ResultSet) {
			$this->totalTime += $result->getTime();
			if ($this->count < $this->maxQueries) {
				$this->queries[] = array($connection, $result->getQueryString(), $result->getParameters(), $source, $result->getTime(), $result->getRowCount(), NULL);
			}

		} elseif ($result instanceof \PDOException && $this->count < $this->maxQueries) {
			$this->queries[] = array($connection, $result->queryString, NULL, $source, NULL, NULL, $result->getMessage());
		}
	}


	public static function renderException($e)
	{
		if (!$e instanceof \PDOException) {
			return;
		}
		if (isset($e->queryString)) {
			$sql = $e->queryString;

		} elseif ($item = Tracy\Helpers::findTrace($e->getTrace(), 'PDO::prepare')) {
			$sql = $item['args'][0];
		}
		return isset($sql) ? array(
			'tab' => 'SQL',
			'panel' => Helpers::dumpSql($sql),
		) : NULL;
	}


	public function getTab()
	{
		$name = $this->name;
		$count = $this->count;
		$totalTime = $this->totalTime;
		ob_start();
		require __DIR__ . '/templates/ConnectionPanel.tab.phtml';
		return ob_get_clean();
	}


	public function getPanel()
	{
		$this->disabled = TRUE;
		if (!$this->count) {
			return;
		}

		$name = $this->name;
		$count = $this->count;
		$totalTime = $this->totalTime;
		$queries = array();
		foreach ($this->queries as $query) {
			list($connection, $sql, $params, $source, $time, $rows, $error) = $query;
			$explain = NULL;
			if (!$error && $this->explain && preg_match('#\s*\(?\s*SELECT\s#iA', $sql)) {
				try {
					$cmd = is_string($this->explain) ? $this->explain : 'EXPLAIN';
					$explain = $connection->queryArgs("$cmd $sql", $params)->fetchAll();
				} catch (\PDOException $e) {
				}
			}
			$query[] = $explain;
			$queries[] = $query;
		}

		ob_start();
		require __DIR__ . '/templates/ConnectionPanel.panel.phtml';
		return ob_get_clean();
	}

}
