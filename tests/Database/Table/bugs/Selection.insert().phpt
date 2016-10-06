<?php
/**
 * @dataProvider? ../../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test4.sql");

//Insert into table without auto_increament primary key
test(function () use ($context) {

	$inserted = $context->table('simple_pk')->insert([
		'id' => 8,
		'name' => 'Michal'
	]);

	Assert::equal(8, $inserted->id);
	Assert::equal('Michal', $inserted->name);
});

//Insert into table with composite primary key
test(function () use ($context) {

	$inserted = $context->table('composite_pk')->insert([
		'id1' => 8,
		'id2' => 10,
		'name' => 'Michal'
	]);

	Assert::equal(8, $inserted->id1);
	Assert::equal(10, $inserted->id2);
	Assert::equal('Michal', $inserted->name);
});

//Insert into table with composite primary key and one of them is auto_increment
test(function () use ($context, $driverName) {

	//Sqlite doesn't allow this type of table and sqlsrv's driver don't implement reflection
	if ($driverName == 'mysql' || $driverName == 'pgsql') {
		$inserted = $context->table('composite_pk_ai')->insert([
			'id2' => 10,
			'name' => 'Michal'
		]);

		Assert::equal(10, $inserted->id2);
		Assert::equal('Michal', $inserted->name);
	}
});
