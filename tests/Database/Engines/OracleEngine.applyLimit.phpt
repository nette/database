<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$connection = Mockery::mock(Nette\Database\Connection::class);
$engine = new Nette\Database\Drivers\Engines\OracleEngine($connection);

$query = 'SELECT 1 FROM t';
$engine->applyLimit($query, 10, 20);
Assert::same('SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (SELECT 1 FROM t) t WHERE ROWNUM <= 30) WHERE "__rnum" > 20', $query);

$query = 'SELECT 1 FROM t';
$engine->applyLimit($query, 0, 20);
Assert::same('SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (SELECT 1 FROM t) t WHERE ROWNUM <= 20) WHERE "__rnum" > 20', $query);

$query = 'SELECT 1 FROM t';
$engine->applyLimit($query, 10, 0);
Assert::same('SELECT * FROM (SELECT 1 FROM t) WHERE ROWNUM <= 10', $query);

$query = 'SELECT 1 FROM t';
$engine->applyLimit($query, null, 20);
Assert::same('SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (SELECT 1 FROM t) t ) WHERE "__rnum" > 20', $query);

$query = 'SELECT 1 FROM t';
$engine->applyLimit($query, 10, null);
Assert::same('SELECT * FROM (SELECT 1 FROM t) WHERE ROWNUM <= 10', $query);

Assert::exception(function () use ($engine) {
	$query = 'SELECT 1 FROM t';
	$engine->applyLimit($query, -1, null);
}, Nette\InvalidArgumentException::class, 'Negative offset or limit.');

Assert::exception(function () use ($engine) {
	$query = 'SELECT 1 FROM t';
	$engine->applyLimit($query, null, -1);
}, Nette\InvalidArgumentException::class, 'Negative offset or limit.');
