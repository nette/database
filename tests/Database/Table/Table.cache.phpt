<?php

/**
 * Test: Nette\Database\Table: Caching.
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test(function () use ($context) { // Testing Selection caching
	$sql = [];
	for ($i = 0; $i < 4; $i += 1) {
		if ($i !== 2) {
			$bookSelection = $context->table('book')->wherePrimary(2);
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


test(function () use ($context) { // Testing GroupedSelection reinvalidation caching
	foreach ($context->table('author') as $author) {
		$stack[] = $selection = $author->related('book.author_id')->order('title');
		foreach ($selection as $book) {
			$book->title;
		}
	}

	reset($stack)->__destruct();


	$books = [];
	foreach ($context->table('author') as $author) {
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


before(function () use ($cacheMemoryStorage) {
	$cacheMemoryStorage->clean([Nette\Caching\Cache::ALL => TRUE]);
});


test(function () use ($context) {
	$selection = $context->table('book');
	foreach ($selection as $book) {
		$book->id;
	}
	$selection->__destruct();

	$authors = [];
	foreach ($context->table('book') as $book) {
		$authors[$book->author->name] = 1;
	}

	$authors = array_keys($authors);
	sort($authors);

	Assert::same([
		'David Grudl',
		'Jakub Vrana',
	], $authors);
});


test(function () use ($context) {
	$relatedStack = [];
	foreach ($context->table('author') as $author) {
		$relatedStack[] = $related = $author->related('book.author_id');
		foreach ($related as $book) {
			$book->id;
		}
	}

	foreach ($relatedStack as $related) {
		$property = (new ReflectionClass($related))->getProperty('accessedColumns');
		$property->setAccessible(TRUE);
		// checks if instances have shared data of accessed columns
		Assert::same(['id', 'author_id'], array_keys((array) $property->getValue($related)));
	}
});


test(function () use ($context) { // Test saving joining keys even with 0 rows
	$cols = [];
	for ($i = 0; $i < 2; $i += 1) {
		$author = $context->table('author')->get(11);
		$books = $author->related('book')->where('translator_id', 99); // 0 rows
		$cols[] = $books->getPreviousAccessedColumns();
		foreach ($books as $book) {}
		$books->__destruct();
	}

	Assert::same([
		[],
		['id', 'author_id'],
	], $cols);
});


test(function () use ($context) { // Test saving the union of needed cols, the second call is subset
	$cols = [];
	for ($i = 0; $i < 3; $i += 1) {
		$author = $context->table('author')->get(11);
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


test(function () use ($context) { // Test saving the union of needed cols, the second call is not subset
	$cols = [];
	for ($i = 0; $i < 3; $i += 1) {
		$author = $context->table('author')->get(11);
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


test(function () use ($context) { // Test multiple use of same selection
	$sql = [];
	$context->getConnection()->onQuery[] = function($_, $result) use (&$sql) {
		$sql[] = $result->getQueryString();
	};

	for ($i = 0; $i < 3; $i += 1) {
		$bookSelection = $context->table('book');
		count($bookSelection);

		foreach ($bookSelection->where('author_id = ?', 11) as $book) {
			$book->title;
			if ($i>=1) {
				$book->translator_id;
			}
		}
		$bookSelection->__destruct();
	}

	Assert::same([
		reformat('SELECT * FROM [book]'), //First round
		reformat('SELECT * FROM [book] WHERE ([author_id] = 11)'),
		reformat('SELECT [id] FROM [book]'), //Second round
		reformat('SELECT [id], [title] FROM [book] WHERE ([author_id] = 11)'),
		reformat('SELECT * FROM [book] WHERE ([author_id] = 11)'), //Missing translator_id
		reformat('SELECT [id] FROM [book]'), //Third round
		reformat('SELECT [id], [title], [translator_id] FROM [book] WHERE ([author_id] = 11)'),

	], $sql);
});
