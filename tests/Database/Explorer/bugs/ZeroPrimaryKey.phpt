<?php

/**
 * Test: Zero Primary key bug
 * @dataProvider? ../../databases.ini mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php';


$explorer->query('
	CREATE TABLE ships (
		id INTEGER PRIMARY KEY NOT NULL,
		name TEXT NOT NULL
	);
');

$explorer->query('
	INSERT INTO ships (id, name) VALUES(2, "Enterprise");
');

$explorer->query('
	INSERT INTO ships (id, name) VALUES(0, "Endeavour");
');

Assert::same(2, $explorer->table('ships')->order('id DESC')->count());

$result = $explorer->table('ships')->order('id DESC')->fetchAll(); // SELECT * FROM `ships` ORDER BY id DESC

Assert::same('Enterprise', $result[2]->name);

Assert::same('Endeavour', $result[0]->name);
