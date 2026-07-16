<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Table\Selection::insertMany()
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('inserts a list of rows and returns affected count', function () use ($explorer) {
	Assert::same(3, $explorer->table('author')->count());
	$result = $explorer->table('author')->insertMany([
		['name' => 'Catelyn Stark', 'web' => 'http://example.com', 'born' => new DateTime('2011-11-11')],
		['name' => 'Sansa Stark', 'web' => 'http://example.com', 'born' => new DateTime('2021-11-11')],
	]);
	Assert::same(2, $result);
	Assert::same(5, $explorer->table('author')->count());
});


test('works on a GroupedSelection (related)', function () use ($explorer) {
	$explorer->table('book_tag')->where('book_id', 1)->delete();

	Assert::same(4, $explorer->table('book_tag')->count());
	$result = $explorer->table('book')->get(1)->related('book_tag')->insertMany([
		['tag_id' => 21],
		['tag_id' => 22],
	]);
	Assert::same(2, $result);
	Assert::same(6, $explorer->table('book_tag')->count());
});


test('empty list inserts nothing and returns 0', function () use ($explorer) {
	$count = $explorer->table('author')->count();
	Assert::same(0, $explorer->table('author')->insertMany([]));
	Assert::same($count, $explorer->table('author')->count());
});


test('rejects a single associative row', function () use ($explorer) {
	$count = $explorer->table('author')->count();
	Assert::exception(
		fn() => $explorer->table('author')->insertMany(['name' => 'Arya Stark']),
		Nette\InvalidArgumentException::class,
	);
	Assert::same($count, $explorer->table('author')->count()); // nothing was inserted
});


test('accepts a generator', function () use ($explorer) {
	$rows = (function () {
		yield ['name' => 'Rickon Stark', 'web' => 'http://example.com'];
	})();

	Assert::same(1, $explorer->table('author')->insertMany($rows));
});


test('accepts a generator with colliding keys (yield from)', function () use ($explorer) {
	$batch1 = [['name' => 'Hodor', 'web' => 'http://example.com']];
	$batch2 = [['name' => 'Osha', 'web' => 'http://example.com']];
	$rows = (function () use ($batch1, $batch2) {
		yield from $batch1; // both batches yield the key 0
		yield from $batch2;
	})();

	Assert::same(2, $explorer->table('author')->insertMany($rows));
});


test('accepts rows under non-sequential keys, e.g. left by array_filter()', function () use ($explorer) {
	$rows = array_filter([
		['name' => 'Robb Stark', 'web' => 'http://example.com'],
		['name' => 'skip me', 'web' => ''],
		['name' => 'Bran Stark', 'web' => 'http://example.com'],
	], fn($row) => $row['web'] !== ''); // leaves keys 0 and 2

	Assert::same(2, $explorer->table('author')->insertMany($rows));
});


test('a GroupedSelection assigns the group to rows under non-sequential keys too', function () use ($explorer) {
	$before = $explorer->table('book_tag')->where('book_id', 3)->count();

	Assert::same(2, $explorer->table('book')->get(3)->related('book_tag')->insertMany([
		0 => ['tag_id' => 23],
		2 => ['tag_id' => 24],
	]));

	Assert::same($before + 2, $explorer->table('book_tag')->where('book_id', 3)->count());
});


test('accepts Row objects, which the preprocessor supports', function () use ($explorer) {
	$rows = [
		Nette\Database\Row::from(['name' => 'Jon Snow', 'web' => 'http://example.com']),
		Nette\Database\Row::from(['name' => 'Ygritte', 'web' => 'http://example.com']),
	];

	Assert::same(2, $explorer->table('author')->insertMany($rows));
});


test('does not modify the caller\'s Row objects', function () use ($explorer) {
	$explorer->table('book_tag')->where('book_id', 1)->delete();

	$row = Nette\Database\Row::from(['tag_id' => 21]);
	$explorer->table('book')->get(1)->related('book_tag')->insertMany([$row]);

	Assert::same(['tag_id' => 21], (array) $row); // no book_id leaked into it
});
