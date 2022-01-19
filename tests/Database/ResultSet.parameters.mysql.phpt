<?php

/**
 * Test: Nette\Database\ResultSet parameters
 * @dataProvider? databases.ini  mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

$res = $connection->fetch('SELECT ? AS c1, ? AS c2, ? AS c3, ? as c4', fopen(__FILE__, 'r'), true, null, 123);

Assert::same(
	['c1' => file_get_contents(__FILE__), 'c2' => 1, 'c3' => null, 'c4' => 123],
	(array) $res,
);
