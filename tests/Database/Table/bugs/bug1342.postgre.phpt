<?php

/**
 * Test: bug 1342
 * @dataProvider? ../../databases.ini postgresql
 */

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php';

$context->query('DROP SCHEMA IF EXISTS public CASCADE');
$context->query('CREATE SCHEMA public');
$context->query('
	CREATE TABLE "public"."bug1342" (
		"a1" int2 NOT NULL,
		"a2" int2 NOT NULL,
		PRIMARY KEY ("a1", "a2")
	)
');


$row = $context->table('bug1342')->insert([
	'a1' => 1,
	'a2' => 2,
]);

Assert::same(1, $row->a1);
Assert::same(2, $row->a2);
