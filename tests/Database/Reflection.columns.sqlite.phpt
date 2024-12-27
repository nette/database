<?php

/**
 * Test: Nette\Database\Connection: reflection
 * @dataProvider? databases.ini  sqlite
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlite-nette_test3.sql');


$reflection = $connection->getReflection();
$columns = $reflection->getTable('types')->columns;

$expectedColumns = [
	'int' => [
		'name' => 'int',
		'table' => 'types',
		'nativeType' => 'INT',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'integer' => [
		'name' => 'integer',
		'table' => 'types',
		'nativeType' => 'INTEGER',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'tinyint' => [
		'name' => 'tinyint',
		'table' => 'types',
		'nativeType' => 'TINYINT',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'smallint' => [
		'name' => 'smallint',
		'table' => 'types',
		'nativeType' => 'SMALLINT',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'mediumint' => [
		'name' => 'mediumint',
		'table' => 'types',
		'nativeType' => 'MEDIUMINT',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'bigint' => [
		'name' => 'bigint',
		'table' => 'types',
		'nativeType' => 'BIGINT',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'unsigned_big_int' => [
		'name' => 'unsigned_big_int',
		'table' => 'types',
		'nativeType' => 'UNSIGNED BIG INT',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'int2' => [
		'name' => 'int2',
		'table' => 'types',
		'nativeType' => 'INT2',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'int8' => [
		'name' => 'int8',
		'table' => 'types',
		'nativeType' => 'INT8',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'character_20' => [
		'name' => 'character_20',
		'table' => 'types',
		'nativeType' => 'CHARACTER',
		'size' => 20,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'varchar_255' => [
		'name' => 'varchar_255',
		'table' => 'types',
		'nativeType' => 'VARCHAR',
		'size' => 255,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'varying_character_255' => [
		'name' => 'varying_character_255',
		'table' => 'types',
		'nativeType' => 'VARYING CHARACTER',
		'size' => 255,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'nchar_55' => [
		'name' => 'nchar_55',
		'table' => 'types',
		'nativeType' => 'NCHAR',
		'size' => 55,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'native_character_70' => [
		'name' => 'native_character_70',
		'table' => 'types',
		'nativeType' => 'NATIVE CHARACTER',
		'size' => 70,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'nvarchar_100' => [
		'name' => 'nvarchar_100',
		'table' => 'types',
		'nativeType' => 'NVARCHAR',
		'size' => 100,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'text' => [
		'name' => 'text',
		'table' => 'types',
		'nativeType' => 'TEXT',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'clob' => [
		'name' => 'clob',
		'table' => 'types',
		'nativeType' => 'CLOB',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'blob' => [
		'name' => 'blob',
		'table' => 'types',
		'nativeType' => 'BLOB',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'real' => [
		'name' => 'real',
		'table' => 'types',
		'nativeType' => 'REAL',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'double' => [
		'name' => 'double',
		'table' => 'types',
		'nativeType' => 'DOUBLE',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'double precision' => [
		'name' => 'double precision',
		'table' => 'types',
		'nativeType' => 'DOUBLE PRECISION',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'float' => [
		'name' => 'float',
		'table' => 'types',
		'nativeType' => 'FLOAT',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'numeric' => [
		'name' => 'numeric',
		'table' => 'types',
		'nativeType' => 'NUMERIC',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'decimal_10_5' => [
		'name' => 'decimal_10_5',
		'table' => 'types',
		'nativeType' => 'DECIMAL',
		'size' => 10,
		'scale' => 5,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'boolean' => [
		'name' => 'boolean',
		'table' => 'types',
		'nativeType' => 'BOOLEAN',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'date' => [
		'name' => 'date',
		'table' => 'types',
		'nativeType' => 'DATE',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'datetime' => [
		'name' => 'datetime',
		'table' => 'types',
		'nativeType' => 'DATETIME',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'omitted' => [
		'name' => 'omitted',
		'table' => 'types',
		'nativeType' => 'BLOB',
		'size' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
];

Assert::same(
	$expectedColumns,
	array_map(fn($c) => [
		'name' => $c->name,
		'table' => $c->table->name,
		'nativeType' => $c->nativeType,
		'size' => $c->size,
		'scale' => $c->scale,
		'nullable' => $c->nullable,
		'default' => $c->default,
		'autoIncrement' => $c->autoIncrement,
		'primary' => $c->primary,
	], $columns),
);
