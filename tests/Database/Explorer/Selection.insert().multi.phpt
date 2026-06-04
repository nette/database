<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Table: Multi insert operations
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
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
	Assert::same(2, $result);
	Assert::same(5, $explorer->table('author')->count());

	$explorer->table('book_tag')->where('book_id', 1)->delete();  // DELETE FROM `book_tag` WHERE (`book_id` = ?)

	Assert::same(4, $explorer->table('book_tag')->count());
	$result = $explorer->table('book')->get(1)->related('book_tag')->insert([  // SELECT * FROM `book` WHERE (`id` = ?)
		['tag_id' => 21],
		['tag_id' => 22],
		['tag_id' => 23],
	]);  // INSERT INTO `book_tag` (`tag_id`, `book_id`) VALUES (21, 1), (22, 1), (23, 1)
	Assert::same(3, $result);
	Assert::same(7, $explorer->table('book_tag')->count());
});


test('rows with non-sequential keys are treated as a multi-insert', function () use ($explorer) {
	$rows = (function () {
		yield 1 => ['name' => 'Arya Stark', 'web' => 'http://example.com'];
		yield 3 => ['name' => 'Jon Snow', 'web' => 'http://example.com'];
	})();

	Assert::same(2, $explorer->table('author')->insert($rows));

	// an array behaves the same, e.g. left over from array_filter()
	Assert::same(2, $explorer->table('author')->insert([
		0 => ['name' => 'Hodor', 'web' => 'http://example.com'],
		2 => ['name' => 'Osha', 'web' => 'http://example.com'],
	]));

	// and so does a GroupedSelection, which adds the grouping column to each row
	$before = $explorer->table('book_tag')->where('book_id', 3)->count();
	Assert::same(2, $explorer->table('book')->get(3)->related('book_tag')->insert([
		0 => ['tag_id' => 23],
		2 => ['tag_id' => 24],
	]));
	Assert::same($before + 2, $explorer->table('book_tag')->where('book_id', 3)->count());
});


test('a generator yielding rows under colliding keys loses none of them', function () use ($explorer) {
	$before = $explorer->table('author')->count();
	$rows = (function () {
		yield from [['name' => 'Ygritte', 'web' => 'http://example.com']]; // both batches yield the key 0
		yield from [['name' => 'Gilly', 'web' => 'http://example.com']];
	})();

	Assert::same(2, $explorer->table('author')->insert($rows));
	Assert::same($before + 2, $explorer->table('author')->count());
});
