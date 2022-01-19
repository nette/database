<?php

/**
 * Test: Nette\Database\Table\SqlBuilder: Escaping with SqlLiteral.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Nette\Database\SqlLiteral;
use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('Leave literals lower-cased, also not-delimiting them is tested.', function () use ($explorer, $driverName) {
	$literal = match ($driverName) {
		'mysql' => new SqlLiteral('year(now())'),
		'pgsql' => new SqlLiteral('extract(year from now())::int'),
		'sqlite' => new SqlLiteral("cast(strftime('%Y', date('now')) as integer)"),
		'sqlsrv' => new SqlLiteral('year(cast(current_timestamp as datetime))'),
		default => Assert::fail("Unsupported driver $driverName"),
	};

	$selection = $explorer
		->table('book')
		->select($driverName === 'pgsql' ? '?::text AS col1' : '? AS col1', 'hi there!')
		->select('? AS col2', $literal);

	$row = $selection->fetch();
	Assert::same('hi there!', $row['col1']);
	Assert::same((int) date('Y'), $row['col2']);
});


test('', function () use ($explorer) {
	$bookTagsCount = [];
	$books = $explorer
		->table('book')
		->select('book.title, COUNT(DISTINCT :book_tag.tag_id) AS tagsCount')
		->group('book.title')
		->having('COUNT(DISTINCT :book_tag.tag_id) < ?', 2)
		->order('book.title');

	foreach ($books as $book) {
		$bookTagsCount[$book->title] = $book->tagsCount;
	}

	Assert::same([
		'JUSH' => 1,
		'Nette' => 1,
	], $bookTagsCount);
});


test('', function () use ($explorer, $driverName) {
	if ($driverName === 'mysql') {
		$authors = [];
		$selection = $explorer->table('author')->order('FIELD(name, ?)', ['Jakub Vrana', 'David Grudl', 'Geek']);
		foreach ($selection as $author) {
			$authors[] = $author->name;
		}

		Assert::same(['Jakub Vrana', 'David Grudl', 'Geek'], $authors);
	}
});


test('Test placeholder for GroupedSelection', function () use ($explorer, $driverName) {
	if ($driverName === 'sqlsrv') { // This syntax is not supported on SQL Server
		return;
	}

	$books = $explorer->table('author')->get(11)->related('book')->order('title = ? DESC', 'Test');
	foreach ($books as $book) {
	}

	$books = $explorer->table('author')->get(11)->related('book')->select('SUBSTR(title, ?)', 3);
	foreach ($books as $book) {
	}
});
