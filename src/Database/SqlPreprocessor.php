<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette;


/**
 * SQL preprocessor.
 */
class SqlPreprocessor
{
	use Nette\SmartObject;

	private const
		MODE_AND = 'and',       // (key [operator] value) AND ...
		MODE_OR = 'or',         // (key [operator] value) OR ...
		MODE_SET = 'set',       // key=value, key=value, ...
		MODE_VALUES = 'values', // (key, key, ...) VALUES (value, value, ...)
		MODE_ORDER = 'order',   // key, key DESC, ...
		MODE_AUTO = 'auto';     // arrayMode for arrays

	private const MODES = [self::MODE_AND, self::MODE_OR, self::MODE_SET, self::MODE_VALUES, self::MODE_ORDER];

	private const ARRAY_MODES = [
		'INSERT' => self::MODE_VALUES,
		'REPLACE' => self::MODE_VALUES,
		'KEY UPDATE' => self::MODE_SET,
		'SET' => self::MODE_SET,
		'WHERE' => self::MODE_AND,
		'HAVING' => self::MODE_AND,
		'ORDER BY' => self::MODE_ORDER,
		'GROUP BY' => self::MODE_ORDER,
	];

	private const PARAMETRIC_COMMANDS = [
		'SELECT' => 1,
		'INSERT' => 1,
		'UPDATE' => 1,
		'DELETE' => 1,
		'REPLACE' => 1,
		'EXPLAIN' => 1,
	];

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

	/** @var bool */
	private $useParams;

	/** @var string|null values|set|and|order */
	private $arrayMode;


	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
		$this->driver = $connection->getSupplementalDriver();
	}


	/**
	 * @return array of [sql, params]
	 */
	public function process(array $params, bool $useParams = false): array
	{
		$this->params = $params;
		$this->counter = 0;
		$prev = -1;
		$this->remaining = [];
		$this->arrayMode = null;
		$this->useParams = $useParams;
		$res = [];

		while ($this->counter < count($params)) {
			$param = $params[$this->counter++];

			if (($this->counter === 2 && count($params) === 2) || !is_scalar($param)) {
				$res[] = $this->formatValue($param, self::MODE_AUTO);
				$this->arrayMode = null;

			} elseif (is_string($param) && $this->counter > $prev + 1) {
				$prev = $this->counter;
				$this->arrayMode = null;
				$res[] = Nette\Utils\Strings::replace(
					$param,
					'~\'[^\']*+\'|"[^"]*+"|\?[a-z]*|^\s*+(?:\(?\s*SELECT|INSERT|UPDATE|DELETE|REPLACE|EXPLAIN)\b|\b(?:SET|WHERE|HAVING|ORDER BY|GROUP BY|KEY UPDATE)(?=\s*$|\s*\?)|/\*.*?\*/|--[^\n]*~Dsi',
					\Closure::fromCallable([$this, 'callback'])
				);
			} else {
				throw new Nette\InvalidArgumentException('There are more parameters than placeholders.');
			}
		}

		return [implode(' ', $res), $this->remaining];
	}


	private function callback(array $m): string
	{
		$m = $m[0];
		if ($m[0] === '?') { // placeholder
			if ($this->counter >= count($this->params)) {
				throw new Nette\InvalidArgumentException('There are more placeholders than passed parameters.');
			}
			return $this->formatValue($this->params[$this->counter++], substr($m, 1) ?: self::MODE_AUTO);

		} elseif ($m[0] === "'" || $m[0] === '"' || $m[0] === '/' || $m[0] === '-') { // string or comment
			return $m;

		} else { // command
			$cmd = ltrim(strtoupper($m), "\t\n\r (");
			$this->arrayMode = self::ARRAY_MODES[$cmd] ?? null;
			$this->useParams = isset(self::PARAMETRIC_COMMANDS[$cmd]) || $this->useParams;
			return $m;
		}
	}


	private function formatValue($value, string $mode = null): string
	{
		if (!$mode || $mode === self::MODE_AUTO) {
			if (is_scalar($value) || is_resource($value)) {
				if ($this->useParams) {
					$this->remaining[] = $value;
					return '?';

				} elseif (is_int($value) || is_bool($value)) {
					return (string) (int) $value;

				} elseif (is_float($value)) {
					return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');

				} elseif (is_resource($value)) {
					return $this->connection->quote(stream_get_contents($value));

				} else {
					return $this->connection->quote((string) $value);
				}

			} elseif ($value === null) {
				return 'NULL';

			} elseif ($value instanceof Table\IRow) {
				$this->remaining[] = $value->getPrimary();
				return '?';

			} elseif ($value instanceof SqlLiteral) {
				$prep = clone $this;
				[$res, $params] = $prep->process(array_merge([$value->__toString()], $value->getParameters()), $this->useParams);
				$this->remaining = array_merge($this->remaining, $params);
				return $res;

			} elseif ($value instanceof \DateTimeInterface) {
				return $this->driver->formatDateTime($value);

			} elseif ($value instanceof \DateInterval) {
				return $this->driver->formatDateInterval($value);

			} elseif (is_object($value) && method_exists($value, '__toString')) {
				$this->remaining[] = (string) $value;
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
			$vx = $kx = [];
			if ($mode === self::MODE_AUTO) {
				$mode = $this->arrayMode;
			}

			if ($mode === self::MODE_VALUES) { // (key, key, ...) VALUES (value, value, ...)
				if (array_key_exists(0, $value)) { // multi-insert
					if (!is_array($value[0]) && !$value[0] instanceof Row) {
						throw new Nette\InvalidArgumentException('Automaticaly detected multi-insert, but values aren\'t array. If you need try to change mode like "?[' . implode('|', self::MODES) . ']". Mode "' . $mode . '" was used.');
					}
					foreach ($value[0] as $k => $v) {
						$kx[] = $this->delimite($k);
					}
					foreach ($value as $val) {
						$vx2 = [];
						foreach ($value[0] as $k => $foo) {
							$vx2[] = $this->formatValue($val[$k]);
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

			} elseif (!$mode || $mode === self::MODE_SET) {
				foreach ($value as $k => $v) {
					if (is_int($k)) { // value, value, ... OR (1, 2), (3, 4)
						$vx[] = is_array($v)
							? '(' . $this->formatValue($v) . ')'
							: $this->formatValue($v);
					} elseif (substr($k, -1) === '=') { // key+=value, key-=value, ...
						$k2 = $this->delimite(substr($k, 0, -2));
						$vx[] = $k2 . '=' . $k2 . ' ' . substr($k, -2, 1) . ' ' . $this->formatValue($v);
					} else { // key=value, key=value, ...
						$vx[] = $this->delimite($k) . '=' . $this->formatValue($v);
					}
				}
				return implode(', ', $vx);

			} elseif ($mode === self::MODE_AND || $mode === self::MODE_OR) { // (key [operator] value) AND ...
				foreach ($value as $k => $v) {
					if (is_int($k)) {
						$vx[] = $this->formatValue($v);
						continue;
					}
					[$k, $operator] = explode(' ', $k . ' ');
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
						$operator = $v === 'NULL'
							? ($operator === 'NOT' ? 'IS NOT' : ($operator ?: 'IS'))
							: ($operator ?: '=');
						$vx[] = $k . ' ' . $operator . ' ' . $v;
					}
				}
				return $value
					? '(' . implode(') ' . strtoupper($mode) . ' (', $vx) . ')'
					: '1=1';

			} elseif ($mode === self::MODE_ORDER) { // key, key DESC, ...
				foreach ($value as $k => $v) {
					$vx[] = $this->delimite($k) . ($v > 0 ? '' : ' DESC');
				}
				return implode(', ', $vx);

			} else {
				throw new Nette\InvalidArgumentException("Unknown placeholder ?$mode.");
			}

		} elseif (in_array($mode, self::MODES, true)) {
			$type = gettype($value);
			throw new Nette\InvalidArgumentException("Placeholder ?$mode expects array or Traversable object, $type given.");

		} elseif ($mode && $mode !== self::MODE_AUTO) {
			throw new Nette\InvalidArgumentException("Unknown placeholder ?$mode.");

		} else {
			throw new Nette\InvalidArgumentException('Unexpected type of parameter: ' . (is_object($value) ? get_class($value) : gettype($value)));
		}
	}


	private function delimite(string $name): string
	{
		return implode('.', array_map([$this->driver, 'delimite'], explode('.', $name)));
	}
}
