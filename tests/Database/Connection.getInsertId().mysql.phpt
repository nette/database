<?php

/**
 * Test: Nette\Database\Connection::getInsertId()
 * @dataProvider? databases.ini  mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();

$connection->query('
	CREATE TEMPORARY TABLE noprimarykey (
		col int
	) ENGINE=InnoDB
');

$connection->query('INSERT INTO noprimarykey (col) VALUES (NULL)');
Assert::same('0', $connection->getInsertId());

$connection->query('INSERT INTO noprimarykey (col) VALUES (3)');
Assert::same('0', $connection->getInsertId());


$connection->query('
	CREATE TEMPORARY TABLE primarykey (
		prim int NOT NULL,
		PRIMARY KEY(prim)
	) ENGINE=InnoDB
');

$connection->query('INSERT INTO primarykey (prim) VALUES (5)');
Assert::same('0', $connection->getInsertId());

$connection->query('INSERT INTO primarykey (prim) VALUES (6)');
Assert::same('0', $connection->getInsertId());


$connection->query('
	CREATE TEMPORARY TABLE autoprimarykey (
		prim int NOT NULL AUTO_INCREMENT,
		col int,
		PRIMARY KEY(prim)
	) ENGINE=InnoDB
');

$connection->query('INSERT INTO autoprimarykey (col) VALUES (NULL)');
Assert::same('1', $connection->getInsertId());

$connection->query('INSERT INTO autoprimarykey (col) VALUES (NULL)');
Assert::same('2', $connection->getInsertId());

$connection->query('INSERT INTO autoprimarykey (prim, col) VALUES (10, NULL)');
Assert::same('10', $connection->getInsertId());


$connection->query('
	CREATE TEMPORARY TABLE multiautoprimarykey (
		prim1 int NOT NULL AUTO_INCREMENT,
		prim2 int NOT NULL,
		PRIMARY KEY(prim1, prim2)
	) ENGINE=InnoDB
');

$connection->query('INSERT INTO multiautoprimarykey (prim2) VALUES (3)');
Assert::same('1', $connection->getInsertId());

$connection->query('INSERT INTO multiautoprimarykey (prim2) VALUES (3)');
Assert::same('2', $connection->getInsertId());

$connection->query('INSERT INTO multiautoprimarykey (prim1, prim2) VALUES (10, 3)');
Assert::same('10', $connection->getInsertId());
