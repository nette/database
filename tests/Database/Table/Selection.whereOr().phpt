<?php

/**
 * Test: Nette\Database\Table: WhereOr operations
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

// without question mark
test(function () use ($context) {
	$count = $context->table('book')->whereOr([
			'author_id' => 12,
			'title' => 'JUSH',
		])->count();
	Assert::same(3, $count);
});


// full condition
test(function () use ($context) {
	$count = $context->table('book')->whereOr([
			'translator_id IS NULL',
			'title' => 'Dibi',
		])->count();
	Assert::same(2, $count);
});


// with question mark
test(function () use ($context) {
	$count = $context->table('book')->whereOr([
			'id > ?' => 3,
			'translator_id' => 11,
		])->count();
	Assert::same(2, $count);
});


// just one condition
test(function () use ($context) {
	$count = $context->table('book')->whereOr([
			'id > ?' => 3,
		])->count();
	Assert::same(1, $count);
});


// with question mark
test(function () use ($context) {
	$count = $context->table('book')->whereOr([
			'id ?' => [3, 4],
			'translator_id' => 11,
		])->count();
	Assert::same(3, $count);
});


// multiple values for one key
test(function () use ($context) {
	$count = $context->table('author')->whereOr([
			'id > ?' => 12,
			'ROUND(id, ?) = ?' => [5, 3],
		])->count();
	Assert::same(1, $count);
});


// nested condition
test(function () use ($context) {
	$books = $context->table('book')->whereOr([
			'id = ?' => 4,
			'author_id = ? AND translator_id ?' => [11, null],
	]);
	Assert::same(2, $books->count());
});


// invalid param count
test(function () use ($context) {
	$f = function () use ($context) {
		$context->table('author')->whereOr([
				'id > ?' => 3,
				'ROUND(id, ?) = ?' => [5],
		])->count();
	};
	Assert::throws($f, '\Nette\InvalidArgumentException', 'Argument count does not match placeholder count.');
});


// invalid param count
test(function () use ($context) {
	$f = function () use ($context) {
		$context->table('author')->whereOr([
				'id > ?' => 3,
				'ROUND(id, ?) = ?' => 5,
		])->count();
	};
	Assert::throws($f, '\Nette\InvalidArgumentException', 'Argument count does not match placeholder count.');
});
