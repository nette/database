<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Table: Caching.
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('column access caching across queries', function () use ($explorer) {
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


test('related selection caching consistency', function () use ($explorer) {
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


test('cache invalidation after destruction', function () use ($explorer) {
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


test('shared accessed columns in related instances', function () use ($explorer) {
	$relatedStack = [];
	foreach ($explorer->table('author') as $author) {
		$relatedStack[] = $related = $author->related('book.author_id');
		foreach ($related as $book) {
			$book->id;
		}
	}

	foreach ($relatedStack as $related) {
		$property = (new ReflectionClass($related))->getProperty('accessedColumns');
		// checks if instances have shared data of accessed columns
		Assert::same(['id', 'author_id'], array_keys((array) $property->getValue($related)));
	}
});


test('previous accessed columns tracking', function () use ($explorer) {
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


test('accessed columns across iterations', function () use ($explorer) {
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


test('incremental column access tracking', function () use ($explorer) {
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


test('SQL query logging with caching', function () use ($explorer) {
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


test('table without primary key never narrows the select', function () use ($explorer) {
	$explorer->table('note')->insert(['book_id' => 1, 'note' => 'test note']);

	$sql = [];
	for ($i = 0; $i < 2; ++$i) {
		$selection = $explorer->table('note');
		$sql[] = $selection->getSql();
		foreach ($selection as $row) {
			$row->book_id;
			if ($i > 0) {
				Assert::same('test note', $row->note); // reading a column unknown to the cache must not throw
			}
		}

		$selection->__destruct();
	}

	Assert::same([
		reformat('SELECT * FROM [note]'),
		reformat('SELECT * FROM [note]'), // never narrowed, a re-query would need the primary key
	], $sql);
});


test('a column probed by isset() before it existed is readable after being added', function () use ($explorer) {
	$connection = $explorer->getConnection();
	$connection->query('DROP TABLE IF EXISTS probe_test');
	$connection->query('CREATE TABLE probe_test (id INTEGER NOT NULL PRIMARY KEY, a INTEGER)');
	$connection->query('INSERT INTO probe_test (id, a) VALUES (1, 10)');
	$explorer->getStructure()->rebuild();

	$extraValues = [];
	for ($i = 0; $i < 3; ++$i) {
		if ($i === 1) {
			$connection->query('ALTER TABLE probe_test ADD extra INTEGER'); // "migration"
			$connection->query('UPDATE probe_test SET extra = 42');
		}

		$selection = $explorer->table('probe_test');
		foreach ($selection as $row) {
			$row->a;
			if ($i === 0) {
				isset($row->extra); // probes a column that does not exist yet
			} else {
				if ($i === 1) {
					// isset() deliberately does not reload the narrowed row: a reload here would permanently
					// disable narrowing for every legitimate isset() probe of an absent column
					Assert::false(isset($row->extra));
				}

				$extraValues[] = $row->extra; // __get() reloads and heals the row, must not throw
				Assert::true(isset($row->extra));
			}
		}

		$selection->__destruct();
	}

	Assert::same([42, 42], $extraValues);
});
