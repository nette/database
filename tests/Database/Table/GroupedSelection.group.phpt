<?php

/**
 * Test: Nette\Database\Table: grouping.
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test(function() use ($context) {
	$results = array(
		1 => array(21, 22),
		2 => array(23),
		3 => array(21),
		4 => array(21, 22)
	);
	$books = $context->table('book');
	foreach($books as $book){
		$pairs = $book->related('book_tag')->group('tag_id')->fetchPairs(NULL, 'tag_id');
		Assert::same($results[$book->id], array_values($pairs));
	}
});
