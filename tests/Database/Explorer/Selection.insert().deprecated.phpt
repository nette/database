<?php declare(strict_types=1);

/**
 * Test: deprecated bulk forms of Nette\Database\Table\Selection::insert() still work and emit a notice.
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('insert() with a list of rows is deprecated but still inserts', function () use ($explorer) {
	$result = null;
	Assert::error(
		function () use ($explorer, &$result) {
			$result = $explorer->table('author')->insert([
				['name' => 'Catelyn Stark', 'web' => 'http://example.com', 'born' => new DateTime('2011-11-11')],
				['name' => 'Sansa Stark', 'web' => 'http://example.com', 'born' => new DateTime('2021-11-11')],
			]);
		},
		E_USER_DEPRECATED,
		'Nette\Database\Table\Selection::insert() with a list of rows is deprecated, use insertMany() instead.',
	);
	Assert::same(2, $result);
	Assert::same(5, $explorer->table('author')->count());
});


test('insert() with a list on a GroupedSelection is deprecated but still inserts', function () use ($explorer) {
	$explorer->table('book_tag')->where('book_id', 1)->delete();

	$result = null;
	Assert::error(
		function () use ($explorer, &$result) {
			$result = $explorer->table('book')->get(1)->related('book_tag')->insert([
				['tag_id' => 21],
				['tag_id' => 22],
				['tag_id' => 23],
			]);
		},
		E_USER_DEPRECATED,
		'Nette\Database\Table\Selection::insert() with a list of rows is deprecated, use insertMany() instead.',
	);
	Assert::same(3, $result);
	Assert::same(7, $explorer->table('book_tag')->count());
});


test('deprecated insert() with a generator does not lose rows to colliding keys', function () use ($explorer) {
	$rows = (function () {
		yield from [['name' => 'Hodor', 'web' => 'http://example.com']]; // both batches yield the key 0
		yield from [['name' => 'Osha', 'web' => 'http://example.com']];
	})();

	$result = null;
	Assert::error(
		function () use ($explorer, $rows, &$result) {
			$result = $explorer->table('author')->insert($rows);
		},
		E_USER_DEPRECATED,
	);
	Assert::same(2, $result);
});
