<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Connection LockTimeoutException on MySQL.
 * @dataProvider? databases.ini  mysql
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('Exception thrown on lock wait timeout', function () use ($connection) {
	$holder = connectToDB()->getConnection();
	$holder->beginTransaction();
	$holder->query('SELECT * FROM author WHERE id = 11 FOR UPDATE');

	$connection->query('SET innodb_lock_wait_timeout = 1');
	$connection->beginTransaction();

	Assert::exception(
		fn() => $connection->query('SELECT * FROM author WHERE id = 11 FOR UPDATE'),
		Nette\Database\LockTimeoutException::class,
	);

	$connection->rollBack();
	$holder->rollBack();
});
