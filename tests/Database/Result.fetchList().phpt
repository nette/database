<?php

/**
 * Test: Nette\Database\Result::fetchFields()
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('fetches array of values from first row', function () use ($connection) {
	$res = $connection->query('SELECT name, id FROM author ORDER BY id');

	Assert::same(['Jakub Vrana', 11], $res->fetchList());
});


test('returns null when no rows found', function () use ($connection) {
	$res = $connection->query('SELECT id FROM author WHERE id = ?', 666);

	Assert::null($res->fetchList());
});
