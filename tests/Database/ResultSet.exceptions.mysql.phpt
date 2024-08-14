<?php

/**
 * Test: Nette\Database\ResultSet & Connection exceptions.
 * @dataProvider? databases.ini  mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


$e = Assert::exception(
	fn() => $connection->query('SELECT'),
	Nette\Database\DriverException::class,
	'%a% Syntax error %a%',
	'42000',
);

Assert::same(1064, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(
	fn() => $connection->query('INSERT INTO author (id, name, web, born) VALUES (11, "", "", NULL)'),
	Nette\Database\UniqueConstraintViolationException::class,
	'%a% Integrity constraint violation: %a%',
	'23000',
);

Assert::same(1062, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(
	fn() => $connection->query('INSERT INTO author (name, web, born) VALUES (NULL, "", NULL)'),
	Nette\Database\NotNullConstraintViolationException::class,
	'%a% Integrity constraint violation: %a%',
	'23000',
);

Assert::same(1048, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(
	fn() => $connection->query('INSERT INTO book (author_id, translator_id, title) VALUES (999, 12, "")'),
	Nette\Database\ForeignKeyConstraintViolationException::class,
	'%a% a foreign key constraint fails %a%',
	'23000',
);

Assert::same(1452, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());
