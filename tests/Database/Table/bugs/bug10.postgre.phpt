<?php

/**
 * Test: bug 10
 * @dataProvider? ../../databases.ini postgresql
 */

declare(strict_types=1);


require __DIR__ . '/../../connect.inc.php';

$context->query('DROP SCHEMA IF EXISTS public CASCADE');
$context->query('CREATE SCHEMA public');
$context->query('
	CREATE TABLE "public"."Bug10" (
		"Bug10CaseProblem" serial,
		"D1" integer,
		PRIMARY KEY ("Bug10CaseProblem")
	)
');

$result = $context->table('Bug10')->insert([
	'D1' => 123,
]);

Tester\Assert::notEqual(null, $result);
