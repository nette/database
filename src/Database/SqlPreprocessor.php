<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;


/**
 * SQL preprocessor.
 *
 * @author     David Grudl
 */
class SqlPreprocessor extends Nette\Object
{
	/** @var Connection */
	private $connection;

	/** @var ISupplementalDriver */
	private $driver;

	/** @var array of input parameters */
	private $params;

	/** @var array of parameters to be processed by PDO */
	private $remaining;

	/** @var int */
	private $counter;

	/** @var string values|set|and|order */
	private $arrayMode;


	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
		$this->driver = $connection->getSupplementalDriver();
	}


	/**
	 * @param  array
	 * @return array of [sql, params]
	 */
	public function process($params)
	{
		$this->params = $params;
		$this->counter = 0;
		$this->remaining = array();
		$this->arrayMode = NULL;
		$res = array();

		while ($this->counter < count($params)) {
			$param = $params[$this->counter++];

			if (($this->counter === 2 && count($params) === 2) || !is_scalar($param)) {
				$res[] = $this->formatValue($param, 'auto');
				$this->arrayMode = NULL;

			} elseif (is_string($param)) {
				$this->arrayMode = NULL;
				$res[] = Nette\Utils\Strings::replace(
					$param,
					'~\'[^\']*+\'|"[^"]*+"|\?[a-z]*|^\s*+(?:INSERT|REPLACE)\b|\b(?:UPDATE|SET|WHERE|HAVING|ORDER BY|GROUP BY)(?=[\s?]*+\z)|/\*.*?\*/|--[^\n]*~si',
					array($this, 'callback')
				);
			} else {
				throw new Nette\InvalidArgumentException('There are more parameters than placeholders.');
			}
		}

		return array(implode(' ', $res), $this->remaining);
	}


	/** @internal */
	public function callback($m)
	{
		$m = $m[0];
		if ($m[0] === "'" || $m[0] === '"' || $m[0] === '/' || $m[0] === '-') { // string or comment
			return $m;

		} elseif ($m[0] === '?') { // placeholder
			if ($this->counter >= count($this->params)) {
				throw new Nette\InvalidArgumentException('There are more placeholders than passed parameters.');

			} elseif (in_array($m, array('?', '?and', '?or', '?set', '?values', '?order'), TRUE)) {
				return $this->formatValue($this->params[$this->counter++], substr($m, 1) ?: 'auto');

			} elseif ($m === '?name') {
				return $this->driver->delimite($this->params[$this->counter++]);

			} else {
				throw new Nette\InvalidArgumentException("Unknown placeholder $m.");
			}

		} else { // command
			static $modes = array(
				'INSERT' => 'values',
				'REPLACE' => 'values',
				'UPDATE' => 'set',
				'SET' => 'set',
				'WHERE' => 'and',
				'HAVING' => 'and',
				'ORDER BY' => 'order',
				'GROUP BY' => 'order',
			);
			$this->arrayMode = $modes[ltrim(strtoupper($m))];
			return $m;
		}
	}


	private function formatValue($value, $mode = NULL)
	{
		if (is_string($value)) {
			if (strlen($value) > 20) {
				$this->remaining[] = $value;
				return '?';

			} else {
				return $this->connection->quote($value);
			}

		} elseif (is_int($value)) {
			return (string) $value;

		} elseif (is_float($value)) {
			return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');

		} elseif (is_bool($value)) {
			return $this->driver->formatBool($value);

		} elseif ($value === NULL) {
			return 'NULL';

		} elseif ($value instanceof Table\IRow) {
			return $value->getPrimary();

		} elseif (is_array($value) || $value instanceof \Traversable) {
			$vx = $kx = array();
			if ($mode === 'auto') {
				$mode = $this->arrayMode;
			}

			if ($value instanceof \Traversable) {
				$value = iterator_to_array($value);
			}

			if (array_key_exists(0, $value)) { // non-associative
				foreach ($value as $val) {
					$vx2 = array();
					foreach (is_array($val) ? $val : array($val) as $v) {
						$vx2[] = $this->formatValue($v);
					}
					$vx[] = implode(', ', $vx2);
				}
				if ($mode === 'values') { // multi-insert
					$select = $this->driver->isSupported(ISupplementalDriver::SUPPORT_MULTI_INSERT_AS_SELECT);
					foreach ($value[0] as $k => $v) {
						$kx[] = $this->delimite($k);
					}
					return '(' . implode(', ', $kx) . ($select ? ') SELECT ' : ') VALUES (')
						. implode($select ? ' UNION ALL SELECT ' : '), (', $vx) . ($select ? '' : ')');

				} else { // value, value, ... OR (1, 2), (3, 4)
					return is_array($val) ? '(' . implode('), (', $vx) . ')' : implode(', ', $vx);
				}

			} elseif ($mode === 'values') { // (key, key, ...) VALUES (value, value, ...)
				foreach ($value as $k => $v) {
					$kx[] = $this->delimite($k);
					$vx[] = $this->formatValue($v);
				}
				return '(' . implode(', ', $kx) . ') VALUES (' . implode(', ', $vx) . ')';

			} elseif (!$mode || $mode === 'set') { // key=value, key=value, ...
				foreach ($value as $k => $v) {
					if (substr($k, -1) === '=') {
						$k2 = $this->delimite(substr($k, 0, -2));
						$vx[] = $k2 . '=' . $k2 . ' ' . substr($k, -2, 1) . ' ' . $this->formatValue($v);
					} else {
						$vx[] = $this->delimite($k) . '=' . $this->formatValue($v);
					}
				}
				return implode(', ', $vx);

			} elseif ($mode === 'and' || $mode === 'or') { // (key [operator] value) AND ...
				foreach ($value as $k => $v) {
					list($k, $operator) = explode(' ', $k . ' ');
					$k = $this->delimite($k);
					if (is_array($v)) {
						$vx[] = $v ? ($k . ' ' . ($operator ?: 'IN') . ' (' . $this->formatValue(array_values($v)) . ')') : '1=0';
					} else {
						$v = $this->formatValue($v);
						$vx[] = $k . ' ' . ($operator ?: ($v === 'NULL' ? 'IS' : '=')) . ' ' . $v;
					}
				}
				return $value ? '(' . implode(') ' . strtoupper($mode) . ' (', $vx) . ')' : '1=1';

			} elseif ($mode === 'order') { // key, key DESC, ...
				foreach ($value as $k => $v) {
					$vx[] = $this->delimite($k) . ($v > 0 ? '' : ' DESC');
				}
				return implode(', ', $vx);
			}

		} elseif ($value instanceof \DateTime || $value instanceof \DateTimeInterface) {
			return $this->driver->formatDateTime($value);

		} elseif ($value instanceof SqlLiteral) {
			$this->remaining = array_merge($this->remaining, $value->getParameters());
			return $value->__toString();

		} else {
			$this->remaining[] = $value;
			return '?';
		}
	}


	private function delimite($name)
	{
		return implode('.', array_map(array($this->driver, 'delimite'), explode('.', $name)));
	}

}
