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


test('related() right after insert sees the referencing rows', function () use ($explorer) {
	$author = $explorer->table('author')->insert([
		'name' => 'Arya Stark',
		'web' => 'http://example.com',
		'born' => new DateTime('2011-11-11'),
	]);

	$explorer->table('book')->insert([
		'title' => 'Needle',
		'author_id' => $author->id,
	]);

	$books = $author->related('book.author_id');
	Assert::same(1, $books->count('*'));
	Assert::same('Needle', $books->fetch()->title);
});


test('row inserted via related() gets the group column and is fully usable', function () use ($explorer) {
	$author = $explorer->table('author')->insert([
		'name' => 'Sansa Stark',
		'web' => 'http://example.com',
		'born' => new DateTime('2011-11-11'),
	]);

	$book = $author->related('book.author_id')->insert(['title' => 'Alayne']);

	Assert::type(Nette\Database\Table\ActiveRow::class, $book);
	Assert::same($author->id, $book->author_id);
	Assert::same('Alayne', $book->title);
	Assert::same('Sansa Stark', $book->author->name); // ref() on a lazy row from GroupedSelection

	$tag = $explorer->table('tag')->insert(['name' => 'saga']);
	$explorer->table('book_tag')->insert(['book_id' => $book->id, 'tag_id' => $tag->id]);
	Assert::same(1, $book->related('book_tag')->count('*')); // related() on a lazy row from GroupedSelection

	$book->update(['title' => 'Alayne Stone']);
	Assert::same('Alayne Stone', $book->title);
});


test('lazy row completes its data even after related() executed the selection', function () use ($explorer) {
	$author = $explorer->table('author')->insert([
		'name' => 'Rickon Stark',
		'web' => 'http://example.com',
		'born' => new DateTime('2011-11-11'),
	]);

	Assert::same(0, $author->related('book.author_id')->count('*')); // executes the backing selection
	Assert::same('Rickon Stark', $author->name); // deferred fetch must still find the row
});


test('lazy row stays consistent across related(), completion and update()', function () use ($explorer) {
	$author = $explorer->table('author')->insert([
		'name' => 'Benjen Stark',
		'web' => 'http://example.com',
		'born' => new DateTime('2011-11-11'),
	]);
	$explorer->table('book')->insert([
		'title' => 'The Wall',
		'author_id' => $author->id,
	]);

	Assert::same(1, $author->related('book.author_id')->count('*'));
	Assert::same('Benjen Stark', $author->name); // completes data, row becomes canonical in the selection
	$author->update(['name' => 'First Ranger']);
	Assert::same('First Ranger', $author->name);
	Assert::same(1, $author->related('book.author_id')->count('*')); // cached prototype keys still match
});
