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


test(function () { // non lazy
	Assert::exception(function () {
		$connection = new Nette\Database\Connection('dsn', 'user', 'password');
	}, Nette\Database\DriverException::class, 'invalid data source name');
});


test(function () { // lazy
	$connection = new Nette\Database\Connection('dsn', 'user', 'password', ['lazy' => TRUE]);
	$context = new Nette\Database\Context($connection, new Structure($connection, new DevNullStorage()));
	Assert::exception(function () use ($context) {
		$context->query('SELECT ?', 10);
	}, Nette\Database\DriverException::class, 'invalid data source name');
});


test(function () {
	$connection = new Nette\Database\Connection('dsn', 'user', 'password', ['lazy' => TRUE]);
	Assert::exception(function () use ($connection) {
		$connection->quote('x');
	}, Nette\Database\DriverException::class, 'invalid data source name');
});


test(function () { // connect & disconnect
	$options = Tester\Environment::loadData() + ['user' => NULL, 'password' => NULL];
	$connections = 1;

	try {
		$connection = new Nette\Database\Connection($options['dsn'], $options['user'], $options['password']);
	} catch (PDOException $e) {
		Tester\Environment::skip("Connection to '$options[dsn]' failed. Reason: " . $e->getMessage());
	}
	$connection->onConnect[] = function () use (&$connections) {
		$connections++;
	};

	// first connection
	$pdo = $connection->getPdo();
	$driver = $connection->getSupplementalDriver();
	Assert::same(1, $connections);

	// still first connection
	$connection->connect();
	Assert::same($pdo, $connection->getPdo());
	Assert::same($driver, $connection->getSupplementalDriver());
	Assert::same(1, $connections);

	// second connection
	$connection->reconnect();
	$pdo2 = $connection->getPdo();
	$driver2 = $connection->getSupplementalDriver();

	Assert::notSame($pdo, $pdo2);
	Assert::notSame($driver, $driver2);
	Assert::same(2, $connections);

	// third connection
	$connection->disconnect();
	Assert::notSame($pdo2, $connection->getPdo());
	Assert::notSame($driver2, $connection->getSupplementalDriver());
	Assert::same(3, $connections);
});
