<?php

/**
 * Test: Nette\Database\Connection exceptions.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$e = Assert::exception(function () {
	$connection = new Nette\Database\Connection('unknown');
}, Nette\Database\ConnectionException::class, 'invalid data source name', 0);

Assert::same(null, $e->getDriverCode());
Assert::same(null, $e->getSqlState());
