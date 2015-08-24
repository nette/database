<?php

/**
 * Test: Nette\Database\ResultSet::normalizeRow()
 * @dataProvider? databases.ini  mysql
 */

use Tester\Assert;
use Nette\Utils\DateTime;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');


$res = $context->query('SELECT * FROM types');

Assert::equal([
	'unsigned_int' => 1,
	'int' => 1,
	'smallint' => 1,
	'tinyint' => 1,
	'mediumint' => 1,
	'bigint' => 1,
	'bit' => '1',
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
	'bit' => '0',
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
	'unsigned_int' => NULL,
	'int' => NULL,
	'smallint' => NULL,
	'tinyint' => NULL,
	'mediumint' => NULL,
	'bigint' => NULL,
	'bit' => NULL,
	'decimal' => NULL,
	'decimal2' => NULL,
	'float' => NULL,
	'double' => NULL,
	'date' => NULL,
	'time' => NULL,
	'datetime' => NULL,
	'timestamp' => NULL,
	'year' => NULL,
	'char' => NULL,
	'varchar' => NULL,
	'binary' => NULL,
	'varbinary' => NULL,
	'blob' => NULL,
	'tinyblob' => NULL,
	'mediumblob' => NULL,
	'longblob' => NULL,
	'text' => NULL,
	'tinytext' => NULL,
	'mediumtext' => NULL,
	'longtext' => NULL,
	'enum' => NULL,
	'set' => NULL,
], (array) $res->fetch());


$res = $context->query('SELECT `int` AS a, `char` AS a FROM types');

Assert::same([
	'a' => 'a',
], (array) @$res->fetch());
