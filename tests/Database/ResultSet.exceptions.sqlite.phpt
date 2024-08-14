<?php

/**
 * Test: Nette\Database\ResultSet & Connection exceptions.
 * @dataProvider? databases.ini  sqlite
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


$e = Assert::exception(
	fn() => $connection->query('SELECT'),
	Nette\Database\DriverException::class,
	'%a% error%a%',
	'HY000',
);

Assert::same(1, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(
	fn() => $connection->query('INSERT INTO author (id, name, web, born) VALUES (11, "", "", NULL)'),
	Nette\Database\UniqueConstraintViolationException::class,
	'%a% Integrity constraint violation: %a%',
	'23000',
);

Assert::same(19, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(
	fn() => $connection->query('INSERT INTO author (name, web, born) VALUES (NULL, "", NULL)'),
	Nette\Database\NotNullConstraintViolationException::class,
	'%a% Integrity constraint violation: %a%',
	'23000',
);

Assert::same(19, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(function () use ($connection) {
	$connection->query('PRAGMA foreign_keys=true');
	$connection->query('INSERT INTO book (author_id, translator_id, title) VALUES (999, 12, "")');
}, Nette\Database\ForeignKeyConstraintViolationException::class, '%a% Integrity constraint violation: %a%', '23000');

Assert::same(19, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());
