<?php

/**
 * Test: Nette\Database\Connection::getInsertId()
 * @dataProvider? databases.ini  sqlsrv
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();

$connection->query("IF OBJECT_ID('noprimarykey', 'U') IS NOT NULL DROP TABLE noprimarykey");
$connection->query('
	CREATE TABLE [noprimarykey] (
		col int
	)
');

$connection->query('INSERT INTO noprimarykey (col) VALUES (NULL)');
Assert::equal('', $connection->getInsertId());

$connection->query('INSERT INTO noprimarykey (col) VALUES (NULL)');
Assert::equal('', $connection->getInsertId());


$connection->query("IF OBJECT_ID('primarykey', 'U') IS NOT NULL DROP TABLE primarykey");
$connection->query('
	CREATE TABLE [primarykey] (
		prim int NOT NULL,
		PRIMARY KEY(prim)
	)
');

$connection->query('INSERT INTO primarykey (prim) VALUES (5)');
Assert::equal('', $connection->getInsertId());

$connection->query('INSERT INTO primarykey (prim) VALUES (6)');
Assert::equal('', $connection->getInsertId());


$connection->query("IF OBJECT_ID('autoprimarykey', 'U') IS NOT NULL DROP TABLE autoprimarykey");
$connection->query('
	CREATE TABLE [autoprimarykey] (
		prim int NOT NULL IDENTITY(1,1),
		col int,
		PRIMARY KEY(prim)
	)
');

$connection->query('INSERT INTO autoprimarykey (col) VALUES (NULL)');
Assert::equal('1', $connection->getInsertId());

$connection->query('INSERT INTO autoprimarykey (col) VALUES (NULL)');
Assert::equal('2', $connection->getInsertId());

$connection->query('SET IDENTITY_INSERT autoprimarykey ON; INSERT INTO autoprimarykey (prim, col) VALUES (10, NULL)');
Assert::equal('10', $connection->getInsertId());


$connection->query("IF OBJECT_ID('multiautoprimarykey', 'U') IS NOT NULL DROP TABLE multiautoprimarykey");
$connection->query('
	CREATE TABLE [multiautoprimarykey] (
		prim1 int NOT NULL IDENTITY(1,1),
		prim2 int NOT NULL,
		PRIMARY KEY(prim1, prim2)
	)
');

$connection->query('INSERT INTO multiautoprimarykey (prim2) VALUES (3)');
Assert::equal('1', $connection->getInsertId());

$connection->query('INSERT INTO multiautoprimarykey (prim2) VALUES (3)');
Assert::equal('2', $connection->getInsertId());

$connection->query('SET IDENTITY_INSERT multiautoprimarykey ON; INSERT INTO multiautoprimarykey (prim1, prim2) VALUES (10, 3)');
Assert::equal('10', $connection->getInsertId());
