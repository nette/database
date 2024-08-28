<?php

/**
 * Test: Nette\Database\Connection fetch methods.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('fetch', function () use ($connection) {
	$row = $connection->fetch('SELECT name, id FROM author WHERE id = ?', 11);
	Assert::type(Nette\Database\Row::class, $row);
	Assert::equal(Nette\Database\Row::from([
		'name' => 'Jakub Vrana',
		'id' => 11,
	]), $row);
});


test('fetchAssoc', function () use ($connection) {
	$row = $connection->fetchAssoc('SELECT name, id FROM author WHERE id = ?', 11);
	Assert::same([
		'name' => 'Jakub Vrana',
		'id' => 11,
	], $row);
});


test('fetchField', function () use ($connection) {
	Assert::same('Jakub Vrana', $connection->fetchField('SELECT name FROM author ORDER BY id'));
});


test('fetchFields', function () use ($connection) {
	Assert::same([11, 'Jakub Vrana'], $connection->fetchFields('SELECT id, name FROM author ORDER BY id'));
});


test('fetchPairs', function () use ($connection) {
	$pairs = $connection->fetchPairs('SELECT name, id FROM author WHERE id > ? ORDER BY id', 11);
	Assert::same([
		'David Grudl' => 12,
		'Geek' => 13,
	], $pairs);
});


test('fetchAll', function () use ($connection) {
	$arr = $connection->fetchAll('SELECT name, id FROM author WHERE id < ? ORDER BY id', 13);
	Assert::equal([
		Nette\Database\Row::from(['name' => 'Jakub Vrana', 'id' => 11]),
		Nette\Database\Row::from(['name' => 'David Grudl', 'id' => 12]),
	], $arr);
});
