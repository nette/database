<?php

/**
 * Test: Nette\Database\Connection exceptions.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$e = Assert::exception(
	fn() => new Nette\Database\Connection('unknown'),
	Nette\Database\ConnectionException::class,
	'%a%valid data source %a%',
	0,
);

Assert::same(null, $e->getDriverCode());
Assert::same(null, $e->getSqlState());
