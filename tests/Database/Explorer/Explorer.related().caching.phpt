<?php

/**
 * Test: Nette\Database\Table: Shared related data caching.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$books = $explorer->table('book');
	foreach ($books as $book) {
		foreach ($book->related('book_tag') as $bookTag) {
			$bookTag->tag;
		}
	}

	$tags = [];
	foreach ($books as $book) {
		foreach ($book->related('book_tag_alt') as $bookTag) {
			$tags[] = $bookTag->tag->name;
		}
	}

	Assert::same([
		'PHP',
		'MySQL',
		'JavaScript',
		'Neon',
	], $tags);
});


test('', function () use ($explorer) {
	$authors = $explorer->table('author')->where('id', 11);
	$books = [];
	foreach ($authors as $author) {
		foreach ($author->related('book')->where('translator_id', null) as $book) {
			foreach ($book->related('book_tag') as $bookTag) {
				$books[] = $bookTag->tag->name;
			}
		}
	}
	Assert::same(['JavaScript'], $books);

	foreach ($authors as $author) {
		foreach ($author->related('book')->where('NOT translator_id', null) as $book) {
			foreach ($book->related('book_tag')->order('tag_id') as $bookTag) {
				$books[] = $bookTag->tag->name;
			}
		}
	}
	Assert::same(['JavaScript', 'PHP', 'MySQL'], $books);
});


test('', function () use ($explorer) {
	$explorer->query('UPDATE book SET translator_id = 12 WHERE id = 2');
	$author = $explorer->table('author')->get(11);

	foreach ($author->related('book')->limit(1) as $book) {
		$book->ref('author', 'translator_id')->name;
	}

	$translators = [];
	foreach ($author->related('book')->limit(2) as $book) {
		$translators[] = $book->ref('author', 'translator_id')->name;
	}
	sort($translators);

	Assert::same([
		'David Grudl',
		'Jakub Vrana',
	], $translators);
});



test('cache can\'t be affected by inner query!', function () use ($explorer) {
	$author = $explorer->table('author')->get(11);
	$secondBookTagRels = null;
	foreach ($author->related('book')->order('id') as $book) {
		if (!isset($secondBookTagRels)) {
			$bookFromAnotherSelection = $author->related('book')->where('id', $book->id)->fetch();
			$bookFromAnotherSelection->related('book_tag')->fetchPairs('id');
			$secondBookTagRels = [];
		} else {
			foreach ($book->related('book_tag') as $bookTagRel) {
				$secondBookTagRels[] = $bookTagRel->tag->name;
			}
		}
	}
	Assert::same(['JavaScript'], $secondBookTagRels);
});
