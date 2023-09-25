<?php

/**
 * Test: Nette\Database\Connection exceptions.
 * @dataProvider? databases.ini  postgresql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $options


$e = Assert::exception(
	fn() => new Nette\Database\Connection($options['dsn'], 'unknown', 'unknown'),
	Nette\Database\ConnectionException::class,
	null,
	'08006',
);

Assert::same(7, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(
	fn() => $connection->rollback(),
	Nette\Database\DriverException::class,
	'There is no active transaction',
	0,
);

Assert::same(null, $e->getDriverCode());
