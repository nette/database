<?php

/**
 * Test: Nette\Database\Table: Delete operations
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('delete records by condition in book_tag', function () use ($explorer) {
	$explorer->table('book_tag')->where('book_id', 4)->delete();  // DELETE FROM `book_tag` WHERE (`book_id` = ?)

	$count = $explorer->table('book_tag')->where('book_id', 4)->count();  // SELECT * FROM `book_tag` WHERE (`book_id` = ?)
	Assert::same(0, $count);
});


test('delete related book_tag_alt and cascade book deletion', function () use ($explorer) {
	$book = $explorer->table('book')->get(3);  // SELECT * FROM `book` WHERE (`id` = ?)
	$book->related('book_tag_alt')->where('tag_id', 21)->delete();  // DELETE FROM `book_tag_alt` WHERE (`book_id` = ?) AND (`tag_id` = ?)

	$count = $explorer->table('book_tag_alt')->where('book_id', 3)->count();  // SELECT * FROM `book_tag_alt` WHERE (`book_id` = ?)
	Assert::same(3, $count);

	$book->delete();  // DELETE FROM `book` WHERE (`id` = ?)
	Assert::count(0, $explorer->table('book')->wherePrimary(3));  // SELECT * FROM `book` WHERE (`id` = ?)
});
