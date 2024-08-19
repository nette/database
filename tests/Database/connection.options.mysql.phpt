<?php

/**
 * Test: Nette\Database\Connection options.
 * @dataProvider? databases.ini  mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('default charset', function () {
	$connection = connectToDB(['charset' => null])->getConnection();
	$row = $connection->fetch("SHOW VARIABLES LIKE 'character_set_client'");
	Assert::same('utf8mb4', $row->Value);
});

test('custom charset', function () {
	$connection = connectToDB(['charset' => 'latin2'])->getConnection();
	$row = $connection->fetch("SHOW VARIABLES LIKE 'character_set_client'");
	Assert::same('latin2', $row->Value);
});


test('custom sqlmode', function () {
	$desiredMode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';
	$connection = connectToDB(['sqlmode' => $desiredMode])->getConnection();
	$field = $connection->fetchField('SELECT @@sql_mode');
	Assert::same($desiredMode, $field);
});


test('default convertBoolean', function () {
	$connection = connectToDB(['convertBoolean' => null])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same(1, $row->bool);
});

test('convertBoolean = true', function () {
	$connection = connectToDB(['convertBoolean' => true])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same(true, $row->bool);
});

test('convertBoolean = false', function () {
	$connection = connectToDB(['convertBoolean' => false])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same(1, $row->bool);
});


test('default newDateTime', function () {
	$connection = connectToDB(['newDateTime' => null])->getConnection();
	$field = $connection->fetchField('SELECT NOW()');
	Assert::type(Nette\Utils\DateTime::class, $field);
});

test('newDateTime = false', function () {
	$connection = connectToDB(['newDateTime' => false])->getConnection();
	$field = $connection->fetchField('SELECT NOW()');
	Assert::type(Nette\Utils\DateTime::class, $field);
});

test('newDateTime = true', function () {
	$connection = connectToDB(['newDateTime' => true])->getConnection();
	$field = $connection->fetchField('SELECT NOW()');
	Assert::type(Nette\Database\DateTime::class, $field);
});
