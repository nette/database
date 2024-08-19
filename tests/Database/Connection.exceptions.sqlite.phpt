<?php

/**
 * Test: Nette\Database\Connection exceptions.
 * @dataProvider? databases.ini  sqlite
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('Exception thrown for unable to open database file', function () {
	$e = Assert::exception(
		fn() => new Nette\Database\Connection('sqlite:.'),
		Nette\Database\ConnectionException::class,
		'SQLSTATE[HY000] [14] unable to open database file',
		'HY000',
	);

	Assert::same(14, $e->getDriverCode());
	Assert::same($e->getCode(), $e->getSqlState());
});


test('Exception thrown when calling rollback with no active transaction', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->rollback(),
		Nette\Database\DriverException::class,
		'There is no active transaction',
		0,
	);

	Assert::same(null, $e->getDriverCode());
});


test('Exception thrown for error in SQL query', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query('SELECT'),
		Nette\Database\DriverException::class,
		'%a% error%a%',
		'HY000',
	);

	Assert::same(1, $e->getDriverCode());
	Assert::same($e->getCode(), $e->getSqlState());
});


test('Exception thrown for unique constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query('INSERT INTO author (id, name, web, born) VALUES (11, "", "", NULL)'),
		Nette\Database\UniqueConstraintViolationException::class,
		'%a% Integrity constraint violation: %a%',
		'23000',
	);

	Assert::same(19, $e->getDriverCode());
	Assert::same($e->getCode(), $e->getSqlState());
});


test('Exception thrown for not null constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query('INSERT INTO author (name, web, born) VALUES (NULL, "", NULL)'),
		Nette\Database\NotNullConstraintViolationException::class,
		'%a% Integrity constraint violation: %a%',
		'23000',
	);

	Assert::same(19, $e->getDriverCode());
	Assert::same($e->getCode(), $e->getSqlState());
});


test('Exception thrown for foreign key constraint violation', function () use ($connection) {
	$e = Assert::exception(function () use ($connection) {
		$connection->query('PRAGMA foreign_keys=true');
		$connection->query('INSERT INTO book (author_id, translator_id, title) VALUES (999, 12, "")');
	}, Nette\Database\ForeignKeyConstraintViolationException::class, '%a% Integrity constraint violation: %a%', '23000');

	Assert::same(19, $e->getDriverCode());
	Assert::same($e->getCode(), $e->getSqlState());
});
