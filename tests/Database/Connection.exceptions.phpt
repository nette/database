<?php

/**
 * Test: Nette\Database\Connection exceptions.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$e = Assert::exception(function() {
	$connection = new Nette\Database\Connection('unknown');
}, 'Nette\Database\ConnectionException', 'invalid data source name', 0);

Assert::same(NULL, $e->getDriverCode());
Assert::same(NULL, $e->getSqlState());
