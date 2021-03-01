<?php

/**
 * @dataProvider? ../../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");

$explorer->query('CREATE VIEW books_view AS SELECT * FROM book');

test('', function () use ($explorer) {
	$selection = $explorer->table('books_view')->where('id', 1);
	Assert::same(1, $selection->count());
});

test('', function () use ($connection) {
	$driver = $connection->getDriver();
	$columns = $driver->getColumns('books_view');
	$columnsNames = array_map(fn($item) => $item['name'], $columns);
	Assert::same(['id', 'author_id', 'translator_id', 'title', 'next_volume'], $columnsNames);
});

$explorer->query('DROP VIEW books_view');
