<?php

/**
 * Test: Nette\Database\Table: Aggregation functions.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('basic count aggregation', function () use ($explorer) {
	$count = $explorer->table('book')->count('*');  // SELECT COUNT(*) FROM `book`
	Assert::same(4, $count);
});


test('related count aggregation', function () use ($explorer) {
	$tags = [];
	foreach ($explorer->table('book') as $book) {  // SELECT * FROM `book`
		$count = $book->related('book_tag')->count('*');  // SELECT COUNT(*), `book_id` FROM `book_tag` WHERE (`book_tag`.`book_id` IN (1, 2, 3, 4)) GROUP BY `book_id`
		$tags[$book->title] = $count;
	}

	Assert::same([
		'1001 tipu a triku pro PHP' => 2,
		'JUSH' => 1,
		'Nette' => 1,
		'Dibi' => 2,
	], $tags);
});


test('group by with join condition', function () use ($explorer) {
	$authors = $explorer->table('author')->where(':book.translator_id IS NOT NULL')->group('author.id');  // SELECT `author`.* FROM `author` INNER JOIN `book` ON `author`.`id` = `book`.`author_id` WHERE (`book`.`translator_id` IS NOT NULL) GROUP BY `author`.`id`
	Assert::count(2, $authors);
	Assert::same(2, $authors->count('DISTINCT author.id'));  // SELECT COUNT(DISTINCT author.id) FROM `author` INNER JOIN `book` ON `author`.`id` = `book`.`author_id` WHERE (`book`.`translator_id` IS NOT NULL)
});


test('having clause with group by', function () use ($explorer) {
	$authors = $explorer->table('book')->group('book.id')->having('COUNT(DISTINCT :book_tag.tag_id) < 2');  // SELECT `author`.* FROM `author` INNER JOIN `book` ON `author`.`id` = `book`.`author_id` WHERE (`book`.`translator_id` IS NOT NULL) GROUP BY `author`.`id`
	Assert::count(2, $authors);
	Assert::same(2, $authors->count('DISTINCT author.id'));  // SELECT COUNT(DISTINCT author.id) FROM `author` INNER JOIN `book` ON `author`.`id` = `book`.`author_id` WHERE (`book`.`translator_id` IS NOT NULL)
});


test('distinct count in related aggregation', function () use ($explorer) {
	$bookTags = [];
	foreach ($explorer->table('book') as $book) {
		$book_tags = $book->related('book_tag');
		$bookTags[$book->title] = $book_tags->count('DISTINCT tag.id');
	}

	Assert::same([
		'1001 tipu a triku pro PHP' => 2,
		'JUSH' => 1,
		'Nette' => 1,
		'Dibi' => 2,
	], $bookTags);
});


test('filtering groups by related count', function () use ($explorer) {
	$bookTags = [];
	foreach ($explorer->table('book')->group('book.id, book.title')->having('COUNT(DISTINCT :book_tag.tag_id) < 2') as $book) {
		$book_tags = $book->related('book_tag');
		$bookTags[$book->title] = $book_tags->count('DISTINCT tag.id');
	}

	Assert::same([
		'JUSH' => 1,
		'Nette' => 1,
	], $bookTags);
});

test('nested group by and having', function () use ($explorer) {
	$bookTags = [];
	foreach ($explorer->table('author') as $author) {
		$books = $author->related('book');
		$bookTags[$author->name] = $books->group('book.id')->having('COUNT(DISTINCT :book_tag.tag_id) < 2')->count('DISTINCT book.id');
	}

	Assert::same([
		'Jakub Vrana' => 1,
		'David Grudl' => 1,
		'Geek' => 0,
	], $bookTags);
});
