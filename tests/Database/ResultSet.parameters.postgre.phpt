<?php

/**
 * Test: Nette\Database\ResultSet parameters
 * @dataProvider? databases.ini  postgresql
 */

declare(strict_types=1);

use Tester\Assert;
use Nette\Utils\DateTime;

require __DIR__ . '/connect.inc.php'; // create $connection

$res = $connection->fetch('SELECT ?::bool AS c1, ? AS c2, ?::int AS c3', TRUE, NULL, 123);

Assert::same(
	['c1' => TRUE, 'c2' => NULL, 'c3' => 123],
	(array) $res
);
