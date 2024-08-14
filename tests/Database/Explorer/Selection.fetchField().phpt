<?php

/**
 * Test: Nette\Database\Table: Fetch field.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$title = $explorer->table('book')->where('id', 1)->fetchField('title');  // SELECT `title` FROM `book` WHERE `id` = 1
	Assert::same('1001 tipu a triku pro PHP', $title);
	Assert::null($explorer->table('book')->where('title', 'Nonexistent')->fetchField());
});


test('', function () use ($explorer) {
	$title = $explorer->table('book')->where('id', 1)->select('title')->fetchField();  // SELECT `title` FROM `book` WHERE `id` = 1
	Assert::same('1001 tipu a triku pro PHP', $title);
	Assert::null($explorer->table('book')->where('title', 'Nonexistent')->select('title')->fetchField());
});
