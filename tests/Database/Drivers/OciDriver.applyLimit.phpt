<?php

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


$driver = new Nette\Database\Drivers\OciDriver(new Nette\Database\Connection('', '', '', array('lazy' => TRUE)), array());

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, 20);
Assert::same('SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (SELECT 1 FROM t) t WHERE ROWNUM <= 30) WHERE "__rnum" > 20', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 0, 20);
Assert::same('SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (SELECT 1 FROM t) t WHERE ROWNUM <= 20) WHERE "__rnum" > 20', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, 0);
Assert::same('SELECT * FROM (SELECT 1 FROM t) WHERE ROWNUM <= 10', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, NULL, 20);
Assert::same('SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (SELECT 1 FROM t) t ) WHERE "__rnum" > 20', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, 10, NULL);
Assert::same('SELECT * FROM (SELECT 1 FROM t) WHERE ROWNUM <= 10', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, -1, NULL);
Assert::same('SELECT * FROM (SELECT 1 FROM t) WHERE ROWNUM <= -1', $query);

$query = 'SELECT 1 FROM t';
$driver->applyLimit($query, NULL, -1);
Assert::same('SELECT 1 FROM t', $query);
