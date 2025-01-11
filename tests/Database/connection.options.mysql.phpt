<?php

/**
 * Test: Nette\Database\Connection options.
 * @dataProvider? databases.ini  mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('default charset', function () {
	$connection = connectToDB(['charset' => null]);
	$row = $connection->fetch("SHOW VARIABLES LIKE 'character_set_client'");
	Assert::same('utf8mb4', $row->Value);
});

test('custom charset', function () {
	$connection = connectToDB(['charset' => 'latin2']);
	$row = $connection->fetch("SHOW VARIABLES LIKE 'character_set_client'");
	Assert::same('latin2', $row->Value);
});


test('custom sqlmode', function () {
	$desiredMode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';
	$connection = connectToDB(['sqlmode' => $desiredMode]);
	$field = $connection->fetchField('SELECT @@sql_mode');
	Assert::same($desiredMode, $field);
});


test('default convertBoolean', function () {
	$connection = connectToDB(['convertBoolean' => null]);
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same(true, $row->bool);
});

test('convertBoolean = true', function () {
	$connection = connectToDB(['convertBoolean' => true]);
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same(true, $row->bool);
});

test('convertBoolean = false', function () {
	$connection = connectToDB(['convertBoolean' => false]);
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same(1, $row->bool);
});


test('default newDateTime', function () {
	$connection = connectToDB(['newDateTime' => null]);
	$field = $connection->fetchField('SELECT NOW()');
	Assert::type(Nette\Database\DateTime::class, $field);
});

test('newDateTime = false', function () {
	$connection = connectToDB(['newDateTime' => false]);
	$field = $connection->fetchField('SELECT NOW()');
	Assert::type(Nette\Utils\DateTime::class, $field);
});

test('newDateTime = true', function () {
	$connection = connectToDB(['newDateTime' => true]);
	$field = $connection->fetchField('SELECT NOW()');
	Assert::type(Nette\Database\DateTime::class, $field);
});


test('default convertDateTime', function () {
	$connection = connectToDB(['convertDateTime' => null]);
	$field = $connection->fetchField('SELECT NOW()');
	Assert::type(Nette\Database\DateTime::class, $field);
});

test('convertDateTime = false', function () {
	$connection = connectToDB(['convertDateTime' => false]);
	$field = $connection->fetchField('SELECT NOW()');
	Assert::type('string', $field);
});

test('convertDateTime = true', function () {
	$connection = connectToDB(['convertDateTime' => true]);
	$field = $connection->fetchField('SELECT NOW()');
	Assert::type(Nette\Database\DateTime::class, $field);
});


test('default convertDecimal', function () {
	$connection = connectToDB(['convertDecimal' => null]);
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same(1, $row->decimal);
	Assert::same(1.1, $row->decimal2);

	$fields = $connection->fetchFields('SELECT 10, 10.5');
	Assert::same([10, 10.5], $fields);
});

test('convertDecimal = false', function () {
	$connection = connectToDB(['convertDecimal' => false]);
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same('1', $row->decimal);
	Assert::same('1.10', $row->decimal2);

	$fields = $connection->fetchFields('SELECT 10, 10.5');
	Assert::same([10, '10.5'], $fields);
});

test('convertDecimal = true', function () {
	$connection = connectToDB(['convertDecimal' => true]);
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same(1, $row->decimal);
	Assert::same(1.1, $row->decimal2);

	$fields = $connection->fetchFields('SELECT 10, 10.5');
	Assert::same([10, 10.5], $fields);
});
