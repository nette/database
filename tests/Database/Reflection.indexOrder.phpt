<?php declare(strict_types=1);

/**
 * Test: index column order in driver reflection
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
$driver = $connection->getDriver();

$connection->query('DROP TABLE IF EXISTS idx_test');
$connection->query('CREATE TABLE idx_test (a INT NOT NULL, b INT NOT NULL)');
$connection->query('CREATE INDEX idx_ba ON idx_test (b, a)');


test('multi-column index reports columns in index order, not in table order', function () use ($driver) {
	$indexes = array_column($driver->getIndexes('idx_test'), 'columns', 'name');
	Assert::same(['b', 'a'], $indexes['idx_ba']);
});


test('expression index parts are reported as expressions (PostgreSQL)', function () use ($connection, $driver, $driverName) {
	if ($driverName !== 'pgsql') {
		return;
	}

	$connection->query('DROP INDEX IF EXISTS idx_expr');
	$connection->query('CREATE INDEX idx_expr ON idx_test ((a + b), b)');
	$indexes = array_column($driver->getIndexes('idx_test'), 'columns', 'name');
	Assert::same(['(a + b)', 'b'], $indexes['idx_expr']);
});
