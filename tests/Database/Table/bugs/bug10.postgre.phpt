<?php

/**
 * Test: bug 10
 * @dataProvider? ../../databases.ini postgresql
 */


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

// Throw pdo sequence exception: relation "bug10_bug10caseproblem_seq" does not exist
\Tester\Assert::exception(function() use ($context) {
	$context->table('Bug10')->insert([
		'D1' => 123,
	]);
}, '\PDOException', NULL, '42P01');
