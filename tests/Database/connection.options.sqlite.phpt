<?php

/**
 * Test: Nette\Database\Connection options.
 * @dataProvider? databases.ini  sqlite
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('formatDateTime', function () {
	$connection = connectToDB(['formatDateTime' => 'U'])->getConnection();
	$engine = $connection->getDatabaseEngine();
	Assert::same('254358000', $engine->formatDateTime(new DateTime('1978-01-23 00:00:00')));
});

test('formatDateTime', function () {
	$connection = connectToDB(['formatDateTime' => 'Y-m-d'])->getConnection();
	$engine = $connection->getDatabaseEngine();
	Assert::same('1978-01-23', $engine->formatDateTime(new DateTime('1978-01-23 00:00:00')));
});


test('default convertDateTime', function () {
	$connection = connectToDB(['convertDateTime' => null])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlite-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::type(Nette\Database\DateTime::class, $row->date);
	Assert::type(Nette\Database\DateTime::class, $row->datetime);
});

test('convertDateTime = false', function () {
	$connection = connectToDB(['convertDateTime' => false])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlite-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::type('int', $row->date);
	Assert::type('int', $row->datetime);
});

test('convertDateTime = true', function () {
	$connection = connectToDB(['convertDateTime' => true])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlite-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::type(Nette\Database\DateTime::class, $row->date);
});
