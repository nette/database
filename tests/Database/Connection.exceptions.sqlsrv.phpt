<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Connection exceptions.
 * @dataProvider? databases.ini  sqlsrv
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");

$connection->query('DROP TABLE IF EXISTS exc_test');
$connection->query('CREATE TABLE exc_test (id INT NOT NULL PRIMARY KEY, price INT CHECK (price >= 0))');
$connection->query('INSERT INTO exc_test (id, price) VALUES (1, 1)');


test('Exception thrown for unique constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query('INSERT INTO exc_test (id, price) VALUES (1, 1)'),
		Nette\Database\UniqueConstraintViolationException::class,
	);

	Assert::same(2627, $e->getDriverCode());
});


test('Exception thrown for not null constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query('INSERT INTO exc_test (id, price) VALUES (NULL, 1)'),
		Nette\Database\NotNullConstraintViolationException::class,
	);

	Assert::same(515, $e->getDriverCode());
});


test('Exception thrown for check constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query('INSERT INTO exc_test (id, price) VALUES (2, -5)'),
		Nette\Database\CheckConstraintViolationException::class,
	);

	Assert::same(547, $e->getDriverCode());
});


test('Exception thrown for foreign key constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query("INSERT INTO book (author_id, translator_id, title) VALUES (999, 12, '')"),
		Nette\Database\ForeignKeyConstraintViolationException::class,
	);

	Assert::same(547, $e->getDriverCode());
});
