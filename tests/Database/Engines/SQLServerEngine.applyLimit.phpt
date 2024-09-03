<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$connection = Mockery::mock(Nette\Database\Drivers\Connection::class);
$engine = new Nette\Database\Drivers\Engines\SQLServerEngine($connection);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', 10, 20),
	'SELECT 1 FROM t OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY',
);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', 0, 20),
	'SELECT 1 FROM t OFFSET 20 ROWS FETCH NEXT 0 ROWS ONLY',
);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', 10, 0),
	'SELECT 1 FROM t OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', null, 20),
	'SELECT 1 FROM t OFFSET 20 ROWS FETCH NEXT 0 ROWS ONLY',
);

Assert::same(
	$engine->applyLimit('SELECT 1 FROM t', 10, null),
	'SELECT 1 FROM t OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
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
