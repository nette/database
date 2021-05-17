<?php

/**
 * Test: Nette\Database\Table: Backward join.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Nette\Database\Driver;
use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");
$driver = $connection->getDriver();


test('', function () use ($explorer) {
	$authorTagsCount = [];
	$authors = $explorer
		->table('author')
		->select('author.name, COUNT(DISTINCT :book:book_tag.tag_id) AS tagsCount')
		->group('author.name')
		->having('COUNT(DISTINCT :book:book_tag.tag_id) < 3')
		->order('tagsCount DESC');

	foreach ($authors as $author) {
		$authorTagsCount[$author->name] = $author->tagsCount;
	}

	Assert::same([
		'David Grudl' => 2,
		'Geek' => 0,
	], $authorTagsCount);
});


test('', function () use ($explorer, $driver) {
	$authorsSelection = $explorer->table('author')->where(':book.translator_id IS NOT NULL')->wherePrimary(12);

	if ($driver->isSupported(Driver::SUPPORT_SCHEMA)) {
		Assert::same(
			reformat('SELECT [author].* FROM [author] LEFT JOIN [public].[book] [book] ON [author].[id] = [book].[author_id] WHERE ([book].[translator_id] IS NOT NULL) AND ([author].[id] = ?)'),
			$authorsSelection->getSql()
		);
	} else {
		Assert::same(
			reformat('SELECT [author].* FROM [author] LEFT JOIN [book] ON [author].[id] = [book].[author_id] WHERE ([book].[translator_id] IS NOT NULL) AND ([author].[id] = ?)'),
			$authorsSelection->getSql()
		);
	}

	$authors = [];
	foreach ($authorsSelection as $author) {
		$authors[$author->id] = $author->name;
	}

	Assert::same([12 => 'David Grudl'], $authors);
});


test('', function () use ($explorer) {
	$count = $explorer->table('author')->where(':book(translator).title LIKE ?', '%JUSH%')->count('*'); // by translator_id
	Assert::same(0, $count);
});
