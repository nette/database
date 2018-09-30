<?php

/**
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");


test(function () use ($context) {
	$row = $context->table('book')->get(2);
	Assert::same('Jakub Vrana', $row->author->name);
	Assert::true(isset($row->author->name));
});
