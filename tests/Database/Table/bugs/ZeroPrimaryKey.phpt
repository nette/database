<?php

/**
 * Test: Zero Primary key bug
 * @dataProvider? ../../databases.ini mysql
 */

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php';

$context->query('CREATE DATABASE IF NOT EXISTS nette_test');
$context->query('USE nette_test');

$context->query('
	CREATE TABLE ships (
		id INTEGER PRIMARY KEY NOT NULL,
		name TEXT NOT NULL
	);
');

$context->query('
	INSERT INTO ships (id, name) VALUES(2, "Enterprise");
');

$context->query('
	INSERT INTO ships (id, name) VALUES(0, "Endeavour");
');

Assert::same(2, $context->table('ships')->order('id DESC')->count());

$result = $context->table('ships')->order('id DESC')->fetchAll(); // SELECT * FROM `ships` ORDER BY id DESC

Assert::same("Enterprise", $result[2]->name);

Assert::same("Endeavour", $result[0]->name);
