<?php

/**
 * Test: bug 1342
 * @dataProvider? ../../databases.ini postgresql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$explorer = connectToDB();

$explorer->query('DROP SCHEMA IF EXISTS public CASCADE');
$explorer->query('CREATE SCHEMA public');
$explorer->query('
	CREATE TABLE "public"."bug1342" (
		"a1" int2 NOT NULL,
		"a2" int2 NOT NULL,
		PRIMARY KEY ("a1", "a2")
	)
');


$insertedRows = $explorer->table('bug1342')->insert([
	'a1' => 1,
	'a2' => 2,
]);

Assert::same($insertedRows->a1, 1);
Assert::same($insertedRows->a2, 2);

$insertedRows = $explorer->table('bug1342')->insert([
	'a1' => 24,
	'a2' => 48,
]);

Assert::same($insertedRows->a1, 24);
Assert::same($insertedRows->a2, 48);
