<?php

/**
 * Test: Nette\Database\Table: Related().
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$books1 = $books2 = $books3 = [];

	foreach ($explorer->table('author') as $author) {  // SELECT * FROM `author`
		foreach ($author->related('book', 'translator_id') as $book) {  // SELECT * FROM `book` WHERE (`book`.`translator_id` IN (11, 12, 13))
			$books1[$book->title] = $author->name;
		}

		foreach ($author->related('book.author_id') as $book) {  // SELECT * FROM `book` WHERE (`book`.`author_id` IN (11, 12, 13))
			$books2[$book->title] = $author->name;
		}

		foreach ($author->related('book') as $book) {  // SELECT * FROM `book` WHERE (`book`.`author_id` IN (11, 12, 13))
			$books3[$book->title] = $author->name;
		}
	}

	Assert::same([
		'1001 tipu a triku pro PHP' => 'Jakub Vrana',
		'Nette' => 'David Grudl',
		'Dibi' => 'David Grudl',
	], $books1);

	$expectBooks = [
		'1001 tipu a triku pro PHP' => 'Jakub Vrana',
		'JUSH' => 'Jakub Vrana',
		'Nette' => 'David Grudl',
		'Dibi' => 'David Grudl',
	];

	Assert::same($expectBooks, $books2);
	Assert::same($expectBooks, $books3);
});


test('', function () use ($explorer) {
	$tagsAuthors = [];
	foreach ($explorer->table('tag') as $tag) {
		$book_tags = $tag->related('book_tag')->group('book_tag.tag_id, book.author_id, book.author.name')->select('book.author_id')->order('book.author.name');
		foreach ($book_tags as $book_tag) {
			$tagsAuthors[$tag->name][] = $book_tag->ref('author', 'author_id')->name;
		}
	}

	Assert::same([
		'PHP' => [
			'David Grudl',
			'Jakub Vrana',
		],
		'MySQL' => [
			'David Grudl',
			'Jakub Vrana',
		],
		'JavaScript' => [
			'Jakub Vrana',
		],
	], $tagsAuthors);
});


test('', function () use ($explorer) {
	$counts1 = $counts2 = [];
	foreach ($explorer->table('author')->order('id') as $author) {
		$counts1[] = $author->related('book.author_id')->count('id');
		$counts2[] = $author->related('book.author_id')->where('translator_id', null)->count('id');
	}

	Assert::same([2, 2, 0], $counts1);
	Assert::same([1, 0, 0], $counts2);
});


test('', function () use ($explorer) {
	$author = $explorer->table('author')->get(11);
	$books = $author->related('book')->where('translator_id', 11);
	Assert::same('1001 tipu a triku pro PHP', $books->fetch()->title);
	Assert::null($books->fetch());

	Assert::same('1001 tipu a triku pro PHP', $author->related('book')->fetch()->title);

	Assert::same('JUSH', $author->related('book', null)->where('translator_id', null)->fetch()->title);
});
