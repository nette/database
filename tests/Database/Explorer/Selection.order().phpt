<?php

/**
 * Test: Nette\Database\Table: Search and order items.
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
	foreach ($explorer->table('book')->where('title LIKE ?', '%t%')->order('title')->limit(3) as $book) {  // SELECT * FROM `book` WHERE (`title` LIKE ?) ORDER BY `title` LIMIT 3
		$apps[] = $book->title;
	}

	Assert::same([
		'1001 tipu a triku pro PHP',
		'Nette',
	], $apps);
});
