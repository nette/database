<?php

/**
 * Test: Nette\Database\Connection::getInsertId()
 * @dataProvider? databases.ini  sqlite
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();

$connection->query('
	CREATE TABLE [noprimarykey] (
		[col] INTEGER
	)
');

$connection->query('INSERT INTO noprimarykey (col) VALUES (NULL)');
Assert::same('1', $connection->getInsertId());

$connection->query('INSERT INTO noprimarykey (col) VALUES (3)');
Assert::same('2', $connection->getInsertId());


$connection->query('
	CREATE TABLE [primarykey] (
		[prim] INTEGER PRIMARY KEY NOT NULL
	)
');

$connection->query('INSERT INTO primarykey (prim) VALUES (5)');
Assert::same('5', $connection->getInsertId());

$connection->query('INSERT INTO primarykey (prim) VALUES (6)');
Assert::same('6', $connection->getInsertId());


$connection->query('
	CREATE TABLE [autoprimarykey] (
		[prim] INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
		[col] INTEGER
	)
');

$connection->query('INSERT INTO autoprimarykey (col) VALUES (NULL)');
Assert::same('1', $connection->getInsertId());

$connection->query('INSERT INTO autoprimarykey (col) VALUES (NULL)');
Assert::same('2', $connection->getInsertId());

$connection->query('INSERT INTO autoprimarykey (prim, col) VALUES (10, NULL)');
Assert::same('10', $connection->getInsertId());
