<?php

/**
 * Test: Nette\Database\Connection exceptions.
 * @dataProvider? databases.ini  postgresql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('Exception thrown for invalid database credentials', function () {
	$options = Tester\Environment::loadData();
	$e = Assert::exception(
		fn() => new Nette\Database\Connection($options['dsn'], 'unknown', 'unknown'),
		Nette\Database\ConnectionException::class,
		null,
		'08006',
	);

	Assert::same(7, $e->getDriverCode());
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


test('Exception thrown for syntax error in SQL query', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query('SELECT INTO'),
		Nette\Database\DriverException::class,
		'%a% syntax error %A%',
		'42601',
	);

	Assert::same(7, $e->getDriverCode());
	Assert::same($e->getCode(), $e->getSqlState());
});


test('Exception thrown for unique constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query("INSERT INTO author (id, name, web, born) VALUES (11, '', '', NULL)"),
		Nette\Database\UniqueConstraintViolationException::class,
		'%a% Unique violation: %A%',
		'23505',
	);

	Assert::same(7, $e->getDriverCode());
	Assert::same($e->getCode(), $e->getSqlState());
});


test('Exception thrown for not null constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query("INSERT INTO author (name, web, born) VALUES (NULL, '', NULL)"),
		Nette\Database\NotNullConstraintViolationException::class,
		'%a% Not null violation: %A%',
		'23502',
	);

	Assert::same(7, $e->getDriverCode());
	Assert::same($e->getCode(), $e->getSqlState());
});


test('Exception thrown for foreign key constraint violation', function () use ($connection) {
	$e = Assert::exception(
		fn() => $connection->query("INSERT INTO book (author_id, translator_id, title) VALUES (999, 12, '')"),
		Nette\Database\ForeignKeyConstraintViolationException::class,
		'%a% Foreign key violation: %A%',
		'23503',
	);

	Assert::same(7, $e->getDriverCode());
	Assert::same($e->getCode(), $e->getSqlState());
});
