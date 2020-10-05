<?php

/**
 * Test: Nette\Database\Table: Referencing table PK datatype.
 * @dataProvider? ../../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test5.sql");


test(function () use ($context) { // referencing table with integer primary key
	$computers = $context->table('room')->get(1000)->related('computer');
	Assert::count(1, $computers);
	foreach ($computers as $computer) {
		Assert::same(1, $computer->id);
	}
});


test(function () use ($context) { // referencing table with string primary key
	$computers = $context->table('person')->get('mh')->related('computer');
	Assert::count(1, $computers);
	foreach ($computers as $computer) {
		Assert::same(1, $computer->id);
	}
});
