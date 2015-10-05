<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;


/**
 * SQL preprocessor.
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
		$prev = -1;
		$this->remaining = array();
		$this->arrayMode = NULL;
		$res = array();

		while ($this->counter < count($params)) {
			$param = $params[$this->counter++];

			if (($this->counter === 2 && count($params) === 2) || !is_scalar($param)) {
				$res[] = $this->formatValue($param, 'auto');
				$this->arrayMode = NULL;

			} elseif (is_string($param) && $this->counter > $prev + 1) {
				$prev = $this->counter;
				$this->arrayMode = NULL;
				$res[] = Nette\Utils\Strings::replace(
					$param,
					'~\'[^\']*+\'|"[^"]*+"|\?[a-z]*|^\s*+(?:INSERT|REPLACE)\b|\b(?:SET|WHERE|HAVING|ORDER BY|GROUP BY|KEY UPDATE)(?=[\s?]*+\z)|/\*.*?\*/|--[^\n]*~si',
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
		if ($m[0] === '?') { // placeholder
			if ($this->counter >= count($this->params)) {
				throw new Nette\InvalidArgumentException('There are more placeholders than passed parameters.');
			}
			return $this->formatValue($this->params[$this->counter++], substr($m, 1) ?: 'auto');

		} elseif ($m[0] === "'" || $m[0] === '"' || $m[0] === '/' || $m[0] === '-') { // string or comment
			return $m;

		} else { // command
			static $modes = array(
				'INSERT' => 'values',
				'REPLACE' => 'values',
				'KEY UPDATE' => 'set',
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
		if (!$mode || $mode === 'auto') {
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

			} elseif ($value instanceof SqlLiteral) {
				$prep = clone $this;
				list($res, $params) = $prep->process(array_merge(array($value->__toString()), $value->getParameters()));
				$this->remaining = array_merge($this->remaining, $params);
				return $res;

			} elseif ($value instanceof \DateTime || $value instanceof \DateTimeInterface) {
				return $this->driver->formatDateTime($value);

			} elseif ($value instanceof \DateInterval) {
				return $this->driver->formatDateInterval($value);

			} elseif (is_object($value) && method_exists($value, '__toString')) {
				return $this->formatValue((string) $value);

			} elseif (is_resource($value)) {
				$this->remaining[] = $value;
				return '?';
			}

		} elseif ($mode === 'name') {
			if (!is_string($value)) {
				$type = gettype($value);
				throw new Nette\InvalidArgumentException("Placeholder ?$mode expects string, $type given.");
			}
			return $this->delimite($value);
		}

		if ($value instanceof \Traversable && !$value instanceof Table\IRow) {
			$value = iterator_to_array($value);
		}

		if (is_array($value)) {
			$vx = $kx = array();
			if ($mode === 'auto') {
				$mode = $this->arrayMode;
			}

			if ($mode === 'values') { // (key, key, ...) VALUES (value, value, ...)
				if (array_key_exists(0, $value)) { // multi-insert
					foreach ($value[0] as $k => $v) {
						$kx[] = $this->delimite($k);
					}
					foreach ($value as $val) {
						$vx2 = array();
						foreach ($val as $v) {
							$vx2[] = $this->formatValue($v);
						}
						$vx[] = implode(', ', $vx2);
					}
					$select = $this->driver->isSupported(ISupplementalDriver::SUPPORT_MULTI_INSERT_AS_SELECT);
					return '(' . implode(', ', $kx) . ($select ? ') SELECT ' : ') VALUES (')
						. implode($select ? ' UNION ALL SELECT ' : '), (', $vx) . ($select ? '' : ')');
				}

				foreach ($value as $k => $v) {
					$kx[] = $this->delimite($k);
					$vx[] = $this->formatValue($v);
				}
				return '(' . implode(', ', $kx) . ') VALUES (' . implode(', ', $vx) . ')';

			} elseif (!$mode || $mode === 'set') {
				foreach ($value as $k => $v) {
					if (is_int($k)) { // value, value, ... OR (1, 2), (3, 4)
						$vx[] = is_array($v) ? '(' . $this->formatValue($v) . ')' : $this->formatValue($v);
					} elseif (substr($k, -1) === '=') { // key+=value, key-=value, ...
						$k2 = $this->delimite(substr($k, 0, -2));
						$vx[] = $k2 . '=' . $k2 . ' ' . substr($k, -2, 1) . ' ' . $this->formatValue($v);
					} else { // key=value, key=value, ...
						$vx[] = $this->delimite($k) . '=' . $this->formatValue($v);
					}
				}
				return implode(', ', $vx);

			} elseif ($mode === 'and' || $mode === 'or') { // (key [operator] value) AND ...
				foreach ($value as $k => $v) {
					if (is_int($k)) {
						$vx[] = $this->formatValue($v);
						continue;
					}
					list($k, $operator) = explode(' ', $k . ' ');
					$k = $this->delimite($k);
					if (is_array($v)) {
						if ($v) {
							$vx[] = $k . ' ' . ($operator ? $operator . ' ' : '') . 'IN (' . $this->formatValue(array_values($v)) . ')';
						} elseif ($operator === 'NOT') {
						} else {
							$vx[] = '1=0';
						}
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

			} else {
				throw new Nette\InvalidArgumentException("Unknown placeholder ?$mode.");
			}

		} elseif (in_array($mode, array('and', 'or', 'set', 'values', 'order'), TRUE)) {
			$type = gettype($value);
			throw new Nette\InvalidArgumentException("Placeholder ?$mode expects array or Traversable object, $type given.");

		} elseif ($mode && $mode !== 'auto') {
			throw new Nette\InvalidArgumentException("Unknown placeholder ?$mode.");

		} else {
			throw new Nette\InvalidArgumentException('Unexpected type of parameter: ' . (is_object($value) ? get_class($value) : gettype($value)));
		}
	}


	private function delimite($name)
	{
		return implode('.', array_map(array($this->driver, 'delimite'), explode('.', $name)));
	}

}
