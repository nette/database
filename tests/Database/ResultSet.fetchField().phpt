<?php

/**
 * Test: Nette\Database\ResultSet: Fetch field.
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test(function () use ($connection) {
	$res = $connection->query('SELECT name, id FROM author ORDER BY id');

	Assert::same('Jakub Vrana', $res->fetchField());
	Assert::same(12, $res->fetchField(1));
	Assert::same('Geek', $res->fetchField('name'));
});
