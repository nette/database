<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


$driver = new Nette\Database\Drivers\MsSqlDriver;

Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, 10, 20);
}, Nette\NotSupportedException::class, 'Offset is not supported by this database.');

Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, 0, 20);
}, Nette\NotSupportedException::class, 'Offset is not supported by this database.');

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, 0);
Assert::same('SELECT TOP 10 1 FROM t', $query);

Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, null, 20);
}, Nette\NotSupportedException::class, 'Offset is not supported by this database.');

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, null);
Assert::same('SELECT TOP 10 1 FROM t', $query);

$query = ' select  distinct  1 FROM t';
$driver->applyLimit($query, 10, null);
Assert::same(' select  distinct TOP 10  1 FROM t', $query);

$query = 'UPDATE t SET';
$driver->applyLimit($query, 10, null);
Assert::same('UPDATE TOP 10 t SET', $query);

$query = 'DELETE FROM t SET';
$driver->applyLimit($query, 10, null);
Assert::same('DELETE TOP 10 FROM t SET', $query);

Assert::exception(function () use ($driver) {
	$query = 'SET FROM t';
	$driver->applyLimit($query, 10, null);
}, Nette\InvalidArgumentException::class, 'SQL query must begin with SELECT, UPDATE or DELETE command.');

Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, -1, null);
}, Nette\InvalidArgumentException::class, 'Negative offset or limit.');

Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, null, -1);
}, Nette\NotSupportedException::class, 'Offset is not supported by this database.');
