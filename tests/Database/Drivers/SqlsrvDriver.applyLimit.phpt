<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


$driver = new Nette\Database\Drivers\SqlsrvDriver;

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, 20);
Assert::same('SELECT 1 FROM t OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 0, 20);
Assert::same('SELECT 1 FROM t OFFSET 20 ROWS FETCH NEXT 0 ROWS ONLY', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, 0);
Assert::same('SELECT 1 FROM t OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, null, 20);
Assert::same('SELECT 1 FROM t OFFSET 20 ROWS FETCH NEXT 0 ROWS ONLY', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, null);
Assert::same('SELECT 1 FROM t OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $query);



Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, -1, null);
}, Nette\InvalidArgumentException::class, 'Negative offset or limit.');

Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, null, -1);
}, Nette\InvalidArgumentException::class, 'Negative offset or limit.');
