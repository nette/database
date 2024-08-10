<?php

/**
 * Test: Nette\Database\Connection exceptions.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$factory = new Nette\Database\Factory;

$connection = $factory->createFromParameters(
	driver: 'mysqli',
	host: 'localhost',
	username: 'root',
	password: 'xxx',
	database: 'nette_test',
);

/*
$connection = $factory->createFromParameters([
	'driver' => 'mysqli',
	'host' => 'localhost',
	'username' => 'root',
	'password' => 'xxx',
	'database' => 'nette_test',
]);

$connection = new Nette\Database\Connection([
	'driver' => 'mysqli',
	'host' => 'localhost',
	'username' => 'root',
	'password' => 'xxx',
	'database' => 'nette_test',
]);
*/

$connection->query('SET NAMES utf8');

/*
Assert::same(1045, $e->getDriverCode());
Assert::contains($e->getSqlState(), ['HY000', '28000']);
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(
	fn() => $connection->rollback(),
	Nette\Database\DriverException::class,
	'There is no active transaction',
	0,
);

Assert::same(null, $e->getDriverCode());
*/