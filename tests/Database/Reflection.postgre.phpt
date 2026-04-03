<?php declare(strict_types=1);

/**
 * Test: PostgreSQL specific reflection
 * @dataProvider? databases.ini  postgresql
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();


function names($columns): array
{
	$names = array_column($columns, 'name');
	sort($names);
	return $names;
}


test('Tables in schema', function () use ($connection) {
	Nette\Database\Helpers::loadFromFile($connection, Tester\FileMock::create('
		DROP SCHEMA IF EXISTS "one" CASCADE;
		DROP SCHEMA IF EXISTS "two" CASCADE;

		CREATE SCHEMA "one";
		CREATE SCHEMA "two";

		CREATE TABLE "one"."master" ("one_id" integer NOT NULL, PRIMARY KEY ("one_id"));
		CREATE TABLE "two"."master" ("two_id" integer NOT NULL, PRIMARY KEY ("two_id"));

		ALTER INDEX "one"."master_pkey" RENAME TO "one_master_pkey";
		ALTER INDEX "two"."master_pkey" RENAME TO "two_master_pkey";

		CREATE TABLE "one"."slave" ("one_id" integer NULL);
		CREATE TABLE "two"."slave" ("two_id" integer NULL);

		ALTER TABLE "one"."slave" ADD CONSTRAINT "one_slave_fk" FOREIGN KEY ("one_id") REFERENCES "one"."master"("one_id");
		ALTER TABLE "two"."slave" ADD CONSTRAINT "two_slave_fk" FOREIGN KEY ("two_id") REFERENCES "two"."master"("two_id");
	'));

	$driver = $connection->getDriver();

	// Reflection for tables with the same name but different schema
	$connection->query('SET search_path TO one, two');
	Assert::same(['master', 'slave'], names($driver->getTables()));
	Assert::same(['one_id'], names($driver->getColumns('master')));
	Assert::same(['one_master_pkey'], names($driver->getIndexes('master')));
	Assert::same(['one_slave_fk'], names($driver->getForeignKeys('slave')));

	$connection->query('SET search_path TO two, one');
	Assert::same(['master', 'slave'], names($driver->getTables()));
	Assert::same(['two_id'], names($driver->getColumns('master')));
	Assert::same(['two_master_pkey'], names($driver->getIndexes('master')));
	Assert::same(['two_slave_fk'], names($driver->getForeignKeys('slave')));


	// Reflection for FQN
	Assert::same(['one_id'], names($driver->getColumns('one.master')));
	Assert::same(['one_master_pkey'], names($driver->getIndexes('one.master')));
	$foreign = $driver->getForeignKeys('one.slave');
	Assert::same([
		'name' => 'one_slave_fk',
		'local' => 'one_id',
		'table' => 'one.master',
		'foreign' => 'one_id',
	], (array) $foreign[0]);


	// Limit foreign keys for current schemas only
	$connection->query('ALTER TABLE "one"."slave" ADD CONSTRAINT "one_two_fk" FOREIGN KEY ("one_id") REFERENCES "two"."master"("two_id")');
	$connection->query('SET search_path TO one');
	Assert::same(['one_slave_fk'], names($driver->getForeignKeys('slave')));
	$connection->query('SET search_path TO one, two');
	Assert::same(['one_slave_fk', 'one_two_fk'], names($driver->getForeignKeys('slave')));
});


test('Table with GENERATED ALWAYS AS stored columns', function () use ($connection) {
	$ver = $connection->query('SHOW server_version')->fetchField();
	if (version_compare($ver, '12') < 0) {
		Tester\Environment::skip("GENERATED ALWAYS AS requires PostgreSQL 12+, running $ver.");
	}

	Nette\Database\Helpers::loadFromFile($connection, Tester\FileMock::create('
		DROP TABLE IF EXISTS "generated_test";

		CREATE TABLE "generated_test" (
			"id" serial PRIMARY KEY,
			"first_name" varchar(50) NOT NULL,
			"last_name" varchar(50) NOT NULL,
			"full_name" text GENERATED ALWAYS AS ("first_name" || \' \' || "last_name") STORED
		);
	'));

	$driver = $connection->getDriver();
	$columns = $driver->getColumns('generated_test');
	$columnNames = array_column($columns, 'name');

	Assert::same(['id', 'first_name', 'last_name', 'full_name'], $columnNames);

	$fullNameCol = $columns[3];
	Assert::same('full_name', $fullNameCol['name']);
	Assert::same('TEXT', $fullNameCol['nativetype']);
});
