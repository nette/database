<?php

/**
 * @dataProvider? ../../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");


test(function () use ($context) {
	$selection = $context->table('book');
	$selection->get(2)->author->name; //reading via reference

	$context->table('book')->get(2)->author->update(['name' => 'New name']);
	$context->table('book')->get(2)->update(['title' => 'New book title']);

	$selection->limit(NULL); //should invalidate cache of data and references
	$book = $selection->get(2);

	Assert::same('New book title', $book->title); //data cache invalidated
	Assert::same('New name', $book->author->name); //references NOT invalidated
});
