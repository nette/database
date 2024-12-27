<?php

/**
 * Test: Nette\Database\ResultSet: Fetch field.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('fetches first field from first row', function () use ($connection) {
	$res = $connection->query('SELECT name, id FROM author ORDER BY id');

	Assert::same('Jakub Vrana', $res->fetchField());
});


test('returns null when no rows found', function () use ($connection) {
	$res = $connection->query('SELECT id FROM author WHERE id = ?', 666);

	Assert::null($res->fetchField());
});
