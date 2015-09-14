<?php

/**
 * @dataProvider? ../../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");

$context->query('CREATE VIEW books_view AS SELECT * FROM book');

test(function () use ($context) {
	$selection = $context->table('books_view')->where('id', 1);
	Assert::same(1, $selection->count());
});

test(function () use ($connection) {
	$driver = $connection->getSupplementalDriver();
	$columns = $driver->getColumns('books_view');
	$columnsNames = array_map(function ($item) {
		return $item['name'];
	}, $columns);
	Assert::same(['id', 'author_id', 'translator_id', 'title', 'next_volume'], $columnsNames);
});

$context->query('DROP VIEW books_view');
