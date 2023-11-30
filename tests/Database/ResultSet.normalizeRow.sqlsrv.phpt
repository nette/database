<?php

/**
 * Test: Nette\Database\ResultSet::normalizeRow()
 * @dataProvider? databases.ini  sqlsrv
 */

declare(strict_types=1);

use Nette\Utils\DateTime;
use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlsrv-nette_test3.sql');


$res = $connection->query('SELECT * FROM types');

Assert::equal([
	'bigint' => 1,
	'binary_3' => "\x00\x00\xFF",
	'bit' => '1',
	'char_5' => 'a    ',
	'date' => new DateTime('2012-10-13 00:00:00'),
	'datetime' => new DateTime('2012-10-13 10:10:10'),
	'datetime2' => new DateTime('2012-10-13 10:10:10'),
	'decimal' => 1.0,
	'float' => 1.1,
	'geography' => "\xe6\x10\x00\x00\x01\x14\x87\x16\xd9\xce\xf7\xd3G@\xd7\xa3p=\n\x97^\xc0\x87\x16\xd9\xce\xf7\xd3G@\xcb\xa1E\xb6\xf3\x95^\xc0",
	'geometry' => "\x00\x00\x00\x00\x01\x04\x03\x00\x00\x00\x00\x00\x00\x00\x00\x00Y@\x00\x00\x00\x00\x00\x00Y@\x00\x00\x00\x00\x00\x004@\x00\x00\x00\x00\x00\x80f@\x00\x00\x00\x00\x00\x80f@\x00\x00\x00\x00\x00\x80f@\x01\x00\x00\x00\x01\x00\x00\x00\x00\x01\x00\x00\x00\xff\xff\xff\xff\x00\x00\x00\x00\x02",
	'hierarchyid' => 'X',
	'int' => 1,
	'money' => 1111.1,
	'nchar' => 'a',
	'ntext' => 'a',
	'numeric_10_0' => 1.0,
	'numeric_10_2' => 1.1,
	'nvarchar' => 'a',
	'real' => 1.1,
	'smalldatetime' => new DateTime('2012-10-13 10:10:00'),
	'smallint' => 1,
	'smallmoney' => 1.1,
	'text' => 'a',
	'time' => new DateTime('0001-01-01 10:10:10'),
	'tinyint' => 1,
	'uniqueidentifier' => '678E9994-A048-11E2-9030-003048D30C14',
	'varbinary' => "\x01",
	'varchar' => 'a',
	'xml' => '<doc/>',
], (array) $res->fetch());

Assert::equal([
	'bigint' => 0,
	'binary_3' => "\x00\x00\x00",
	'bit' => '0',
	'char_5' => '     ',
	'date' => new DateTime('0001-01-01 00:00:00'),
	'datetime' => new DateTime('1753-01-01 00:00:00'),
	'datetime2' => new DateTime('0001-01-01 00:00:00'),
	'decimal' => 0.0,
	'float' => 0.5,
	'geography' => null,
	'geometry' => null,
	'hierarchyid' => '',
	'int' => 0,
	'money' => 0.0,
	'nchar' => ' ',
	'ntext' => '',
	'numeric_10_0' => 0.0,
	'numeric_10_2' => 0.5,
	'nvarchar' => '',
	'real' => 0.0,
	'smalldatetime' => new DateTime('1900-01-01 00:00:00'),
	'smallint' => 0,
	'smallmoney' => 0.5,
	'text' => '',
	'time' => new DateTime('0001-01-01 00:00:00'),
	'tinyint' => 0,
	'uniqueidentifier' => '00000000-0000-0000-0000-000000000000',
	'varbinary' => "\x00",
	'varchar' => '',
	'xml' => '',
], (array) $res->fetch());

Assert::same([
	'bigint' => null,
	'binary_3' => null,
	'bit' => null,
	'char_5' => null,
	'date' => null,
	'datetime' => null,
	'datetime2' => null,
	'decimal' => null,
	'float' => null,
	'geography' => null,
	'geometry' => null,
	'hierarchyid' => null,
	'int' => null,
	'money' => null,
	'nchar' => null,
	'ntext' => null,
	'numeric_10_0' => null,
	'numeric_10_2' => null,
	'nvarchar' => null,
	'real' => null,
	'smalldatetime' => null,
	'smallint' => null,
	'smallmoney' => null,
	'text' => null,
	'time' => null,
	'tinyint' => null,
	'uniqueidentifier' => null,
	'varbinary' => null,
	'varchar' => null,
	'xml' => null,
], (array) $res->fetch());


$res = $connection->query('SELECT [int] AS a, [text] AS a FROM types');

Assert::same([
	'a' => 'a',
], (array) @$res->fetch());


function isTimestamp($str)
{
	return is_string($str) && str_starts_with($str, "\x00\x00\x00\x00");
}


$row = (array) $connection->query('SELECT [datetimeoffset], CAST([sql_variant] AS int) AS [sql_variant], [timestamp] FROM types2 WHERE id = 1')->fetch();
Assert::type('DateTime', $row['datetimeoffset']);
Assert::same($row['datetimeoffset']->format('Y-m-d H:i:s P'), '2012-10-13 10:10:10 +02:00');
Assert::same($row['sql_variant'], 123456);
Assert::true(isTimestamp($row['timestamp']));

$row = (array) $connection->query('SELECT [datetimeoffset], CAST([sql_variant] AS varchar) AS [sql_variant], [timestamp] FROM types2 WHERE id = 2')->fetch();
Assert::type('DateTime', $row['datetimeoffset']);
Assert::same($row['datetimeoffset']->format('Y-m-d H:i:s P'), '0001-01-01 00:00:00 +00:00');
Assert::same($row['sql_variant'], 'abcd');
Assert::true(isTimestamp($row['timestamp']));

$row = (array) $connection->query('SELECT [datetimeoffset], CAST([sql_variant] AS int) AS [sql_variant], [timestamp] FROM types2 WHERE id = 3')->fetch();
Assert::same($row['datetimeoffset'], null);
Assert::same($row['sql_variant'], null);
Assert::true(isTimestamp($row['timestamp']));
