<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Table: Multi insert operations
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('multi-insert on autoincrement table returns int row count', function () use ($explorer) {
	Assert::same(3, $explorer->table('author')->count());
	$result = $explorer->table('author')->insert([
		[
			'name' => 'Catelyn Stark',
			'web' => 'http://example.com',
			'born' => new DateTime('2011-11-11'),
		],
		[
			'name' => 'Sansa Stark',
			'web' => 'http://example.com',
			'born' => new DateTime('2021-11-11'),
		],
	]);  // INSERT INTO `author` (`name`, `web`, `born`) VALUES ('Catelyn Stark', 'http://example.com', '2011-11-11 00:00:00'), ('Sansa Stark', 'http://example.com', '2021-11-11 00:00:00')
	Assert::type('int', $result);
	Assert::same(2, $result);
	Assert::same(5, $explorer->table('author')->count());
});

test('multi-insert on composite primary key table returns int row count', function () use ($explorer) {
	$explorer->table('book_tag')->where('book_id', 1)->delete();  // DELETE FROM `book_tag` WHERE (`book_id` = ?)

	Assert::same(4, $explorer->table('book_tag')->count());
	$result = $explorer->table('book')->get(1)->related('book_tag')->insert([  // SELECT * FROM `book` WHERE (`id` = ?)
		['tag_id' => 21],
		['tag_id' => 22],
		['tag_id' => 23],
	]);  // INSERT INTO `book_tag` (`tag_id`, `book_id`) VALUES (21, 1), (22, 1), (23, 1)
	Assert::type('int', $result);
	Assert::same(3, $result);
	Assert::same(7, $explorer->table('book_tag')->count());
});
