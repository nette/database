<?php

/**
 * Test: Nette\Database\Connection lazy connection.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Caching\Storages\DevNullStorage;
use Nette\Database\Structure;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('non lazy', function () {
	Assert::exception(
		fn() => new Nette\Database\Connection('xxx', 'user', 'password'),
		LogicException::class,
		"Unknown PDO driver 'xxx'.",
	);
});


test('lazy & explorer', function () {
	$connection = new Nette\Database\Connection('mysql:', 'user', 'password', ['lazy' => true]);
	$explorer = new Nette\Database\Explorer($connection, new Structure($connection, new DevNullStorage));
	Assert::exception(
		fn() => $explorer->query('SELECT ?', 10),
		Nette\Database\DriverException::class,
	);
});


test('lazy', function () {
	$connection = new Nette\Database\Connection('mysql:', 'user', 'password', ['lazy' => true]);
	Assert::exception(
		fn() => $connection->quote('x'),
		Nette\Database\DriverException::class,
	);
});


test('connect & disconnect', function () {
	$options = Tester\Environment::loadData() + ['username' => null, 'password' => null];
	$connections = 1;

	try {
		$connection = new Nette\Database\Connection($options['dsn'], $options['username'], $options['password']);
	} catch (Nette\Database\DriverException $e) {
		Tester\Environment::skip("Connection to '$options[dsn]' failed. Reason: " . $e->getMessage());
	}

	$connection->onConnect[] = function () use (&$connections) {
		$connections++;
	};

	// first connection
	$native = $connection->getConnection()->getNativeConnection();
	$driver = $connection->getConnection();
	Assert::same(1, $connections);

	// still first connection
	$connection->connect();
	Assert::same($native, $connection->getConnection()->getNativeConnection());
	Assert::same($driver, $connection->getConnection());
	Assert::same(1, $connections);

	// second connection
	$connection->reconnect();
	$native2 = $connection->getConnection()->getNativeConnection();
	$driver2 = $connection->getConnection();

	Assert::notSame($native, $native2);
	Assert::notSame($driver, $driver2);
	Assert::same(2, $connections);

	// third connection
	$connection->disconnect();
	Assert::notSame($native2, $connection->getConnection()->getNativeConnection());
	Assert::notSame($driver2, $connection->getConnection());
	Assert::same(3, $connections);
});
