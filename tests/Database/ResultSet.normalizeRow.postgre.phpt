<?php

/**
 * Test: Nette\Database\ResultSet::normalizeRow()
 * @dataProvider? databases.ini  postgresql
 */

declare(strict_types=1);

use Nette\Database\DateTime;
use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/pgsql-nette_test3.sql');


$connection->query("SET TIMEZONE TO 'UTC'");

$res = $connection->query('SELECT * FROM types');
$row = $res->fetch();

Assert::equal([
	'smallint' => 1,
	'integer' => 1,
	'bigint' => 1,
	'numeric' => 1.0,
	'real' => 1.1,
	'double' => 1.11,
	'money' => 0.0,
	'bool' => true,
	'date' => new DateTime('2012-10-13'),
	'time' => new DateTime('0001-01-01 10:10:10'),
	'timestamp' => new DateTime('2012-10-13 10:10:10'),
	'timestampZone' => new DateTime('2012-10-13 09:10:10+00'),
	'interval' => '1 year',
	'character' => 'a                             ',
	'character_varying' => 'a',
	'text' => 'a',
	'tsquery' => '\'a\'',
	'tsvector' => '\'a\'',
	'uuid' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
	'xml' => 'a',
	'cidr' => '192.168.1.0/24',
	'inet' => '192.168.1.1',
	'macaddr' => '08:00:2b:01:02:03',
	'bit' => '1',
	'bit_varying' => '1',
	'bytea' => null,
	'box' => '(30,40),(10,20)',
	'circle' => '<(10,20),30>',
	'lseg' => '[(10,20),(30,40)]',
	'path' => '((10,20),(30,40))',
	'point' => '(10,20)',
	'polygon' => '((10,20),(30,40))',
], (array) $row);

Assert::same([
	'smallint' => 0,
	'integer' => 0,
	'bigint' => 0,
	'numeric' => 0.0,
	'real' => 0.0,
	'double' => 0.0,
	'money' => null,
	'bool' => false,
	'date' => null,
	'time' => null,
	'timestamp' => null,
	'timestampZone' => null,
	'interval' => '00:00:00',
	'character' => '                              ',
	'character_varying' => '',
	'text' => '',
	'tsquery' => '',
	'tsvector' => '',
	'uuid' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
	'xml' => 'a',
	'cidr' => '192.168.1.0/24',
	'inet' => '192.168.1.1',
	'macaddr' => '08:00:2b:01:02:03',
	'bit' => '0',
	'bit_varying' => '0',
	'bytea' => null,
	'box' => '(30,40),(10,20)',
	'circle' => '<(10,20),30>',
	'lseg' => '[(10,20),(30,40)]',
	'path' => '((10,20),(30,40))',
	'point' => '(10,20)',
	'polygon' => '((10,20),(30,40))',
], (array) $res->fetch());

Assert::same([
	'smallint' => null,
	'integer' => null,
	'bigint' => null,
	'numeric' => null,
	'real' => null,
	'double' => null,
	'money' => null,
	'bool' => null,
	'date' => null,
	'time' => null,
	'timestamp' => null,
	'timestampZone' => null,
	'interval' => null,
	'character' => null,
	'character_varying' => null,
	'text' => null,
	'tsquery' => null,
	'tsvector' => null,
	'uuid' => null,
	'xml' => null,
	'cidr' => null,
	'inet' => null,
	'macaddr' => null,
	'bit' => null,
	'bit_varying' => null,
	'bytea' => null,
	'box' => null,
	'circle' => null,
	'lseg' => null,
	'path' => null,
	'point' => null,
	'polygon' => null,
], (array) $res->fetch());


$res = $connection->query('SELECT "integer" AS a, "text" AS a FROM types');

Assert::same([
	'a' => 'a',
], (array) @$res->fetch());


$res = $connection->query('SELECT SUM("integer") AS int_sum, AVG("integer") AS int_avg, SUM("double") AS float_sum, AVG("double") AS float_avg FROM types WHERE "integer" = 1 GROUP BY "integer"');
Assert::equal([
	'int_sum' => 1,
	'int_avg' => 1.0,
	'float_sum' => 1.11,
	'float_avg' => 1.11,
], (array) $res->fetch());
