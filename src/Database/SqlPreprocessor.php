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
		ModeAnd = 'and',       // (key [operator] value) AND ...
		ModeOr = 'or',         // (key [operator] value) OR ...
		ModeSet = 'set',       // key=value, key=value, ...
		ModeValues = 'values', // (key, key, ...) VALUES (value, value, ...)
		ModeOrder = 'order',   // key, key DESC, ...
		ModeList = 'list',     // value, value, ...  |  (tuple), (tuple), ...
		ModeAuto = 'auto';     // arrayMode for arrays

	private const Modes = [self::ModeAnd, self::ModeOr, self::ModeSet, self::ModeValues, self::ModeOrder, self::ModeList];

	private const ArrayModes = [
		'INSERT' => self::ModeValues,
		'REPLACE' => self::ModeValues,
		'KEY UPDATE' => self::ModeSet,
		'SET' => self::ModeSet,
		'WHERE' => self::ModeAnd,
		'HAVING' => self::ModeAnd,
		'ORDER BY' => self::ModeOrder,
		'GROUP BY' => self::ModeOrder,
	];

	private const ParametricCommands = [
		'SELECT' => 1,
		'INSERT' => 1,
		'UPDATE' => 1,
		'DELETE' => 1,
		'REPLACE' => 1,
		'EXPLAIN' => 1,
	];

	/** @var Connection */
	private $connection;

	/** @var Driver */
	private $driver;

	/** @var array of input parameters */
	private $params;

	/** @var array of parameters to be processed by PDO */
	private $remaining;

	/** @var int */
	private $counter;

	/** @var bool */
	private $useParams;

	/** @var string|null values|set|and|order|items */
	private $arrayMode;


	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
		$this->driver = $connection->getDriver();
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
				$res[] = $this->formatValue($param, self::ModeAuto);

			} elseif (is_string($param) && $this->counter > $prev + 1) {
				$prev = $this->counter;
				$this->arrayMode = null;
				$res[] = Nette\Utils\Strings::replace(
					$param, /** @lang RegExp */
					'~
						\'[^\']*+\'
						|"[^"]*+"
						|\?[a-z]*
						|^\s*+(?:\(?\s*SELECT|INSERT|UPDATE|DELETE|REPLACE|EXPLAIN)\b
						|\b(?:SET|WHERE|HAVING|ORDER\ BY|GROUP\ BY|KEY\ UPDATE)(?=\s*$|\s*\?)
						|\bIN\s+(?:\?|\(\?\))
						|/\*.*?\*/
						|--[^\n]*
					~Dsix',
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

			return $this->formatValue($this->params[$this->counter++], substr($m, 1) ?: self::ModeAuto);

		} elseif ($m[0] === "'" || $m[0] === '"' || $m[0] === '/' || $m[0] === '-') { // string or comment
			return $m;

		} elseif (preg_match('~^IN\s~i', $m)) { // IN (?)
			if ($this->counter >= count($this->params)) {
				throw new Nette\InvalidArgumentException('There are more placeholders than passed parameters.');
			}

			$param = $this->params[$this->counter++];
			return 'IN (' . $this->formatValue($param, is_array($param) ? self::ModeList : null) . ')';

		} else { // command
			$cmd = ltrim(strtoupper($m), "\t\n\r (");
			$this->arrayMode = self::ArrayModes[$cmd] ?? null;
			$this->useParams = isset(self::ParametricCommands[$cmd]) || $this->useParams;
			return $m;
		}
	}


	private function formatValue($value, ?string $mode = null): string
	{
		if (!$mode || $mode === self::ModeAuto) {
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

			} elseif ($value instanceof Table\ActiveRow) {
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

		if ($value instanceof \Traversable && !$value instanceof Table\ActiveRow) {
			$value = iterator_to_array($value);
		}

		if ($mode && is_array($value)) {
			$vx = $kx = [];
			if ($mode === self::ModeAuto) {
				$mode = $this->arrayMode ?? self::ModeSet;
			}

			if ($mode === self::ModeValues) { // (key, key, ...) VALUES (value, value, ...)
				if (array_key_exists(0, $value)) { // multi-insert
					if (!is_array($value[0]) && !$value[0] instanceof Row) {
						throw new Nette\InvalidArgumentException(
							'Automaticaly detected multi-insert, but values aren\'t array. If you need try to change mode like "?['
							. implode('|', self::Modes) . ']". Mode "' . $mode . '" was used.'
						);
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

					$select = $this->driver->isSupported(Driver::SUPPORT_MULTI_INSERT_AS_SELECT);
					return '(' . implode(', ', $kx) . ($select ? ') SELECT ' : ') VALUES (')
						. implode($select ? ' UNION ALL SELECT ' : '), (', $vx) . ($select ? '' : ')');
				}

				foreach ($value as $k => $v) {
					$kx[] = $this->delimite($k);
					$vx[] = $this->formatValue($v);
				}

				return '(' . implode(', ', $kx) . ') VALUES (' . implode(', ', $vx) . ')';

			} elseif ($mode === self::ModeSet) {
				foreach ($value as $k => $v) {
					if (is_int($k)) { // value, value, ...
						$vx[] = $this->formatValue($v);
					} elseif (substr($k, -1) === '=') { // key+=value, key-=value, ...
						$k2 = $this->delimite(substr($k, 0, -2));
						$vx[] = $k2 . '=' . $k2 . ' ' . substr($k, -2, 1) . ' ' . $this->formatValue($v);
					} else { // key=value, key=value, ...
						$vx[] = $this->delimite($k) . '=' . $this->formatValue($v);
					}
				}

				return implode(', ', $vx);

			} elseif ($mode === self::ModeList) { // value, value, ...  |  (tuple), (tuple), ...
				foreach ($value as $k => $v) {
					$vx[] = is_array($v)
						? '(' . $this->formatValue($v, self::ModeList) . ')'
						: $this->formatValue($v);
				}

				return implode(', ', $vx);

			} elseif ($mode === self::ModeAnd || $mode === self::ModeOr) { // (key [operator] value) AND ...
				foreach ($value as $k => $v) {
					if (is_int($k)) {
						$vx[] = $this->formatValue($v);
						continue;
					}

					[$k, $operator] = explode(' ', $k . ' ');
					$k = $this->delimite($k);
					if (is_array($v)) {
						if ($v) {
							$vx[] = $k . ' ' . ($operator ? $operator . ' ' : '') . 'IN (' . $this->formatValue(array_values($v), self::ModeList) . ')';
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

			} elseif ($mode === self::ModeOrder) { // key, key DESC, ...
				foreach ($value as $k => $v) {
					$vx[] = $this->delimite($k) . ($v > 0 ? '' : ' DESC');
				}

				return implode(', ', $vx);

			} else {
				throw new Nette\InvalidArgumentException("Unknown placeholder ?$mode.");
			}
		} elseif (in_array($mode, self::Modes, true)) {
			$type = gettype($value);
			throw new Nette\InvalidArgumentException("Placeholder ?$mode expects array or Traversable object, $type given.");

		} elseif ($mode && $mode !== self::ModeAuto) {
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
