<?php

/**
 * Test: Nette\Database\Table: Subqueries.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$apps = [];
	$unknownBorn = $explorer->table('author')->where('born', null); // authors with unknown date of born
	foreach ($explorer->table('book')->where('author_id', $unknownBorn) as $book) { // their books: SELECT `id` FROM `author` WHERE (`born` IS NULL), SELECT * FROM `book` WHERE (`author_id` IN (11, 12))
		$apps[] = $book->title;
	}

	Assert::same([
		'1001 tipu a triku pro PHP',
		'JUSH',
		'Nette',
		'Dibi',
	], $apps);
});
