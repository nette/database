<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Table\Selection::insert() returns a lazy row holding only the primary key;
 * the remaining columns are fetched on first access of any other column.
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('reading only the primary key triggers no SELECT', function () use ($explorer, $connection) {
	$row = $explorer->table('author')->insert([
		'name' => 'Eddard Stark',
		'web' => 'http://example.com',
		'born' => new DateTime('2011-11-11'),
	]);

	$count = 0;
	$connection->onQuery[] = function () use (&$count) { $count++; };

	Assert::type('int', $row->id);
	Assert::same(0, $count);
});


test('reading a non-primary column triggers exactly one SELECT', function () use ($explorer, $connection) {
	$row = $explorer->table('author')->insert([
		'name' => 'Catelyn Stark',
		'web' => 'http://example.com',
		'born' => new DateTime('2011-11-11'),
	]);

	$count = 0;
	$connection->onQuery[] = function () use (&$count) { $count++; };

	Assert::same('Catelyn Stark', $row->name);
	Assert::same(1, $count);

	Assert::same('http://example.com', $row->web); // already loaded
	Assert::same(1, $count);
});


test('toArray() materializes the whole row', function () use ($explorer, $connection) {
	$row = $explorer->table('author')->insert([
		'name' => 'Robb Stark',
		'web' => 'http://example.com',
		'born' => new DateTime('2011-11-11'),
	]);

	$count = 0;
	$connection->onQuery[] = function () use (&$count) { $count++; };

	$arr = $row->toArray();
	Assert::same(1, $count);
	Assert::same('Robb Stark', $arr['name']);
});


test('column computed by the database is read correctly', function () use ($explorer) {
	$row = $explorer->table('author')->insert([
		'name' => $explorer->literal('LOWER(?)', 'Eddard Stark'),
		'web' => 'http://example.com',
		'born' => new DateTime('2011-11-11'),
	]);

	Assert::same('eddard stark', $row->name);
});


test('relationship is accessible right after insert', function () use ($explorer) {
	$author = $explorer->table('author')->insert([
		'name' => 'Jon Snow',
		'web' => 'http://example.com',
		'born' => new DateTime('2011-11-11'),
	]);

	$book = $explorer->table('book')->insert([
		'title' => 'Winterfell',
		'author_id' => $author->id,
	]);

	Assert::same('Jon Snow', $book->author->name);
});
