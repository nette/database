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
	$driver = $connection->getDriver();
	Assert::same('254358000', $driver->formatDateTime(new DateTime('1978-01-23 00:00:00')));
});

test('formatDateTime', function () {
	$connection = connectToDB(['formatDateTime' => 'Y-m-d'])->getConnection();
	$driver = $connection->getDriver();
	Assert::same('1978-01-23', $driver->formatDateTime(new DateTime('1978-01-23 00:00:00')));
});
