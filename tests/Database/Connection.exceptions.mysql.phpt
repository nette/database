<?php

/**
 * Test: Nette\Database\Connection exceptions.
 * @dataProvider? databases.ini  mysql
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $options


$e = Assert::exception(function () use ($options) {
	$connection = new Nette\Database\Connection($options['dsn'], 'unknown', 'unknown');
}, Nette\Database\ConnectionException::class, '%a% Access denied for user %a%');

Assert::same(1045, $e->getDriverCode());
Assert::contains($e->getSqlState(), ['HY000', '28000']);
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(function () use ($connection) {
	$connection->rollback();
}, Nette\Database\DriverException::class, 'There is no active transaction', 0);

Assert::same(NULL, $e->getDriverCode());
