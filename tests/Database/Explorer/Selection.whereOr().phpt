<?php

/**
 * Test: Nette\Database\Table: WhereOr operations
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

test('without question mark', function () use ($explorer) {
	$count = $explorer->table('book')->whereOr([
		'author_id' => 12,
		'title' => 'JUSH',
	])->count();
	Assert::same(3, $count);
});


test('full condition', function () use ($explorer) {
	$count = $explorer->table('book')->whereOr([
		'translator_id IS NULL',
		'title' => 'Dibi',
	])->count();
	Assert::same(2, $count);
});


test('with question mark', function () use ($explorer) {
	$count = $explorer->table('book')->whereOr([
		'id > ?' => 3,
		'translator_id' => 11,
	])->count();
	Assert::same(2, $count);
});


test('just one condition', function () use ($explorer) {
	$count = $explorer->table('book')->whereOr([
		'id > ?' => 3,
	])->count();
	Assert::same(1, $count);
});


test('with question mark', function () use ($explorer) {
	$count = $explorer->table('book')->whereOr([
		'id ?' => [3, 4],
		'translator_id' => 11,
	])->count();
	Assert::same(3, $count);
});


test('multiple values for one key', function () use ($explorer) {
	$count = $explorer->table('author')->whereOr([
		'id > ?' => 12,
		'ROUND(id, ?) = ?' => [5, 3],
	])->count();
	Assert::same(1, $count);
});


test('nested condition', function () use ($explorer) {
	$books = $explorer->table('book')->whereOr([
		'id = ?' => 4,
		'author_id = ? AND translator_id ?' => [11, null],
	]);
	Assert::same(2, $books->count());
});


test('invalid param count', function () use ($explorer) {
	$f = function () use ($explorer) {
		$explorer->table('author')->whereOr([
			'id > ?' => 3,
			'ROUND(id, ?) = ?' => [5],
		])->count();
	};
	Assert::throws($f, Nette\InvalidArgumentException::class, 'Argument count does not match placeholder count.');
});


test('invalid param count', function () use ($explorer) {
	$f = function () use ($explorer) {
		$explorer->table('author')->whereOr([
			'id > ?' => 3,
			'ROUND(id, ?) = ?' => 5,
		])->count();
	};
	Assert::throws($f, Nette\InvalidArgumentException::class, 'Argument count does not match placeholder count.');
});
