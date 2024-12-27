<?php

/**
 * Test: Nette\Database\Connection options.
 * @dataProvider? databases.ini  sqlite
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('formatDateTime', function () {
	$connection = connectToDB(['formatDateTime' => 'U'])->getConnection();
	$engine = $connection->getDatabaseEngine();
	Assert::same('254358000', $engine->formatDateTime(new DateTime('1978-01-23 00:00:00')));
});

test('formatDateTime', function () {
	$connection = connectToDB(['formatDateTime' => 'Y-m-d'])->getConnection();
	$engine = $connection->getDatabaseEngine();
	Assert::same('1978-01-23', $engine->formatDateTime(new DateTime('1978-01-23 00:00:00')));
});
