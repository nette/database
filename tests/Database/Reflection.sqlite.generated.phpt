<?php declare(strict_types=1);

/**
 * Test: SqliteDriver::getColumns() generated and hidden columns
 * @dataProvider? databases.ini  sqlite
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
$driver = $connection->getDriver();


test('generated columns are listed', function () use ($connection, $driver) {
	$connection->query('CREATE TABLE gen_test (id INTEGER PRIMARY KEY, price REAL, qty INTEGER, total REAL GENERATED ALWAYS AS (price * qty) VIRTUAL, label TEXT AS (price || qty) STORED)');

	Assert::same(
		['id' => 'INTEGER', 'price' => 'REAL', 'qty' => 'INTEGER', 'total' => 'REAL', 'label' => 'TEXT'],
		array_column($driver->getColumns('gen_test'), 'nativetype', 'name'),
	);
});


test('hidden columns of a virtual table are not listed', function () use ($connection, $driver) {
	$connection->query('CREATE VIRTUAL TABLE fts_test USING fts5(body)');

	Assert::same(['body'], array_column($driver->getColumns('fts_test'), 'name'));
});
