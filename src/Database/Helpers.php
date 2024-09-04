<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Database;

use Nette;
use Nette\Bridges\DatabaseTracy\ConnectionPanel;
use Tracy;
use function array_filter, array_keys, array_unique, count, fclose, fgets, fopen, fstat, get_resource_type, htmlspecialchars, implode, is_bool, is_float, is_resource, is_string, preg_last_error, preg_match, preg_replace, preg_replace_callback, reset, rtrim, set_time_limit, str_ends_with, str_starts_with, stream_get_meta_data, strlen, strncasecmp, substr, trim, wordwrap;
use const ENT_IGNORE, ENT_NOQUOTES, PREG_UNMATCHED_AS_NULL;


/**
 * Database utility functions.
 */
class Helpers
{
	use Nette\StaticClass;

	/** maximum SQL length */
	public static int $maxLength = 100;


	/**
	 * Displays result set as HTML table.
	 */
	public static function dumpResult(Result $result): void
	{
		echo "\n<table class=\"dump\">\n<caption>" . htmlspecialchars($result->getQueryString(), ENT_IGNORE, 'UTF-8') . "</caption>\n";
		if (!$result->getColumnCount()) {
			echo "\t<tr>\n\t\t<th>Affected rows:</th>\n\t\t<td>", $result->getRowCount(), "</td>\n\t</tr>\n</table>\n";
			return;
		}

		$i = 0;
		foreach ($result as $row) {
			if ($i === 0) {
				echo "<thead>\n\t<tr>\n\t\t<th>#row</th>\n";
				foreach ($row as $col => $foo) {
					echo "\t\t<th>" . htmlspecialchars($col, ENT_NOQUOTES, 'UTF-8') . "</th>\n";
				}

				echo "\t</tr>\n</thead>\n<tbody>\n";
			}

			echo "\t<tr>\n\t\t<th>", $i, "</th>\n";
			foreach ($row as $col) {
				if (is_bool($col)) {
					$s = $col ? 'TRUE' : 'FALSE';
				} elseif ($col === null) {
					$s = 'NULL';
				} else {
					$s = (string) $col;
				}

				echo "\t\t<td>", htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8'), "</td>\n";
			}

			echo "\t</tr>\n";
			$i++;
		}

		if ($i === 0) {
			echo "\t<tr>\n\t\t<td><em>empty result set</em></td>\n\t</tr>\n</table>\n";
		} else {
			echo "</tbody>\n</table>\n";
		}
	}


	/**
	 * Returns syntax highlighted SQL command.
	 */
	public static function dumpSql(string $sql, ?array $params = null, ?Connection $connection = null): string
	{
		$keywords1 = 'SELECT|(?:ON\s+DUPLICATE\s+KEY)?UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|DELETE|CALL|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE';
		$keywords2 = 'ALL|DISTINCT|DISTINCTROW|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|[RI]?LIKE|REGEXP|TRUE|FALSE';

		// insert new lines
		$sql = " $sql ";
		$sql = preg_replace("#(?<=[\\s,(])($keywords1)(?=[\\s,)])#i", "\n\$1", $sql);

		// reduce spaces
		$sql = preg_replace('#[ \t]{2,}#', ' ', $sql);

		$sql = wordwrap($sql, 100);
		$sql = preg_replace('#([ \t]*\r?\n){2,}#', "\n", $sql);

		// syntax highlight
		$sql = htmlspecialchars($sql, ENT_IGNORE, 'UTF-8');
		$sql = preg_replace_callback("#(/\\*.+?\\*/)|(\\*\\*.+?\\*\\*)|(?<=[\\s,(])($keywords1)(?=[\\s,)])|(?<=[\\s,(=])($keywords2)(?=[\\s,)=])#is", function (array $matches) {
			if (!empty($matches[1])) { // comment
				return '<em style="color:gray">' . $matches[1] . '</em>';

			} elseif (!empty($matches[2])) { // error
				return '<strong style="color:red">' . $matches[2] . '</strong>';

			} elseif (!empty($matches[3])) { // most important keywords
				return '<strong style="color:blue">' . $matches[3] . '</strong>';

			} elseif (!empty($matches[4])) { // other keywords
				return '<strong style="color:green">' . $matches[4] . '</strong>';
			}
		}, $sql);

		// parameters
		$sql = preg_replace_callback('#\?#', function () use ($params, $connection): string {
			static $i = 0;
			if (!isset($params[$i])) {
				return '?';
			}

			$param = $params[$i++];
			if (
				is_string($param)
				&& (
					preg_match('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]#u', $param)
					|| preg_last_error()
				)
			) {
				return '<i title="Length ' . strlen($param) . ' bytes">&lt;binary&gt;</i>';

			} elseif (is_string($param)) {
				$length = Nette\Utils\Strings::length($param);
				$truncated = Nette\Utils\Strings::truncate($param, self::$maxLength);
				$text = htmlspecialchars($connection ? $connection->quote($truncated) : '\'' . $truncated . '\'', ENT_NOQUOTES, 'UTF-8');
				return '<span title="Length ' . $length . ' characters">' . $text . '</span>';

			} elseif (is_resource($param)) {
				$type = get_resource_type($param);
				if ($type === 'stream') {
					$info = stream_get_meta_data($param);
				}

				return '<i' . (isset($info['uri']) ? ' title="' . htmlspecialchars($info['uri'], ENT_NOQUOTES, 'UTF-8') . '"' : null)
					. '>&lt;' . htmlspecialchars($type, ENT_NOQUOTES, 'UTF-8') . ' resource&gt;</i> ';

			} elseif (is_bool($param)) {
				return (string) (int) $param;

			} else {
				return htmlspecialchars((string) $param, ENT_NOQUOTES, 'UTF-8');
			}
		}, $sql);

		return '<pre class="dump">' . trim($sql) . "</pre>\n";
	}


	/** @internal */
	public static function normalizeRow(
		array $row,
		Result $resultSet,
	): array
	{
		$engine = @$resultSet->getConnection()->getDatabaseEngine();
		$converter = @$resultSet->getConnection()->getTypeConverter();
		foreach ($resultSet->getColumnsMeta() as $key => $meta) {
			$value = $row[$key];
			$row[$key] = isset($value, $converter)
				? $engine->convertToPhp($value, $meta, $converter)
				: $value;
		}
		return $row;
	}


	/**
	 * Imports SQL dump from file.
	 * @param  ?array<callable(int, ?float): void>  $onProgress  Called after each query
	 * @return int  Number of executed commands
	 * @throws Nette\FileNotFoundException
	 */
	public static function loadFromFile(Connection $connection, string $file, ?callable $onProgress = null): int
	{
		@set_time_limit(0); // @ function may be disabled

		$handle = @fopen($file, 'r'); // @ is escalated to exception
		if (!$handle) {
			throw new Nette\FileNotFoundException("Cannot open file '$file'.");
		}

		$stat = fstat($handle);
		$count = $size = 0;
		$delimiter = ';';
		$sql = '';
		$connection = $connection->getConnection(); // native query without logging
		while (($s = fgets($handle)) !== false) {
			$size += strlen($s);
			if (!strncasecmp($s, 'DELIMITER ', 10)) {
				$delimiter = trim(substr($s, 10));

			} elseif (str_ends_with($ts = rtrim($s), $delimiter)) {
				$sql .= substr($ts, 0, -strlen($delimiter));
				$connection->execute($sql);
				$sql = '';
				$count++;
				if ($onProgress) {
					$onProgress($count, isset($stat['size']) ? $size * 100 / $stat['size'] : null);
				}
			} else {
				$sql .= $s;
			}
		}

		if (rtrim($sql) !== '') {
			$connection->execute($sql);
			$count++;
			if ($onProgress) {
				$onProgress($count, isset($stat['size']) ? 100 : null);
			}
		}

		fclose($handle);
		return $count;
	}


	/** @deprecated  use Nette\Bridges\DatabaseTracy\ConnectionPanel::initialize() */
	public static function createDebugPanel(
		Connection $connection,
		bool $explain,
		string $name,
		Tracy\Bar $bar,
		Tracy\BlueScreen $blueScreen,
	): ?ConnectionPanel
	{
		trigger_error(__METHOD__ . '() is deprecated, use Nette\Bridges\DatabaseTracy\ConnectionPanel::initialize()', E_USER_DEPRECATED);
		return ConnectionPanel::initialize($connection, true, $name, $explain, $bar, $blueScreen);
	}


	/** @deprecated  use Nette\Bridges\DatabaseTracy\ConnectionPanel::initialize() */
	public static function initializeTracy(
		Connection $connection,
		bool $addBarPanel = false,
		string $name = '',
		bool $explain = true,
		?Tracy\Bar $bar = null,
		?Tracy\BlueScreen $blueScreen = null,
	): ?ConnectionPanel
	{
		trigger_error(__METHOD__ . '() is deprecated, use Nette\Bridges\DatabaseTracy\ConnectionPanel::initialize()', E_USER_DEPRECATED);
		return ConnectionPanel::initialize($connection, $addBarPanel, $name, $explain, $bar, $blueScreen);
	}


	/**
	 * Converts rows to key-value pairs.
	 * @param  array<Row|Table\ActiveRow|array<string, mixed>>  $rows
	 * @return array<mixed, mixed>
	 */
	public static function toPairs(array $rows, string|int|\Closure|null $key, string|int|null $value): array
	{
		if ($key === null && $value === null) {
			if (!$rows) {
				return [];
			}
			$keys = array_keys((array) reset($rows));
			if (!count($keys)) {
				throw new \LogicException('Result set does not contain any column.');
			}
			if (count($keys) === 1) {
				[$value] = $keys;
			} else {
				[$key, $value] = $keys;
			}
		}

		$return = [];
		if ($key === null) {
			foreach ($rows as $row) {
				$return[] = ($value === null ? $row : $row[$value]);
			}
		} elseif ($key instanceof \Closure) {
			foreach ($rows as $row) {
				$tuple = $key($row);
				if (count($tuple) === 1) {
					$return[] = $tuple[0];
				} else {
					$return[$tuple[0]] = $tuple[1];
				}
			}
		} else {
			foreach ($rows as $row) {
				$return[(string) $row[$key]] = ($value === null ? $row : $row[$value]);
			}
		}

		return $return;
	}


	/**
	 * Returns duplicate columns from result set.
	 */
	public static function findDuplicates(\PDOStatement $statement): string
	{
		$cols = [];
		for ($i = 0; $i < $statement->columnCount(); $i++) {
			$meta = $statement->getColumnMeta($i);
			$cols[$meta['name']][] = $meta['table'] ?? '';
		}

		$duplicates = [];
		foreach ($cols as $name => $tables) {
			if (count($tables) > 1) {
				$tables = array_filter(array_unique($tables));
				$duplicates[] = "'$name'" . ($tables ? ' (from ' . implode(', ', $tables) . ')' : '');
			}
		}

		return implode(', ', $duplicates);
	}


	/** @return array{type: ?string, size: ?int, scale: ?int, parameters: ?string} */
	public static function parseColumnType(string $type): array
	{
		preg_match('/^([^(]+)(?:\((?:(\d+)(?:,(\d+))?|([^)]+))\))?/', $type, $m, PREG_UNMATCHED_AS_NULL);
		return [
			'type' => $m[1] ?? null,
			'size' => isset($m[2]) ? (int) $m[2] : null,
			'scale' => isset($m[3]) ? (int) $m[3] : null,
			'parameters' => $m[4] ?? null,
		];
	}
}
