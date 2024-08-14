<?php

/**
 * Test: Nette\Database\Connection exceptions.
 * @dataProvider? databases.ini  sqlite
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();

$e = Assert::exception(
	fn() => new Nette\Database\Connection('sqlite:.'),
	Nette\Database\ConnectionException::class,
	'SQLSTATE[HY000] [14] unable to open database file',
	'HY000',
);

Assert::same(14, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(
	fn() => $connection->rollback(),
	Nette\Database\DriverException::class,
	'There is no active transaction',
	0,
);

Assert::same(null, $e->getDriverCode());
