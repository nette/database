<?php

/**
 * Test: Nette\Database\ResultSet parameters
 * @dataProvider? databases.ini  postgresql
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

$res = $connection->fetch('SELECT ? AS c1, ? AS c2, ? AS c3', TRUE, NULL, 123);

Assert::same(
	['c1' => TRUE, 'c2' => NULL, 'c3' => 123],
	(array) $res
);
