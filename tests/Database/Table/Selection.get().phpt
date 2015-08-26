<?php

/**
 * Test: Nette\Database\Table: Single row detail.
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test(function () use ($context) {
	$book = $context->table('book')->get(1);  // SELECT * FROM `book` WHERE (`id` = ?)

	Assert::same([
		'id' => 1,
		'author_id' => 11,
		'translator_id' => 11,
		'title' => '1001 tipu a triku pro PHP',
		'next_volume' => NULL,
	], $book->toArray());

	Assert::exception(function() use ($context){
		$context->table('not_existing_table')->get(1);
	}, Nette\InvalidArgumentException::class, "Table 'not_existing_table' does not exist.");
});
