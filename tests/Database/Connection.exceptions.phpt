<?php

/**
 * Test: Nette\Database\Connection exceptions.
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$e = Assert::exception(function () {
	$connection = new Nette\Database\Connection('unknown');
}, Nette\Database\ConnectionException::class, 'invalid data source name', 0);

Assert::same(NULL, $e->getDriverCode());
Assert::same(NULL, $e->getSqlState());
