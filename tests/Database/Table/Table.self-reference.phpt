<?php

/**
 * Test: Nette\Database\Table: DiscoveredReflection with self-reference.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


$explorer->query('UPDATE book SET next_volume = 3 WHERE id IN (2,4)');


test('', function () use ($connection, $explorer) {
	$book = $explorer->table('book')->get(4);
	Assert::same('Nette', $book->volume->title);
	Assert::same('Nette', $book->ref('book', 'next_volume')->title);
});


test('', function () use ($explorer) {
	$book = $explorer->table('book')->get(3);
	Assert::same(2, $book->related('book.next_volume')->count('*'));
	Assert::same(2, $book->related('book', 'next_volume')->count('*'));
});
