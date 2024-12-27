<?php

/**
 * Test: Nette\Database\Connection query methods.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('executes parameterized query and returns ResultSet', function () use ($connection) {
	$res = $connection->query('SELECT id FROM author WHERE id = ?', 11);
	Assert::type(Nette\Database\ResultSet::class, $res);
	Assert::same('SELECT id FROM author WHERE id = ?', $res->getQueryString());
	Assert::same([11], $res->getParameters());
	Assert::same('SELECT id FROM author WHERE id = ?', $connection->getLastQueryString());
});


test('multiple query parameters', function () use ($connection) {
	$res = $connection->query('SELECT id FROM author WHERE id = ? OR id = ?', 11, 12);
	Assert::same('SELECT id FROM author WHERE id = ? OR id = ?', $res->getQueryString());
	Assert::same([11, 12], $res->getParameters());
});


test('query with array of parameters', function () use ($connection) {
	$res = $connection->queryArgs('SELECT id FROM author WHERE id = ? OR id = ?', [11, 12]);
	Assert::same('SELECT id FROM author WHERE id = ? OR id = ?', $res->getQueryString());
	Assert::same([11, 12], $res->getParameters());
});
