<?php

/**
 * Test: PostgreSQL specific reflection
 * @dataProvider? databases.ini  postgresql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

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


function filter($columns)
{
	array_walk($columns, function (&$item) { $item = $item['name']; });
	sort($columns);
	return $columns;
}


// Reflection for tables with the same name but different schema
$connection->query('SET search_path TO one, two');
Assert::same(['master', 'slave'], filter($driver->getTables()));
Assert::same(['one_id'], filter($driver->getColumns('master')));
Assert::same(['one_master_pkey'], filter($driver->getIndexes('master')));
Assert::same(['one_slave_fk'], filter($driver->getForeignKeys('slave')));

$connection->query('SET search_path TO two, one');
Assert::same(['master', 'slave'], filter($driver->getTables()));
Assert::same(['two_id'], filter($driver->getColumns('master')));
Assert::same(['two_master_pkey'], filter($driver->getIndexes('master')));
Assert::same(['two_slave_fk'], filter($driver->getForeignKeys('slave')));


// Reflection for FQN
Assert::same(['one_id'], filter($driver->getColumns('one.master')));
Assert::same(['one_master_pkey'], filter($driver->getIndexes('one.master')));
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
Assert::same(['one_slave_fk'], filter($driver->getForeignKeys('slave')));
$connection->query('SET search_path TO one, two');
Assert::same(['one_slave_fk', 'one_two_fk'], filter($driver->getForeignKeys('slave')));
