<?php

/**
 * Test: Nette\Database\Table: Multi primary key support.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	foreach ($book->related('book_tag') as $bookTag) {
		if ($bookTag->tag->name === 'PHP') {
			$bookTag->delete();
		}
	}

	$count = $book->related('book_tag')->count();
	Assert::same(1, $count);

	$count = $book->related('book_tag')->count('*');
	Assert::same(1, $count);

	$count = $explorer->table('book_tag')->count('*');
	Assert::same(5, $count);
});


test('', function () use ($explorer) {
	$book = $explorer->table('book')->get(3);
	foreach ($related = $book->related('book_tag_alt') as $bookTag) {
	}

	$related->__destruct();

	$states = [];
	$book = $explorer->table('book')->get(3);
	foreach ($book->related('book_tag_alt') as $bookTag) {
		$states[] = $bookTag->state;
	}

	Assert::same([
		'public',
		'private',
		'private',
		'public',
	], $states);
});


test('', function () use ($explorer) {
	$explorer->table('book_tag')->insert([
		'book_id' => 1,
		'tag_id' => 21, // PHP tag
	]);
	$count = $explorer->table('book_tag')->where('book_id', 1)->count('*');
	Assert::same(2, $count);
});
