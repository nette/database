<?php

/**
 * Test: Nette\Database\Table: grouping.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$authors = $explorer->table('book')->group('author_id')->order('author_id')->fetchPairs('author_id', 'author_id');
	Assert::same([11, 12], array_values($authors));
});
