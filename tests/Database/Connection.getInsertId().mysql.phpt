<?php

/**
 * Test: Nette\Database\Connection::getInsertId()
 * @dataProvider? databases.ini  mysql
 */

use Tester\Assert;
use Nette\Utils\DateTime;

require __DIR__ . '/connect.inc.php'; // create $connection

$connection->query('CREATE DATABASE IF NOT EXISTS nette_test');
$connection->query('USE nette_test');


$connection->query('
	CREATE TEMPORARY TABLE noprimarykey (
		col int
	) ENGINE=InnoDB
');

$connection->query('INSERT INTO noprimarykey (col) VALUES (NULL)');
Assert::equal('0', $connection->getInsertId());

$connection->query('INSERT INTO noprimarykey (col) VALUES (3)');
Assert::equal('0', $connection->getInsertId());


$connection->query('
	CREATE TEMPORARY TABLE primarykey (
		prim int NOT NULL,
		PRIMARY KEY(prim)
	) ENGINE=InnoDB
');

$connection->query('INSERT INTO primarykey (prim) VALUES (5)');
Assert::equal('0', $connection->getInsertId());

$connection->query('INSERT INTO primarykey (prim) VALUES (6)');
Assert::equal('0', $connection->getInsertId());


$connection->query('
	CREATE TEMPORARY TABLE autoprimarykey (
		prim int NOT NULL AUTO_INCREMENT,
		col int,
		PRIMARY KEY(prim)
	) ENGINE=InnoDB
');

$connection->query('INSERT INTO autoprimarykey (col) VALUES (NULL)');
Assert::equal('1', $connection->getInsertId());

$connection->query('INSERT INTO autoprimarykey (col) VALUES (NULL)');
Assert::equal('2', $connection->getInsertId());

$connection->query('INSERT INTO autoprimarykey (prim, col) VALUES (10, NULL)');
Assert::equal('10', $connection->getInsertId());


$connection->query('
	CREATE TEMPORARY TABLE multiautoprimarykey (
		prim1 int NOT NULL AUTO_INCREMENT,
		prim2 int NOT NULL,
		PRIMARY KEY(prim1, prim2)
	) ENGINE=InnoDB
');

$connection->query('INSERT INTO multiautoprimarykey (prim2) VALUES (3)');
Assert::equal('1', $connection->getInsertId());

$connection->query('INSERT INTO multiautoprimarykey (prim2) VALUES (3)');
Assert::equal('2', $connection->getInsertId());

$connection->query('INSERT INTO multiautoprimarykey (prim1, prim2) VALUES (10, 3)');
Assert::equal('10', $connection->getInsertId());
