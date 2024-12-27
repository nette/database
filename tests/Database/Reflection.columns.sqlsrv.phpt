<?php

/**
 * Test: Nette\Database\Connection: reflection
 * @dataProvider? databases.ini  sqlsrv
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/sqlsrv-nette_test3.sql');


$reflection = $connection->getReflection();
$columns = $reflection->getTable('types')->columns;

$expectedColumns = [
	'bigint' => [
		'name' => 'bigint',
		'table' => 'types',
		'nativeType' => 'BIGINT',
		'size' => 19,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'binary_3' => [
		'name' => 'binary_3',
		'table' => 'types',
		'nativeType' => 'BINARY',
		'size' => 3,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'bit' => [
		'name' => 'bit',
		'table' => 'types',
		'nativeType' => 'BIT',
		'size' => 1,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'char_5' => [
		'name' => 'char_5',
		'table' => 'types',
		'nativeType' => 'CHAR',
		'size' => 5,
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
		'size' => 10,
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
		'size' => 23,
		'scale' => 3,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'datetime2' => [
		'name' => 'datetime2',
		'table' => 'types',
		'nativeType' => 'DATETIME2',
		'size' => 27,
		'scale' => 7,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'decimal' => [
		'name' => 'decimal',
		'table' => 'types',
		'nativeType' => 'DECIMAL',
		'size' => 18,
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
		'size' => 53,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'geography' => [
		'name' => 'geography',
		'table' => 'types',
		'nativeType' => 'GEOGRAPHY',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'geometry' => [
		'name' => 'geometry',
		'table' => 'types',
		'nativeType' => 'GEOMETRY',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'hierarchyid' => [
		'name' => 'hierarchyid',
		'table' => 'types',
		'nativeType' => 'HIERARCHYID',
		'size' => 892,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'int' => [
		'name' => 'int',
		'table' => 'types',
		'nativeType' => 'INT',
		'size' => 10,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'money' => [
		'name' => 'money',
		'table' => 'types',
		'nativeType' => 'MONEY',
		'size' => 19,
		'scale' => 4,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'nchar' => [
		'name' => 'nchar',
		'table' => 'types',
		'nativeType' => 'NCHAR',
		'size' => 2,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'ntext' => [
		'name' => 'ntext',
		'table' => 'types',
		'nativeType' => 'NTEXT',
		'size' => 16,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'numeric_10_0' => [
		'name' => 'numeric_10_0',
		'table' => 'types',
		'nativeType' => 'NUMERIC',
		'size' => 10,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'numeric_10_2' => [
		'name' => 'numeric_10_2',
		'table' => 'types',
		'nativeType' => 'NUMERIC',
		'size' => 10,
		'scale' => 2,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'nvarchar' => [
		'name' => 'nvarchar',
		'table' => 'types',
		'nativeType' => 'NVARCHAR',
		'size' => 2,
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
		'size' => 24,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'smalldatetime' => [
		'name' => 'smalldatetime',
		'table' => 'types',
		'nativeType' => 'SMALLDATETIME',
		'size' => 16,
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
		'size' => 5,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'smallmoney' => [
		'name' => 'smallmoney',
		'table' => 'types',
		'nativeType' => 'SMALLMONEY',
		'size' => 10,
		'scale' => 4,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'text' => [
		'name' => 'text',
		'table' => 'types',
		'nativeType' => 'TEXT',
		'size' => 16,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'time' => [
		'name' => 'time',
		'table' => 'types',
		'nativeType' => 'TIME',
		'size' => 16,
		'scale' => 7,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'tinyint' => [
		'name' => 'tinyint',
		'table' => 'types',
		'nativeType' => 'TINYINT',
		'size' => 3,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'uniqueidentifier' => [
		'name' => 'uniqueidentifier',
		'table' => 'types',
		'nativeType' => 'UNIQUEIDENTIFIER',
		'size' => 16,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'varbinary' => [
		'name' => 'varbinary',
		'table' => 'types',
		'nativeType' => 'VARBINARY',
		'size' => 1,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'varchar' => [
		'name' => 'varchar',
		'table' => 'types',
		'nativeType' => 'VARCHAR',
		'size' => 1,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'xml' => [
		'name' => 'xml',
		'table' => 'types',
		'nativeType' => 'XML',
		'size' => null,
		'scale' => null,
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
