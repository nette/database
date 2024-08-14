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
	14,
);
Assert::same('HY000', $e->getSqlState());


$e = Assert::exception(
	fn() => $connection->rollback(),
	Nette\Database\DriverException::class,
	'There is no active transaction',
	null,
);
Assert::null($e->getSqlState());
