<?php declare(strict_types=1);

/**
 * Test: SqliteDriver::getColumns() autoincrement detection
 * @dataProvider? databases.ini  sqlite
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
$driver = $connection->getDriver();


test('a column whose name is a substring of the autoincrement column is not flagged', function () use ($connection, $driver) {
	$connection->query('CREATE TABLE ai_test (id INTEGER, paid INTEGER PRIMARY KEY AUTOINCREMENT)');

	$autoincrement = array_column($driver->getColumns('ai_test'), 'autoincrement', 'name');
	Assert::false($autoincrement['id']);
	Assert::true($autoincrement['paid']);
});


test('a column name with regex meta-characters does not break the detection', function () use ($connection, $driver) {
	$connection->query('CREATE TABLE ai_test2 ("price(usd)" TEXT, id INTEGER PRIMARY KEY AUTOINCREMENT)');

	$autoincrement = array_column($driver->getColumns('ai_test2'), 'autoincrement', 'name');
	Assert::false($autoincrement['price(usd)']);
	Assert::true($autoincrement['id']);
});
