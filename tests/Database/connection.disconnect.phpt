<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Connection disconnect()
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('a failed connection is reported by every method that forces it', function () {
	foreach ([fn($connection) => $connection->quote('x'), fn($connection) => $connection->getInsertId()] as $action) {
		$connection = new Nette\Database\Connection('dsn', 'user', 'password');
		Assert::exception(
			fn() => $action($connection),
			Nette\Database\DriverException::class,
			'%a%valid data source %a%',
		);
	}
});


test('connect & disconnect', function () {
	$options = Tester\Environment::loadData() + ['username' => null, 'password' => null];
	if ($options['dsn'] !== 'sqlite::memory:') {
		// serializes with the other tests on this DSN, whose fixtures recreate the whole database
		Tester\Environment::lock($options['dsn'], getTempDir());
	}

	$connections = 1;

	$connection = new Nette\Database\Connection($options['dsn'], $options['username'], $options['password']);
	try {
		$connection->connect();
	} catch (PDOException $e) {
		Tester\Environment::skip("Connection to '$options[dsn]' failed. Reason: " . $e->getMessage());
	}

	$connection->onConnect[] = function () use (&$connections) {
		$connections++;
	};

	// first connection
	$pdo = $connection->getPdo();
	$driver = $connection->getDatabaseEngine();
	Assert::same(1, $connections);

	// still first connection
	$connection->connect();
	Assert::same($pdo, $connection->getPdo());
	Assert::same($driver, $connection->getDatabaseEngine());
	Assert::same(1, $connections);

	// second connection
	$connection->reconnect();
	$pdo2 = $connection->getPdo();
	$driver2 = $connection->getDatabaseEngine();

	Assert::notSame($pdo, $pdo2);
	Assert::notSame($driver, $driver2);
	Assert::same(2, $connections);

	// third connection
	$connection->disconnect();
	Assert::notSame($pdo2, $connection->getPdo());
	Assert::notSame($driver2, $connection->getDatabaseEngine());
	Assert::same(3, $connections);
});
