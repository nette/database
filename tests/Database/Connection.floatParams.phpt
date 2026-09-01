<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Connection float parameters
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
$query = $driverName === 'pgsql'
	? 'SELECT ?::double precision' // the placeholder needs a type the engine can infer
	: 'SELECT ?';

if ($driverName === 'pgsql') {
	// PostgreSQL older than 12 rounds float output to 15 digits, which would hide the value we stored
	$connection->query('SET extra_float_digits = 3');
}

test('A bound float keeps its value', function () use ($connection, $query) {
	foreach ([0.0, 1.0, 0.1, -2.5, 1e-11, 1 / 3, 0.30000000000000004, PHP_FLOAT_EPSILON] as $value) {
		Assert::same($value, (float) $connection->fetchField($query, $value));
	}
});
