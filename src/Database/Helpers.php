<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;
use Tracy;


/**
 * Database helpers.
 */
class Helpers
{
	use Nette\StaticClass;

	/** @var int maximum SQL length */
	public static $maxLength = 100;

	/** @var array */
	public static $typePatterns = [
		'^_' => IStructure::FIELD_TEXT, // PostgreSQL arrays
		'(TINY|SMALL|SHORT|MEDIUM|BIG|LONG)(INT)?|INT(EGER|\d+| IDENTITY)?|(SMALL|BIG|)SERIAL\d*|COUNTER|YEAR|BYTE|LONGLONG|UNSIGNED BIG INT' => IStructure::FIELD_INTEGER,
		'(NEW)?DEC(IMAL)?(\(.*)?|NUMERIC|REAL|DOUBLE( PRECISION)?|FLOAT\d*|(SMALL)?MONEY|CURRENCY|NUMBER' => IStructure::FIELD_FLOAT,
		'BOOL(EAN)?' => IStructure::FIELD_BOOL,
		'TIME' => IStructure::FIELD_TIME,
		'DATE' => IStructure::FIELD_DATE,
		'(SMALL)?DATETIME(OFFSET)?\d*|TIME(STAMP)?' => IStructure::FIELD_DATETIME,
		'BYTEA|(TINY|MEDIUM|LONG|)BLOB|(LONG )?(VAR)?BINARY|IMAGE' => IStructure::FIELD_BINARY,
	];


	/**
	 * Displays complete result set as HTML table for debug purposes.
	 * @return void
	 */
	public static function dumpResult(ResultSet $result)
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
				echo "\t\t<td>", htmlspecialchars($col, ENT_NOQUOTES, 'UTF-8'), "</td>\n";
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
	 * @param  string
	 * @return string
	 */
	public static function dumpSql($sql, array $params = null, Connection $connection = null)
	{
		static $keywords1 = 'SELECT|(?:ON\s+DUPLICATE\s+KEY)?UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|DELETE|CALL|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE';
		static $keywords2 = 'ALL|DISTINCT|DISTINCTROW|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|[RI]?LIKE|REGEXP|TRUE|FALSE';

		// insert new lines
		$sql = " $sql ";
		$sql = preg_replace("#(?<=[\\s,(])($keywords1)(?=[\\s,)])#i", "\n\$1", $sql);

		// reduce spaces
		$sql = preg_replace('#[ \t]{2,}#', ' ', $sql);

		$sql = wordwrap($sql, 100);
		$sql = preg_replace('#([ \t]*\r?\n){2,}#', "\n", $sql);

		// syntax highlight
		$sql = htmlspecialchars($sql, ENT_IGNORE, 'UTF-8');
		$sql = preg_replace_callback("#(/\\*.+?\\*/)|(\\*\\*.+?\\*\\*)|(?<=[\\s,(])($keywords1)(?=[\\s,)])|(?<=[\\s,(=])($keywords2)(?=[\\s,)=])#is", function ($matches) {
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
		$sql = preg_replace_callback('#\?#', function () use ($params, $connection) {
			static $i = 0;
			if (!isset($params[$i])) {
				return '?';
			}
			$param = $params[$i++];
			if (is_string($param) && (preg_match('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]#u', $param) || preg_last_error())) {
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

			} else {
				return htmlspecialchars($param, ENT_NOQUOTES, 'UTF-8');
			}
		}, $sql);

		return '<pre class="dump">' . trim($sql) . "</pre>\n";
	}


	/**
	 * Common column type detection.
	 * @return array
	 */
	public static function detectTypes(\PDOStatement $statement)
	{
		$types = [];
		$count = $statement->columnCount(); // driver must be meta-aware, see PHP bugs #53782, #54695
		for ($col = 0; $col < $count; $col++) {
			$meta = $statement->getColumnMeta($col);
			if (isset($meta['native_type'])) {
				$types[$meta['name']] = self::detectType($meta['native_type']);
			}
		}
		return $types;
	}


	/**
	 * Heuristic column type detection.
	 * @param  string
	 * @return string
	 * @internal
	 */
	public static function detectType($type)
	{
		static $cache;
		if (!isset($cache[$type])) {
			$cache[$type] = 'string';
			foreach (self::$typePatterns as $s => $val) {
				if (preg_match("#^($s)$#i", $type)) {
					return $cache[$type] = $val;
				}
			}
		}
		return $cache[$type];
	}


	/**
	 * Import SQL dump from file - extremely fast.
	 * @return int  count of commands
	 */
	public static function loadFromFile(Connection $connection, $file)
	{
		@set_time_limit(0); // @ function may be disabled

		$handle = @fopen($file, 'r'); // @ is escalated to exception
		if (!$handle) {
			throw new Nette\FileNotFoundException("Cannot open file '$file'.");
		}

		$count = 0;
		$delimiter = ';';
		$sql = '';
		$pdo = $connection->getPdo(); // native query without logging
		while (!feof($handle)) {
			$s = rtrim(fgets($handle));
			if (!strncasecmp($s, 'DELIMITER ', 10)) {
				$delimiter = substr($s, 10);

			} elseif (substr($s, -strlen($delimiter)) === $delimiter) {
				$sql .= substr($s, 0, -strlen($delimiter));
				$pdo->exec($sql);
				$sql = '';
				$count++;

			} else {
				$sql .= $s . "\n";
			}
		}
		if (trim($sql) !== '') {
			$pdo->exec($sql);
			$count++;
		}
		fclose($handle);
		return $count;
	}


	public static function createDebugPanel($connection, $explain = true, $name = null)
	{
		$panel = new Nette\Bridges\DatabaseTracy\ConnectionPanel($connection);
		$panel->explain = $explain;
		$panel->name = $name;
		Tracy\Debugger::getBar()->addPanel($panel);
		return $panel;
	}


	/**
	 * Reformat source to key -> value pairs.
	 * @return array
	 */
	public static function toPairs(array $rows, $key = null, $value = null)
	{
		if (!$rows) {
			return [];
		}

		$keys = array_keys((array) reset($rows));
		if (!count($keys)) {
			throw new \LogicException('Result set does not contain any column.');

		} elseif ($key === null && $value === null) {
			if (count($keys) === 1) {
				list($value) = $keys;
			} else {
				list($key, $value) = $keys;
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
	 * @param  \PDOStatement
	 * @return string
	 */
	public static function findDuplicates(\PDOStatement $statement)
	{
		$cols = [];
		for ($i = 0; $i < $statement->columnCount(); $i++) {
			$meta = $statement->getColumnMeta($i);
			$cols[$meta['name']][] = isset($meta['table']) ? $meta['table'] : '';
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
