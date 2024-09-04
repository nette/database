<?php

/**
 * Test: SQL Server can execute queries.
 * @dataProvider? databases.ini  sqlsrv
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB();

Assert::noError(
	fn() => Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlsrv-loadFromFile.sql')
);
