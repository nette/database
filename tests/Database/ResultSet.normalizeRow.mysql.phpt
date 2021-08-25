<?php

/**
 * Test: Nette\Database\ResultSet::normalizeRow()
 * @dataProvider? databases.ini  mysql
 */

declare(strict_types=1);

use Nette\Utils\DateTime;
use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');


$res = $connection->query('SELECT * FROM types');

Assert::equal([
	'unsigned_int' => 1,
	'int' => 1,
	'smallint' => 1,
	'tinyint' => 1,
	'mediumint' => 1,
	'bigint' => 1,
	'bit' => PHP_VERSION_ID < 80100 ? '1' : 1,
	'decimal' => 1.0,
	'decimal2' => 1.1,
	'float' => 1.0,
	'double' => 1.1,
	'date' => new DateTime('2012-10-13'),
	'time' => new DateInterval('PT30H10M10S'),
	'datetime' => new DateTime('2012-10-13 10:10:10'),
	'timestamp' => new DateTime('2012-10-13 10:10:10'),
	'year' => 2012,
	'char' => 'a',
	'varchar' => 'a',
	'binary' => 'a',
	'varbinary' => 'a',
	'blob' => 'a',
	'tinyblob' => 'a',
	'mediumblob' => 'a',
	'longblob' => 'a',
	'text' => 'a',
	'tinytext' => 'a',
	'mediumtext' => 'a',
	'longtext' => 'a',
	'enum' => 'a',
	'set' => 'a',
], (array) $res->fetch());

Assert::equal([
	'unsigned_int' => 0,
	'int' => 0,
	'smallint' => 0,
	'tinyint' => 0,
	'mediumint' => 0,
	'bigint' => 0,
	'bit' => PHP_VERSION_ID < 80100 ? '0' : 0,
	'decimal' => 0.0,
	'decimal2' => 0.5,
	'float' => 0.5,
	'double' => 0.5,
	'date' => new DateTime('0000-00-00 00:00:00'),
	'time' => new DateInterval('P0D'),
	'datetime' => new DateTime('0000-00-00 00:00:00'),
	'timestamp' => new DateTime('0000-00-00 00:00:00'),
	'year' => 2000,
	'char' => '',
	'varchar' => '',
	'binary' => "\x00",
	'varbinary' => '',
	'blob' => '',
	'tinyblob' => '',
	'mediumblob' => '',
	'longblob' => '',
	'text' => '',
	'tinytext' => '',
	'mediumtext' => '',
	'longtext' => '',
	'enum' => 'b',
	'set' => '',
], (array) $res->fetch());

Assert::same([
	'unsigned_int' => null,
	'int' => null,
	'smallint' => null,
	'tinyint' => null,
	'mediumint' => null,
	'bigint' => null,
	'bit' => null,
	'decimal' => null,
	'decimal2' => null,
	'float' => null,
	'double' => null,
	'date' => null,
	'time' => null,
	'datetime' => null,
	'timestamp' => null,
	'year' => null,
	'char' => null,
	'varchar' => null,
	'binary' => null,
	'varbinary' => null,
	'blob' => null,
	'tinyblob' => null,
	'mediumblob' => null,
	'longblob' => null,
	'text' => null,
	'tinytext' => null,
	'mediumtext' => null,
	'longtext' => null,
	'enum' => null,
	'set' => null,
], (array) $res->fetch());


$res = $connection->query('SELECT `int` AS a, `char` AS a FROM types');

Assert::same([
	'a' => 'a',
], (array) @$res->fetch());


$res = $connection->query('SELECT sec_to_time(avg(time_to_sec(`time`))) AS `avg_time` FROM `avgs`');

$avgTime = new DateInterval('PT10H10M10S');
$avgTime->f = 0.5;

Assert::equal([
	'avg_time' => $avgTime,
], (array) $res->fetch());


$connection->getPdo()->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

$res = $connection->query('SELECT `int`, `decimal`, `decimal2`, `float`, `double` FROM types');

Assert::equal([
	'int' => 1,
	'decimal' => 1.0,
	'decimal2' => 1.1,
	'float' => 1.0,
	'double' => 1.1,
], (array) $res->fetch());
