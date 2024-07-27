<?php

/**
 * Test: Nette\Database\Table: Fetch pairs.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$apps = $explorer->table('book')->order('title')->fetchPairs('id', 'title');  // SELECT * FROM `book` ORDER BY `title`
	Assert::same([
		1 => '1001 tipu a triku pro PHP',
		4 => 'Dibi',
		2 => 'JUSH',
		3 => 'Nette',
	], $apps);
});


test('', function () use ($explorer) {
	$ids = $explorer->table('book')->order('id')->fetchPairs('id', 'id');  // SELECT * FROM `book` ORDER BY `id`
	Assert::same([
		1 => 1,
		2 => 2,
		3 => 3,
		4 => 4,
	], $ids);
});


test('', function () use ($explorer) {
	$explorer->table('author')->get(11)->update(['born' => new DateTime('2002-02-20')]);
	$explorer->table('author')->get(12)->update(['born' => new DateTime('2002-02-02')]);
	$list = $explorer->table('author')->where('born IS NOT NULL')->order('born')->fetchPairs('born', 'name');
	Assert::same([
		'2002-02-02 00:00:00.000000' => 'David Grudl',
		'2002-02-20 00:00:00.000000' => 'Jakub Vrana',
	], $list);
});


test('with callback', function () use ($explorer) {
	$pairs = $explorer->table('book')->order('title')->fetchPairs(fn($row) => [$row->id, substr($row->title, 0, 4)]);
	Assert::same([
		1 => '1001',
		4 => 'Dibi',
		2 => 'JUSH',
		3 => 'Nett',
	], $pairs);

	$pairs = $explorer->table('book')->order('title')->fetchPairs(fn($row) => [substr($row->title, 0, 4)]);
	Assert::same([
		'1001',
		'Dibi',
		'JUSH',
		'Nett',
	], $pairs);
});
