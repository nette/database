<?php

/**
 * Test: Nette\Database\Connection sqlsrv options.
 * @dataProvider? databases.ini  sqlsrv
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('default convertDecimal', function () {
	$connection = connectToDB(['convertDecimal' => null])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlsrv-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same(1, $row->decimal);
	Assert::same(1, $row->numeric_10_0);
	Assert::same(1.1, $row->numeric_10_2);
});

test('convertDecimal = true', function () {
	$connection = connectToDB(['convertDecimal' => true])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlsrv-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same(1, $row->decimal);
	Assert::same(1, $row->numeric_10_0);
	Assert::same(1.1, $row->numeric_10_2);
});

test('convertDecimal = false', function () {
	$connection = connectToDB(['convertDecimal' => false])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlsrv-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::same('1', $row->decimal);
	Assert::same('1', $row->numeric_10_0);
	Assert::same('1.10', $row->numeric_10_2);
});


test('default convertBoolean', function () {
	$connection = connectToDB(['convertBoolean' => null])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlsrv-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::equal(true, $row->bit);
});

test('convertBoolean = true', function () {
	$connection = connectToDB(['convertBoolean' => true])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlsrv-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::equal(true, $row->bit);
});

test('convertBoolean = false', function () {
	$connection = connectToDB(['convertBoolean' => false])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlsrv-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::equal(1, $row->bit);
});
