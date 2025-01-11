<?php

/**
 * @dataProvider? ../../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$row = $explorer->table('book')->get(2);
	Assert::same('Jakub Vrana', $row->author->name);
	Assert::true(isset($row->author->name));
});
