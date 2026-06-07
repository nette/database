<?php declare(strict_types=1);

/**
 * Test: SQLiteEngine::getColumns() generated and hidden columns
 * @dataProvider? databases.ini  sqlite
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB();
$driver = $connection->getDatabaseEngine();


test('generated columns are listed', function () use ($connection, $driver) {
	$connection->query('CREATE TABLE gen_test (id INTEGER PRIMARY KEY, price REAL, qty INTEGER, total REAL GENERATED ALWAYS AS (price * qty) VIRTUAL, label TEXT AS (price || qty) STORED)');

	Assert::same(
		['id' => 'INTEGER', 'price' => 'REAL', 'qty' => 'INTEGER', 'total' => 'REAL', 'label' => 'TEXT'],
		array_column($driver->getColumns('gen_test'), 'nativeType', 'name'),
	);
});


test('hidden columns of a virtual table are not listed', function () use ($connection, $driver) {
	$connection->query('CREATE VIRTUAL TABLE fts_test USING fts5(body)');

	Assert::same(['body'], array_column($driver->getColumns('fts_test'), 'name'));
});
