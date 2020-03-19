<?php

/**
 * @dataProvider? ../databases.ini  mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection


$driver = $connection->getDriver();

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, 20);
Assert::same('SELECT 1 FROM t LIMIT 10 OFFSET 20', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 0, 20);
Assert::same('SELECT 1 FROM t LIMIT 0 OFFSET 20', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, 0);
Assert::same('SELECT 1 FROM t LIMIT 10', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, null, 20);
Assert::same('SELECT 1 FROM t LIMIT 18446744073709551615 OFFSET 20', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, null);
Assert::same('SELECT 1 FROM t LIMIT 10', $query);

Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, -1, null);
}, Nette\InvalidArgumentException::class, 'Negative offset or limit.');

Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, null, -1);
}, Nette\InvalidArgumentException::class, 'Negative offset or limit.');
