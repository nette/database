<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Connection exceptions.
 * @dataProvider? databases.ini  mariadb
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('Exception thrown for unique constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query('INSERT INTO author (id, name, web, born) VALUES (11, "", "", NULL)'),
		Nette\Database\UniqueConstraintViolationException::class,
	);

	Assert::same(1062, $e->getDriverCode());
});


test('Exception thrown for not null constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query('INSERT INTO author (name, web, born) VALUES (NULL, "", NULL)'),
		Nette\Database\NotNullConstraintViolationException::class,
	);

	Assert::same(1048, $e->getDriverCode());
});


test('Exception thrown for foreign key constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query('INSERT INTO book (author_id, translator_id, title) VALUES (999, 12, "")'),
		Nette\Database\ForeignKeyConstraintViolationException::class,
	);

	Assert::same(1452, $e->getDriverCode());
});


test('Exception thrown for check constraint violation', function () use ($connection) {
	$connection->query('CREATE TEMPORARY TABLE check_test (id int, price int CHECK (price >= 0))');

	$e = Assert::exception(
		fn() => $connection->query('INSERT INTO check_test (id, price) VALUES (1, -5)'),
		Nette\Database\CheckConstraintViolationException::class,
	);

	Assert::same(4025, $e->getDriverCode()); // ER_CONSTRAINT_FAILED, differs from MySQL's 3819
});
