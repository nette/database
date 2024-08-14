<?php

/**
 * Test: Nette\Database\Table: Reference ref().
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


Assert::same('Jakub Vrana', $explorer->table('book')->get(1)->ref('author')->name);


test('', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	$book->update([
		'translator_id' => 12,
	]);

	$book = $explorer->table('book')->get(1);
	Assert::same('David Grudl', $book->ref('author', 'translator_id')->name);
	Assert::same('Jakub Vrana', $book->ref('author', 'author_id')->name);
});


test('', function () use ($explorer) {
	Assert::null($explorer->table('book')->get(2)->ref('author', 'translator_id'));
});

test('', function () use ($explorer, $connection) {
	$counter = 0;

	$connection->onQuery[] = function ($connection, $result) use (&$counter) {
		$counter++;
	};

	$table = $explorer->table('book');

	$names = [];
	foreach ($table as $book) {
		$translator = $book->ref('author', 'translator_id');
	}

	Assert::equal(2, $counter);
});
