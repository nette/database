<?php

/**
 * Test: Nette\Database\Table: Find one item by URL.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$tags = [];
	$book = $explorer->table('book')->where('title', '1001 tipu a triku pro PHP')->fetch();  // SELECT * FROM `book` WHERE (`title` = ?)
	foreach ($book->related('book_tag')->where('tag_id', 21) as $book_tag) {  // SELECT * FROM `book_tag` WHERE (`book_tag`.`book_id` IN (1)) AND (`tag_id` = 21)
		$tags[] = $book_tag->tag->name;  // SELECT * FROM `tag` WHERE (`tag`.`id` IN (21))
	}

	Assert::same(['PHP'], $tags);
	Assert::null($explorer->table('book')->where('title', 'Nonexistent')->fetch());
});
