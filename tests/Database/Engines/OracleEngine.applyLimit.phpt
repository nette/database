<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$connection = Mockery::mock(Nette\Database\Drivers\Connection::class);
$engine = new Nette\Database\Drivers\Engines\OracleEngine($connection);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', 10, 20),
	'SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (SELECT 1 FROM t) t WHERE ROWNUM <= 30) WHERE "__rnum" > 20',
);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', 0, 20),
	'SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (SELECT 1 FROM t) t WHERE ROWNUM <= 20) WHERE "__rnum" > 20',
);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', 10, 0),
	'SELECT * FROM (SELECT 1 FROM t) WHERE ROWNUM <= 10',
);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', null, 20),
	'SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (SELECT 1 FROM t) t ) WHERE "__rnum" > 20',
);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', 10, null),
	'SELECT * FROM (SELECT 1 FROM t) WHERE ROWNUM <= 10',
);

Assert::exception(
	fn() => $engine->applyLimit('SELECT 1 FROM t', -1, null),
	Nette\InvalidArgumentException::class,
	'Negative offset or limit.',
);

Assert::exception(
	fn() => $engine->applyLimit('SELECT 1 FROM t', null, -1),
	Nette\InvalidArgumentException::class,
	'Negative offset or limit.',
);
