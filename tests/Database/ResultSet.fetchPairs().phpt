<?php

/**
 * Test: Nette\Database\ResultSet: Fetch pairs.
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test(function () use ($context) {
	$res = $context->query('SELECT * FROM book ORDER BY title');
	Assert::same([
		1 => '1001 tipu a triku pro PHP',
		4 => 'Dibi',
		2 => 'JUSH',
		3 => 'Nette',
	], $res->fetchPairs('id', 'title'));

	Assert::same([
		'1001 tipu a triku pro PHP' => 1,
		'Dibi' => 4,
		'JUSH' => 2,
		'Nette' => 3,
	], $res->fetchPairs('title', 'id'));
});


test(function () use ($context) {
	$pairs = $context->query('SELECT title, id FROM book ORDER BY title')->fetchPairs(1, 0);
	Assert::same([
		1 => '1001 tipu a triku pro PHP',
		4 => 'Dibi',
		2 => 'JUSH',
		3 => 'Nette',
	], $pairs);
});


test(function () use ($context) {
	$pairs = $context->query('SELECT * FROM book ORDER BY id')->fetchPairs('id', 'id');
	Assert::same([
		1 => 1,
		2 => 2,
		3 => 3,
		4 => 4,
	], $pairs);
});


test(function () use ($context) {
	$pairs = $context->query('SELECT id FROM book ORDER BY id')->fetchPairs('id');
	Assert::equal([
		1 => Nette\Database\Row::from(['id' => 1]),
		2 => Nette\Database\Row::from(['id' => 2]),
		3 => Nette\Database\Row::from(['id' => 3]),
		4 => Nette\Database\Row::from(['id' => 4]),
	], $pairs);
});


test(function () use ($context) {
	$pairs = $context->query('UPDATE author SET born = ? WHERE id = 11', new DateTime('2002-02-20'));
	$pairs = $context->query('UPDATE author SET born = ? WHERE id = 12', new DateTime('2002-02-02'));
	$pairs = $context->query('SELECT * FROM author WHERE born IS NOT NULL ORDER BY born')->fetchPairs('born', 'name');
	Assert::same([
		'2002-02-02 00:00:00' => 'David Grudl',
		'2002-02-20 00:00:00' => 'Jakub Vrana',
	], $pairs);
});


$pairs = $context->query('SELECT id FROM book ORDER BY id')->fetchPairs('id');
Assert::equal([
	1 => Nette\Database\Row::from(['id' => 1]),
	2 => Nette\Database\Row::from(['id' => 2]),
	3 => Nette\Database\Row::from(['id' => 3]),
	4 => Nette\Database\Row::from(['id' => 4]),
], $pairs);


$pairs = $context->query('SELECT id FROM book ORDER BY id')->fetchPairs(NULL, 'id');
Assert::equal([
	0 => 1,
	1 => 2,
	2 => 3,
	3 => 4,
], $pairs);


$pairs = $context->query('SELECT id FROM book ORDER BY id')->fetchPairs();
Assert::equal([
	0 => 1,
	1 => 2,
	2 => 3,
	3 => 4,
], $pairs);


$pairs = $context->query('SELECT id, id + 1 AS id1 FROM book ORDER BY id')->fetchPairs();
Assert::equal([
	1 => 2,
	2 => 3,
	3 => 4,
	4 => 5,
], $pairs);


$pairs = $context->query('SELECT id, id + 1 AS id1, title FROM book ORDER BY id')->fetchPairs();
Assert::equal([
	1 => 2,
	2 => 3,
	3 => 4,
	4 => 5,
], $pairs);


$pairs = $context->query('UPDATE author SET born = ? WHERE id = 11', new DateTime('2002-02-20'));
$pairs = $context->query('UPDATE author SET born = ? WHERE id = 12', new DateTime('2002-02-02'));
$pairs = $context->query('SELECT * FROM author WHERE born IS NOT NULL ORDER BY born')->fetchPairs('born', 'name');
Assert::same([
	'2002-02-02 00:00:00' => 'David Grudl',
	'2002-02-20 00:00:00' => 'Jakub Vrana',
], $pairs);
