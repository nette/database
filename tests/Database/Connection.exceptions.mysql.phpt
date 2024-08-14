<?php

/**
 * Test: Nette\Database\Connection exceptions.
 * @dataProvider? databases.ini  mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();

$options = Tester\Environment::loadData();
$e = Assert::exception(
	fn() => new Nette\Database\Connection($options['dsn'], 'unknown', 'unknown'),
	Nette\Database\ConnectionException::class,
	'%a% Access denied for user %a%',
	1045,
);

Assert::contains($e->getSqlState(), ['HY000', '28000']);


$e = Assert::exception(
	fn() => $connection->rollback(),
	Nette\Database\DriverException::class,
	'There is no active transaction',
	0,
);

Assert::null($e->getSqlState());
