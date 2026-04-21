<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Connection DeadlockException via SERIALIZABLE isolation on PostgreSQL.
 * @dataProvider? databases.ini  postgresql
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('Exception thrown for SERIALIZABLE serialization failure', function () use ($connection) {
	$connection->query('CREATE TABLE IF NOT EXISTS serial_test (id int PRIMARY KEY, val int)');
	$connection->query('TRUNCATE serial_test');
	$connection->query('INSERT INTO serial_test (id, val) VALUES (1, 0), (2, 0)');

	$other = connectToDB()->getConnection();

	try {
		// Classic write-skew pattern: each transaction reads both rows
		// and updates one. PostgreSQL's SERIALIZABLE detects the conflict
		// at commit time and aborts the second committer.
		$connection->query('BEGIN ISOLATION LEVEL SERIALIZABLE');
		$other->query('BEGIN ISOLATION LEVEL SERIALIZABLE');

		$connection->query('SELECT val FROM serial_test WHERE id IN (1, 2)')->fetchAll();
		$other->query('SELECT val FROM serial_test WHERE id IN (1, 2)')->fetchAll();

		$connection->query('UPDATE serial_test SET val = 1 WHERE id = 1');
		$other->query('UPDATE serial_test SET val = 1 WHERE id = 2');

		$connection->commit();

		Assert::exception(
			fn() => $other->commit(),
			Nette\Database\DeadlockException::class,
			null,
			'40001',
		);
	} finally {
		try {
			$connection->rollBack();
		} catch (\Throwable) {
		}
		try {
			$other->rollBack();
		} catch (\Throwable) {
		}
		$connection->query('DROP TABLE IF EXISTS serial_test');
	}
});
