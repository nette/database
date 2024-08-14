<?php

/**
 * Test: Nette\Database\Table: Calling __toString().
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	Assert::same('2', (string) $explorer->table('book')->get(2));
});


test('', function () use ($explorer) {
	Assert::same(2, $explorer->table('book')->get(2)->getPrimary());
});
