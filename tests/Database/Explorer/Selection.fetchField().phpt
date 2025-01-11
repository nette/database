<?php

/**
 * Test: Nette\Database\Table: Fetch field.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('fetch specific field value or null if absent', function () use ($explorer) {
	$title = $explorer->table('book')->where('id', 1)->fetchField('title');  // SELECT `title` FROM `book` WHERE `id` = 1
	Assert::same('1001 tipu a triku pro PHP', $title);
	Assert::null($explorer->table('book')->where('title', 'Nonexistent')->fetchField());
});


test('fetch field via select method returns correct value', function () use ($explorer) {
	$title = $explorer->table('book')->where('id', 1)->select('title')->fetchField();  // SELECT `title` FROM `book` WHERE `id` = 1
	Assert::same('1001 tipu a triku pro PHP', $title);
	Assert::null($explorer->table('book')->where('title', 'Nonexistent')->select('title')->fetchField());
});
