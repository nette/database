<?php

/**
 * Test: Nette\Database\ResultSet & Connection exceptions.
 * @dataProvider? databases.ini  sqlite
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


$e = Assert::exception(function () use ($context) {
	$context->query('SELECT');
}, Nette\Database\DriverException::class, '%a% syntax error', 'HY000');

Assert::same(1, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(function () use ($context) {
	$context->query('INSERT INTO author (id, name, web, born) VALUES (11, "", "", NULL)');
}, Nette\Database\UniqueConstraintViolationException::class, '%a% Integrity constraint violation: %a%', '23000');

Assert::same(19, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(function () use ($context) {
	$context->query('INSERT INTO author (name, web, born) VALUES (NULL, "", NULL)');
}, Nette\Database\NotNullConstraintViolationException::class, '%a% Integrity constraint violation: %a%', '23000');

Assert::same(19, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(function () use ($context) {
	$context->query('PRAGMA foreign_keys=true');
	$context->query('INSERT INTO book (author_id, translator_id, title) VALUES (999, 12, "")');
}, Nette\Database\ForeignKeyConstraintViolationException::class, '%a% Integrity constraint violation: %a%', '23000');

Assert::same(19, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());
