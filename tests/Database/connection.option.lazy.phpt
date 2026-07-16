<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Connection lazy connection.
 * @dataProvider? databases.ini
 */

use Nette\Caching\Storages\DevNullStorage;
use Nette\Database\Structure;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('non lazy', function () {
	Assert::exception(
		fn() => new Nette\Database\Connection('dsn', 'user', 'password'),
		Nette\Database\DriverException::class,
		'%a%valid data source %a%',
	);
});


test('lazy', function () {
	$connection = new Nette\Database\Connection('dsn', 'user', 'password', ['lazy' => true]);
	$explorer = new Nette\Database\Explorer($connection, new Structure($connection, new DevNullStorage));
	Assert::exception(
		fn() => $explorer->query('SELECT ?', 10),
		Nette\Database\DriverException::class,
		'%a%valid data source %a%',
	);
});


test('a failed lazy connection is reported by every method that forces it', function () {
	foreach ([fn($connection) => $connection->quote('x'), fn($connection) => $connection->getInsertId()] as $action) {
		$connection = new Nette\Database\Connection('dsn', 'user', 'password', ['lazy' => true]);
		Assert::exception(
			fn() => $action($connection),
			Nette\Database\DriverException::class,
			'%a%valid data source %a%',
		);
	}
});


test('isInTransaction() does not force a connect', function () {
	$connection = new Nette\Database\Connection('dsn', 'user', 'password', ['lazy' => true]);
	Assert::false($connection->isInTransaction());
});


test('connect & disconnect', function () {
	$options = Tester\Environment::loadData() + ['username' => null, 'password' => null];
	if ($options['dsn'] !== 'sqlite::memory:') {
		// serializes with the other tests on this DSN, whose fixtures recreate the whole database
		Tester\Environment::lock($options['dsn'], getTempDir());
	}

	$connections = 1;

	try {
		$connection = new Nette\Database\Connection($options['dsn'], $options['username'], $options['password']);
	} catch (PDOException $e) {
		Tester\Environment::skip("Connection to '$options[dsn]' failed. Reason: " . $e->getMessage());
	}

	$connection->onConnect[] = function () use (&$connections) {
		$connections++;
	};

	// first connection
	$pdo = $connection->getPdo();
	$driver = $connection->getDriver();
	Assert::same(1, $connections);

	// still first connection
	$connection->connect();
	Assert::same($pdo, $connection->getPdo());
	Assert::same($driver, $connection->getDriver());
	Assert::same(1, $connections);

	// second connection
	$connection->reconnect();
	$pdo2 = $connection->getPdo();
	$driver2 = $connection->getDriver();

	Assert::notSame($pdo, $pdo2);
	Assert::notSame($driver, $driver2);
	Assert::same(2, $connections);

	// third connection
	$connection->disconnect();
	Assert::notSame($pdo2, $connection->getPdo());
	Assert::notSame($driver2, $connection->getDriver());
	Assert::same(3, $connections);
});
