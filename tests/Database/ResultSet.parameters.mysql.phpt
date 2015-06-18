<?php

/**
 * Test: Nette\Database\ResultSet parameters
 * @dataProvider? databases.ini  mysql
 */

use Tester\Assert;
use Nette\Utils\DateTime;

require __DIR__ . '/connect.inc.php'; // create $connection

$res = $connection->fetch('SELECT ? AS c1, ? AS c2, ? AS c3, ? as c4', fopen(__FILE__, 'r'), TRUE, NULL, 123);

Assert::same(
	array('c1' => file_get_contents(__FILE__), 'c2' => 1, 'c3' => NULL, 'c4' => 123),
	(array) $res
);
