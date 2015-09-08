<?php

/**
 * @dataProvider? ../../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");

// add additional tags (not relevant to other tests)
$context->query("INSERT INTO book_tag_alt (book_id, tag_id, state) VALUES (1, 24, 'private');");
$context->query("INSERT INTO book_tag_alt (book_id, tag_id, state) VALUES (2, 24, 'private');");
$context->query("INSERT INTO book_tag_alt (book_id, tag_id, state) VALUES (2, 22, 'private');");

test(function () use ($connection, $context) {

	$context->table('author')->get(11); // have to build cache first

	$count = 0;
	$connection->onQuery[] = function() use (& $count) {
		$count++;
	};

	foreach ($context->table('book') as $book) {
		foreach ($book->related('book_tag_alt')->where('state', 'private') as $bookTag) {
			$tag = $bookTag->tag;
		}
	}

	Assert::same(3, $count);
});
