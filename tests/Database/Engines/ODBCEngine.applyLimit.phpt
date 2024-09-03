<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$connection = Mockery::mock(Nette\Database\Drivers\Connection::class);
$engine = new Nette\Database\Drivers\Engines\ODBCEngine($connection);

Assert::exception(
	fn() => $engine->applyLimit('SELECT 1 FROM t', 10, 20),
	Nette\NotSupportedException::class,
	'Offset is not supported by this database.',
);

Assert::exception(
	fn() => $engine->applyLimit('SELECT 1 FROM t', 0, 20),
	Nette\NotSupportedException::class,
	'Offset is not supported by this database.',
);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', 10, 0),
	'SELECT TOP 10 1 FROM t',
);

Assert::exception(
	fn() => $engine->applyLimit('SELECT 1 FROM t', null, 20),
	Nette\NotSupportedException::class,
	'Offset is not supported by this database.',
);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', 10, null),
	'SELECT TOP 10 1 FROM t',
);

Assert::same(
	$engine->applyLimit(' select  distinct  1 FROM t', 10, null),
	' select  distinct TOP 10  1 FROM t',
);

Assert::same(
	$engine->applyLimit('UPDATE t SET', 10, null),
	'UPDATE TOP 10 t SET',
);

Assert::same(
	$engine->applyLimit('DELETE FROM t SET', 10, null),
	'DELETE TOP 10 FROM t SET',
);

Assert::exception(
	fn() => $engine->applyLimit('SET FROM t', 10, null),
	Nette\InvalidArgumentException::class,
	'SQL query must begin with SELECT, UPDATE or DELETE command.',
);

Assert::exception(
	fn() => $engine->applyLimit('SELECT 1 FROM t', -1, null),
	Nette\InvalidArgumentException::class,
	'Negative offset or limit.',
);

Assert::exception(
	fn() => $engine->applyLimit('SELECT 1 FROM t', null, -1),
	Nette\NotSupportedException::class,
	'Offset is not supported by this database.',
);
