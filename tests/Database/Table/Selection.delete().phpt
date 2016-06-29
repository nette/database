<?php

/**
 * Test: Nette\Database\Table: Delete operations
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test(function () use ($context) {
	$context->table('book_tag')->where('book_id', 4)->delete();  // DELETE FROM `book_tag` WHERE (`book_id` = ?)

	$count = $context->table('book_tag')->where('book_id', 4)->count();  // SELECT * FROM `book_tag` WHERE (`book_id` = ?)
	Assert::same(0, $count);
});


test(function () use ($context) {
	$book = $context->table('book')->get(3);  // SELECT * FROM `book` WHERE (`id` = ?)
	$book->related('book_tag_alt')->where('tag_id', 21)->delete();  // DELETE FROM `book_tag_alt` WHERE (`book_id` = ?) AND (`tag_id` = ?)

	$count = $context->table('book_tag_alt')->where('book_id', 3)->count();  // SELECT * FROM `book_tag_alt` WHERE (`book_id` = ?)
	Assert::same(3, $count);


	$book->delete();  // DELETE FROM `book` WHERE (`id` = ?)
	Assert::count(0, $context->table('book')->wherePrimary(3));  // SELECT * FROM `book` WHERE (`id` = ?)
});

if($driverName === 'mysql') {
	test(function() use ($context) {
		$context->table('book')->where(':book_tag.tag.name = ?', 'PHP')->delete(); // DELETE FROM `book` LEFT JOIN `book_tag` ON `book`.`id` = `book_tag`.`book_id` LEFT JOIN `tag` ON `tag`.`id` = `book_tag`.`tag_id` WHERE `tag`.`name` = ?
		foreach($context->table('book') as $book) {
			foreach($book->related('book_tag') as $bookTag) {
				Assert::notEqual('PHP', $bookTag->ref('tag')->name);
			}
		}
	});
}
