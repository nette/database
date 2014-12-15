<?php

/**
 * Test: Nette\Database\ResultSet & Connection exceptions.
 * @dataProvider? databases.ini  mysql
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


$e = Assert::exception(function() use ($context) {
	$context->query('SELECT');
}, 'Nette\Database\DriverException', '%a% Syntax error %a%', '42000');

Assert::same(1064, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(function() use ($context) {
	$context->query('INSERT INTO author (id, name, web, born) VALUES (11, "", "", NULL)');
}, 'Nette\Database\UniqueConstraintViolationException', '%a% Integrity constraint violation: %a%', '23000');

Assert::same(1062, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(function() use ($context) {
	$context->query('INSERT INTO author (name, web, born) VALUES (NULL, "", NULL)');
}, 'Nette\Database\NotNullConstraintViolationException', '%a% Integrity constraint violation: %a%', '23000');

Assert::same(1048, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());


$e = Assert::exception(function() use ($context) {
	$context->query('INSERT INTO book (author_id, translator_id, title) VALUES (999, 12, "")');
}, 'Nette\Database\ForeignKeyConstraintViolationException', '%a% a foreign key constraint fails %a%', '23000');

Assert::same(1452, $e->getDriverCode());
Assert::same($e->getCode(), $e->getSqlState());
