<?php

/**
 * Test: Nette\Database\Connection::getInsertId()
 * @dataProvider? databases.ini  postgresql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();

$connection->query('
	CREATE TEMPORARY TABLE "primarykey" (
		prim int NOT NULL,
		PRIMARY KEY(prim)
	)
');

$connection->query('INSERT INTO primarykey (prim) VALUES (5)');
if (PHP_VERSION_ID >= 70016) {
	Assert::exception(
		fn() => $connection->getInsertId(),
		Nette\Database\DriverException::class,
	);
} else {
	Assert::equal('0', $connection->getInsertId());
}


$connection->query('
	CREATE TEMPORARY TABLE "autoprimarykey" (
		prim serial NOT NULL,
		col int,
		PRIMARY KEY(prim)
	)
');

$connection->query('INSERT INTO autoprimarykey (col) VALUES (NULL)');
Assert::equal('1', $connection->getInsertId('autoprimarykey_prim_seq'));

$connection->query('INSERT INTO autoprimarykey (col) VALUES (NULL)');
Assert::equal('2', $connection->getInsertId('autoprimarykey_prim_seq'));

$connection->query('INSERT INTO autoprimarykey (prim, col) VALUES (10, NULL)');
Assert::equal('2', $connection->getInsertId('autoprimarykey_prim_seq'));


$connection->query('
	CREATE TEMPORARY TABLE "multiautoprimarykey" (
		prim1 serial NOT NULL,
		prim2 int NOT NULL,
		PRIMARY KEY(prim1, prim2)
	);
');

$connection->query('INSERT INTO multiautoprimarykey (prim2) VALUES (3)');
Assert::equal('1', $connection->getInsertId('multiautoprimarykey_prim1_seq'));

$connection->query('INSERT INTO multiautoprimarykey (prim2) VALUES (3)');
Assert::equal('2', $connection->getInsertId('multiautoprimarykey_prim1_seq'));

$connection->query('INSERT INTO multiautoprimarykey (prim1, prim2) VALUES (10, 3)');
Assert::equal('2', $connection->getInsertId('multiautoprimarykey_prim1_seq'));
