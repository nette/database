<?php

/**
 * Test: Nette\Database\Table\GroupedSelection: Insert operations
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	$book->related('book_tag')->insert(['tag_id' => 23]);

	Assert::same(3, $book->related('book_tag')->count());
	Assert::same(3, $book->related('book_tag')->count('*'));

	$book->related('book_tag')->where('tag_id', 23)->delete();
});


test('test counting already fetched rows', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	iterator_to_array($book->related('book_tag'));
	$book->related('book_tag')->insert(['tag_id' => 23]);
	Assert::same(3, $book->related('book_tag')->count());
});
