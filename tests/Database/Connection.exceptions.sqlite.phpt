<?php

/**
 * Test: Nette\Database\Connection exceptions.
 * @dataProvider? databases.ini  sqlite
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $options


$e = Assert::exception(function () use ($options) {
	$connection = new Nette\Database\Connection('sqlite:.');
}, Nette\Database\ConnectionException::class, 'SQLSTATE[HY000] [14] unable to open database file', 'HY000');

Assert::same(14, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(function () use ($connection) {
	$connection->rollback();
}, Nette\Database\DriverException::class, 'There is no active transaction', 0);

Assert::same(NULL, $e->getDriverCode());
