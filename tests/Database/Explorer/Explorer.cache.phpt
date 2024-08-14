<?php

/**
 * Test: Nette\Database\Table: Caching.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('Testing Selection caching', function () use ($explorer) {
	$sql = [];
	for ($i = 0; $i < 4; ++$i) {
		if ($i !== 2) {
			$bookSelection = $explorer->table('book')->wherePrimary(2);
		}

		$sql[] = $bookSelection->getSql();

		if ($i !== 2) {
			$book = $bookSelection->fetch();
			$book->title;
			$book->translator;
			if ($i === 1) {
				$book->author;
			} else {
				$bookSelection->__destruct();
			}
		} else {
			$bookSelection->__destruct();
		}
	}

	/*
	 * schedule:
	 * - fetch all columns / cycle 1
	 * - fetch used columns, require another and fetch all again / cycle 2, 3
	 * - fetch used column with new used column / cycle 4
	 */

	Assert::same([
		reformat('SELECT * FROM [book] WHERE ([book].[id] = ?)'),
		reformat('SELECT [id], [title], [translator_id] FROM [book] WHERE ([book].[id] = ?)'),
		reformat('SELECT * FROM [book] WHERE ([book].[id] = ?)'),
		reformat('SELECT [id], [title], [translator_id], [author_id] FROM [book] WHERE ([book].[id] = ?)'),
	], $sql);
});


test('Testing GroupedSelection reinvalidation caching', function () use ($explorer) {
	foreach ($explorer->table('author') as $author) {
		$stack[] = $selection = $author->related('book.author_id')->order('title');
		foreach ($selection as $book) {
			$book->title;
		}
	}

	reset($stack)->__destruct();

	$books = [];
	foreach ($explorer->table('author') as $author) {
		foreach ($author->related('book.author_id')->order('title') as $book) {
			if ($book->author_id == 12) {
				$books[$book->title] = $book->translator_id; // translator_id is new used column in the second loop
			}
		}
	}

	Assert::same([
		'Dibi' => 12,
		'Nette' => 12,
	], $books);
});


$cacheMemoryStorage = new Nette\Caching\Storages\MemoryStorage;
setUp(fn() => $cacheMemoryStorage->clean([Nette\Caching\Cache::ALL => true]));


test('', function () use ($explorer) {
	$selection = $explorer->table('book');
	foreach ($selection as $book) {
		$book->id;
	}

	$selection->__destruct();

	$authors = [];
	foreach ($explorer->table('book') as $book) {
		$authors[$book->author->name] = 1;
	}

	$authors = array_keys($authors);
	sort($authors);

	Assert::same([
		'David Grudl',
		'Jakub Vrana',
	], $authors);
});


test('', function () use ($explorer) {
	$relatedStack = [];
	foreach ($explorer->table('author') as $author) {
		$relatedStack[] = $related = $author->related('book.author_id');
		foreach ($related as $book) {
			$book->id;
		}
	}

	foreach ($relatedStack as $related) {
		$property = (new ReflectionClass($related))->getProperty('accessedColumns');
		$property->setAccessible(true);
		// checks if instances have shared data of accessed columns
		Assert::same(['id', 'author_id'], array_keys((array) $property->getValue($related)));
	}
});


test('Test saving joining keys even with 0 rows', function () use ($explorer) {
	$cols = [];
	for ($i = 0; $i < 2; ++$i) {
		$author = $explorer->table('author')->get(11);
		$books = $author->related('book')->where('translator_id', 99); // 0 rows
		$cols[] = $books->getPreviousAccessedColumns();
		foreach ($books as $book) {
		}

		$books->__destruct();
	}

	Assert::same([
		[],
		['id', 'author_id'],
	], $cols);
});


test('Test saving the union of needed cols, the second call is subset', function () use ($explorer) {
	$cols = [];
	for ($i = 0; $i < 3; ++$i) {
		$author = $explorer->table('author')->get(11);
		$books = $author->related('book');
		$cols[] = $books->getPreviousAccessedColumns();
		foreach ($books as $book) {
			if ($i === 0) {
				$book->translator_id;
			}

			$book->title;
		}

		$books->__destruct();
	}

	Assert::same([
		[],
		['id', 'author_id', 'translator_id', 'title'],
		['id', 'author_id', 'translator_id', 'title'],
	], $cols);
});


test('Test saving the union of needed cols, the second call is not subset', function () use ($explorer) {
	$cols = [];
	for ($i = 0; $i < 3; ++$i) {
		$author = $explorer->table('author')->get(11);
		$books = $author->related('book');
		$cols[] = $books->getPreviousAccessedColumns();
		foreach ($books as $book) {
			if ($i === 0) {
				$book->translator_id;
			} else {
				$book->title;
			}
		}

		$books->__destruct();
	}

	Assert::same([
		[],
		['id', 'author_id', 'translator_id'],
		['id', 'author_id', 'translator_id', 'title'],
	], $cols);
});


test('Test multiple use of same selection', function () use ($explorer) {
	$sql = [];
	$explorer->getConnection()->onQuery[] = function ($_, $result) use (&$sql) {
		$sql[] = $result->getQueryString();
	};

	for ($i = 0; $i < 3; ++$i) {
		$bookSelection = $explorer->table('book');
		count($bookSelection);

		foreach ($bookSelection->where('author_id = ?', 11) as $book) {
			$book->title;
			if ($i >= 1) {
				$book->translator_id;
			}
		}

		$bookSelection->__destruct();
	}

	Assert::same([
		reformat('SELECT * FROM [book]'), //First round
		reformat('SELECT * FROM [book] WHERE ([author_id] = ?)'),
		reformat('SELECT [id] FROM [book]'), //Second round
		reformat('SELECT [id], [title] FROM [book] WHERE ([author_id] = ?)'),
		reformat('SELECT * FROM [book] WHERE ([author_id] = ?)'), //Missing translator_id
		reformat('SELECT [id] FROM [book]'), //Third round
		reformat('SELECT [id], [title], [translator_id] FROM [book] WHERE ([author_id] = ?)'),
	], $sql);
});
