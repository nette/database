<?php

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


class SqlsrvDriverMock extends Nette\Database\Drivers\SqlsrvDriver
{
	function __construct()
	{}
}

$driver = new SqlsrvDriverMock;

Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, 10, 20);
}, 'Nette\NotSupportedException', 'Offset is not supported by this database.');

Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, 0, 20);
}, 'Nette\NotSupportedException', 'Offset is not supported by this database.');

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, 0);
Assert::same('SELECT TOP 10 1 FROM t', $query);

Assert::exception(function () use ($driver) {
	$query = 'SELECT 1 FROM t';
	$driver->applyLimit($query, NULL, 20);
}, 'Nette\NotSupportedException', 'Offset is not supported by this database.');

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, NULL);
Assert::same('SELECT TOP 10 1 FROM t', $query);

$query = ' select  distinct  1 FROM t';
$driver->applyLimit($query, 10, NULL);
Assert::same(' select  distinct TOP 10  1 FROM t', $query);

$query = 'UPDATE t SET';
$driver->applyLimit($query, 10, NULL);
Assert::same('UPDATE TOP 10 t SET', $query);

$query = 'DELETE FROM t SET';
$driver->applyLimit($query, 10, NULL);
Assert::same('DELETE TOP 10 FROM t SET', $query);

Assert::exception(function () use ($driver) {
	$query = 'SET FROM t';
	$driver->applyLimit($query, 10, NULL);
}, 'Nette\InvalidArgumentException', 'SQL query must begin with SELECT, UPDATE or DELETE command.');



$version = new ReflectionProperty('Nette\Database\Drivers\SqlsrvDriver', 'version');
$version->setAccessible(TRUE);
$version->setValue($driver, 12);

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
$driver->applyLimit($query, NULL, 20);
Assert::same('SELECT 1 FROM t OFFSET 20 ROWS FETCH NEXT 0 ROWS ONLY', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, NULL);
Assert::same('SELECT 1 FROM t OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $query);



Assert::exception(function () use ($driver) {
$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, -1, NULL);
}, 'Nette\InvalidArgumentException', 'Negative offset or limit.');

Assert::exception(function () use ($driver) {
$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, NULL, -1);
}, 'Nette\InvalidArgumentException', 'Negative offset or limit.');
