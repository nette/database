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
	Assert::equal('utf8mb4', $row->Value);
});

test('custom charset', function () {
	$connection = connectToDB(['charset' => 'latin2'])->getConnection();
	$row = $connection->fetch("SHOW VARIABLES LIKE 'character_set_client'");
	Assert::equal('latin2', $row->Value);
});

test('custom sqlmode', function () {
	$desiredMode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';
	$connection = connectToDB(['sqlmode' => $desiredMode])->getConnection();
	$field = $connection->fetchField('SELECT @@sql_mode');
	Assert::equal($desiredMode, $field);
});

test('convertBoolean = false', function () {
	$connection = connectToDB(['convertBoolean' => false])->getConnection();
	Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-nette_test3.sql');
	$row = $connection->fetch('SELECT * FROM types');
	Assert::equal(1, $row->bool);
});
