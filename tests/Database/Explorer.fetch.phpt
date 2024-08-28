<?php

/**
 * Test: Nette\Database\Explorer fetch methods.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('fetch', function () use ($explorer) {
	$row = $explorer->fetch('SELECT name, id FROM author WHERE id = ?', 11);
	Assert::type(Nette\Database\Row::class, $row);
	Assert::equal(Nette\Database\Row::from([
		'name' => 'Jakub Vrana',
		'id' => 11,
	]), $row);
});


test('fetchAssoc', function () use ($explorer) {
	$row = $explorer->fetchAssoc('SELECT name, id FROM author WHERE id = ?', 11);
	Assert::same([
		'name' => 'Jakub Vrana',
		'id' => 11,
	], $row);
});


test('fetchField', function () use ($explorer) {
	Assert::same('Jakub Vrana', $explorer->fetchField('SELECT name FROM author ORDER BY id'));
});


test('fetchFields', function () use ($explorer) {
	Assert::same([11, 'Jakub Vrana'], $explorer->fetchFields('SELECT id, name FROM author ORDER BY id'));
});


test('fetchPairs', function () use ($explorer) {
	$pairs = $explorer->fetchPairs('SELECT name, id FROM author WHERE id > ? ORDER BY id', 11);
	Assert::same([
		'David Grudl' => 12,
		'Geek' => 13,
	], $pairs);
});


test('fetchAll', function () use ($explorer) {
	$arr = $explorer->fetchAll('SELECT name, id FROM author WHERE id < ? ORDER BY id', 13);
	Assert::equal([
		Nette\Database\Row::from(['name' => 'Jakub Vrana', 'id' => 11]),
		Nette\Database\Row::from(['name' => 'David Grudl', 'id' => 12]),
	], $arr);
});
