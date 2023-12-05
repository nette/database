<?php

/**
 * Test: Nette\Database\ResultSet::normalizeRow()
 * @dataProvider? databases.ini  sqlite
 */

declare(strict_types=1);

use Nette\Utils\DateTime;
use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlite-nette_test3.sql');


$res = $connection->query('SELECT * FROM types');

Assert::equal([
	'int' => 1,
	'integer' => 1,
	'tinyint' => 1,
	'smallint' => 1,
	'mediumint' => 1,
	'bigint' => 1,
	'unsigned_big_int' => 1,
	'int2' => 1,
	'int8' => 1,
	'character_20' => 'a',
	'varchar_255' => 'a',
	'varying_character_255' => 'a',
	'nchar_55' => 'a',
	'native_character_70' => 'a',
	'nvarchar_100' => 'a',
	'text' => 'a',
	'clob' => 'a',
	'blob' => 'a',
	'real' => 1.1,
	'double' => 1.1,
	'double precision' => 1.1,
	'float' => 1.1,
	'numeric' => 1.1,
	'decimal_10_5' => 1.1,
	'boolean' => true,
	'date' => new DateTime('2012-10-13'),
	'datetime' => new DateTime('2012-10-13 10:10:10'),
], (array) $res->fetch());

Assert::equal([
	'int' => 0,
	'integer' => 0,
	'tinyint' => 0,
	'smallint' => 0,
	'mediumint' => 0,
	'bigint' => 0,
	'unsigned_big_int' => 0,
	'int2' => 0,
	'int8' => 0,
	'character_20' => '',
	'varchar_255' => '',
	'varying_character_255' => '',
	'nchar_55' => '',
	'native_character_70' => '',
	'nvarchar_100' => '',
	'text' => '',
	'clob' => '',
	'blob' => '',
	'real' => 0.5,
	'double' => 0.5,
	'double precision' => 0.5,
	'float' => 0.5,
	'numeric' => 0.5,
	'decimal_10_5' => 0.5,
	'boolean' => false,
	'date' => new DateTime('1970-01-01'),
	'datetime' => new DateTime('1970-01-01 00:00:00'),
], (array) $res->fetch());

Assert::same([
	'int' => null,
	'integer' => null,
	'tinyint' => null,
	'smallint' => null,
	'mediumint' => null,
	'bigint' => null,
	'unsigned_big_int' => null,
	'int2' => null,
	'int8' => null,
	'character_20' => null,
	'varchar_255' => null,
	'varying_character_255' => null,
	'nchar_55' => null,
	'native_character_70' => null,
	'nvarchar_100' => null,
	'text' => null,
	'clob' => null,
	'blob' => null,
	'real' => null,
	'double' => null,
	'double precision' => null,
	'float' => null,
	'numeric' => null,
	'decimal_10_5' => null,
	'boolean' => null,
	'date' => null,
	'datetime' => null,
], (array) $res->fetch());


$res = $connection->query('SELECT [int] AS a, [text] AS a FROM types');

Assert::same([
	'a' => 'a',
], (array) @$res->fetch());


$res = $connection->query('SELECT SUM([int]) AS int_sum, AVG([int]) AS int_avg, SUM([double]) AS float_sum, AVG([double]) AS float_avg FROM types WHERE [int] = 1 GROUP BY [int]');
Assert::equal([
	'int_sum' => 1,
	'int_avg' => 1.0,
	'float_sum' => 1.1,
	'float_avg' => 1.1,
], (array) $res->fetch());
