<?php

/**
 * Test: Nette\Database\Connection exceptions.
 * @dataProvider? databases.ini  postgresql
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $options


$e = Assert::exception(function() use ($options) {
	$connection = new Nette\Database\Connection($options['dsn'], 'unknown', 'unknown');
}, 'Nette\Database\ConnectionException', '%a% role "unknown" does not exist', '08006');

Assert::same(7, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(function() use ($connection) {
	$connection->rollback();
}, 'Nette\Database\DriverException', 'There is no active transaction', 0);

Assert::same(NULL, $e->getDriverCode());
