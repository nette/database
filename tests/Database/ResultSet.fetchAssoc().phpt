<?php

/**
 * Test: Nette\Database\ResultSet: Fetch assoc.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('fetches associative array with id-title pairs', function () use ($connection) {
	$res = $connection->query('SELECT * FROM book ORDER BY title');
	Assert::same([
		1 => '1001 tipu a triku pro PHP',
		4 => 'Dibi',
		2 => 'JUSH',
		3 => 'Nette',
	], $res->fetchAssoc('id=title'));
});


test('fetches associative array with single column id key', function () use ($connection) {
	$pairs = $connection->query('SELECT id FROM book ORDER BY id')->fetchAssoc('id');
	Assert::same([
		1 => ['id' => 1],
		2 => ['id' => 2],
		3 => ['id' => 3],
		4 => ['id' => 4],
	], $pairs);
});


test('fetches associative array with array values', function () use ($connection) {
	$pairs = $connection->query('SELECT id FROM book ORDER BY id')->fetchAssoc('id[]=id');
	Assert::same([
		1 => [1],
		2 => [2],
		3 => [3],
		4 => [4],
	], $pairs);
});


test('fetches associative array using datetime values as keys', function () use ($connection) {
	$pairs = $connection->query('UPDATE author SET born = ? WHERE id = 11', new DateTime('2002-02-20'));
	$pairs = $connection->query('UPDATE author SET born = ? WHERE id = 12', new DateTime('2002-02-02'));
	$pairs = $connection->query('SELECT * FROM author WHERE born IS NOT NULL ORDER BY born')->fetchAssoc('born=name');
	Assert::same([
		'2002-02-02 00:00:00.000000' => 'David Grudl',
		'2002-02-20 00:00:00.000000' => 'Jakub Vrana',
	], $pairs);
});
