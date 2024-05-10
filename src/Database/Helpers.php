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


/**
 * Database helpers.
 */
class Helpers
{
	use Nette\StaticClass;

	/** maximum SQL length */
	public static int $maxLength = 100;
	public static array $typePatterns = [];


	/**
	 * Displays complete result set as HTML table for debug purposes.
	 */
	public static function dumpResult(ResultSet $result): void
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


	/**
	 * Common column type detection.
	 * @return array<Type::*>
	 */
	public static function detectTypes(\PDOStatement $statement): array
	{
		$types = [];
		$count = $statement->columnCount(); // driver must be meta-aware, see PHP bugs #53782, #54695
		for ($col = 0; $col < $count; $col++) {
			$meta = $statement->getColumnMeta($col);
			if (isset($meta['native_type'])) {
				$types[$meta['name']] = RowNormalizer::detectType($meta['native_type']);
			}
		}

		return $types;
	}


	/** @deprecated  use RowNormalizer::detectType() */
	public static function detectType(string $type): string
	{
		return RowNormalizer::detectType($type);
	}


	/** @deprecated  use RowNormalizer */
	public static function normalizeRow(array $row, ResultSet $resultSet): array
	{
		return (new RowNormalizer)($row, $resultSet);
	}


	/**
	 * Import SQL dump from file - extremely fast.
	 * @param  ?array<callable(int, ?float): void>  $onProgress
	 * @return int  count of commands
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
		$pdo = $connection->getPdo(); // native query without logging
		while (($s = fgets($handle)) !== false) {
			$size += strlen($s);
			if (!strncasecmp($s, 'DELIMITER ', 10)) {
				$delimiter = trim(substr($s, 10));

			} elseif (str_ends_with($ts = rtrim($s), $delimiter)) {
				$sql .= substr($ts, 0, -strlen($delimiter));
				$pdo->exec($sql);
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
			$pdo->exec($sql);
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
	 * Reformat source to key -> value pairs.
	 */
	public static function toPairs(array $rows, string|int|null $key = null, string|int|null $value = null): array
	{
		if (!$rows) {
			return [];
		}

		$keys = array_keys((array) reset($rows));
		if (!count($keys)) {
			throw new \LogicException('Result set does not contain any column.');

		} elseif ($key === null && $value === null) {
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
		} else {
			foreach ($rows as $row) {
				$return[(string) $row[$key]] = ($value === null ? $row : $row[$value]);
			}
		}

		return $return;
	}


	/**
	 * Finds duplicate columns in select statement
	 */
	public static function findDuplicates(ResultDriver $result): string
	{
		$cols = [];
		for ($i = 0; $i < $result->getColumnCount(); $i++) {
			$meta = $result->getColumnMeta($i);
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
}
