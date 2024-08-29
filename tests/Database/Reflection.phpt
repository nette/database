<?php

/**
 * Test: Nette\Database\Connection: reflection
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Database\Drivers\Engine;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


$reflection = $connection->getReflection();
$schemaSupported = $connection->getDatabaseEngine()->isSupported(Engine::SupportSchema);

// table names
$tableNames = array_keys($reflection->tables);
if ($schemaSupported) {
	Assert::same(
		['public.author', 'public.book', 'public.book_tag', 'public.tag'],
		array_intersect(['public.author', 'public.book', 'public.book_tag', 'public.tag'], $tableNames),
	);
	Assert::true($reflection->hasTable('public.author'));
	Assert::false($reflection->hasTable('unknown'));
} else {
	Assert::same(
		['author', 'book', 'book_tag', 'tag'],
		array_intersect(['author', 'book', 'book_tag', 'tag'], $tableNames),
	);
	Assert::true($reflection->hasTable('author'));
	Assert::false($reflection->hasTable('unknown'));
}


// tables
$tables = array_filter($reflection->tables, fn($t) => in_array($t->name, ['author', 'book', 'book_tag', 'tag'], true));
usort($tables, fn($a, $b) => $a->name <=> $b->name);
Assert::same('author', (string) $tables[0]);

if ($schemaSupported) {
	Assert::same(
		[
			['author', false, 'public.author'],
			['book', false, 'public.book'],
			['book_tag', false, 'public.book_tag'],
			['tag', false, 'public.tag'],
		],
		array_map(fn($t) => [$t->name, $t->view, $t->fullName], $tables),
	);
} else {
	Assert::same(
		[
			['author', false, null],
			['book', false, null],
			['book_tag', false, null],
			['tag', false, null],
		],
		array_map(fn($t) => [$t->name, $t->view, $t->fullName], $tables),
	);
}


// columns
$table = $reflection->getTable('author');

$expectedColumns = [
	'id' => [
		'name' => 'id',
		'table' => 'author',
		'nativeType' => 'INT',
		'size' => 11,
		'scale' => null,
		'nullable' => false,
		'default' => null,
		'autoIncrement' => true,
		'primary' => true,
	],
	'name' => [
		'name' => 'name',
		'table' => 'author',
		'nativeType' => 'VARCHAR',
		'size' => 30,
		'scale' => null,
		'nullable' => false,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'web' => [
		'name' => 'web',
		'table' => 'author',
		'nativeType' => 'VARCHAR',
		'size' => 100,
		'scale' => null,
		'nullable' => false,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	'born' => [
		'name' => 'born',
		'table' => 'author',
		'nativeType' => 'DATE',
		'size' => null,
		'scale' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
];

switch ($driverName) {
	case 'mysql':
		$version = $connection->getServerVersion();
		if (version_compare($version, '8.0', '>=')) {
			$expectedColumns['id']['size'] = null;
		}
		break;
	case 'pgsql':
		$expectedColumns['id']['nativeType'] = 'INT4';
		$expectedColumns['id']['default'] = "nextval('author_id_seq'::regclass)";
		$expectedColumns['id']['size'] = 4;
		$expectedColumns['born']['size'] = 4;
		break;
	case 'sqlite':
		$expectedColumns['id']['nativeType'] = 'INTEGER';
		$expectedColumns['id']['size'] = null;
		$expectedColumns['name']['nativeType'] = 'TEXT';
		$expectedColumns['name']['size'] = null;
		$expectedColumns['web']['nativeType'] = 'TEXT';
		$expectedColumns['web']['size'] = null;
		break;
	case 'sqlsrv':
		$expectedColumns['id']['size'] = 10;
		$expectedColumns['born']['size'] = 10;
		break;
	default:
		Assert::fail("Unsupported driver $driverName");
}

Assert::same('id', array_key_first($table->columns));
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
	], $table->columns),
);


// indexes
$table = $reflection->getTable('book_tag');
$index = $table->indexes[0];
switch ($driverName) {
	case 'pgsql':
		Assert::count(1, $table->indexes);
		Assert::same('book_tag_pkey', $index->name);
		break;
	case 'sqlite':
		Assert::count(1, $table->indexes);
		Assert::same('sqlite_autoindex_book_tag_1', $index->name);
		break;
	case 'sqlsrv':
		Assert::count(1, $table->indexes);
		Assert::same('PK_book_tag', $index->name);
		break;
	case 'mysql':
		Assert::count(2, $table->indexes);
		Assert::same('PRIMARY', $index->name);
		break;
	default:
		Assert::fail("Unsupported driver $driverName");
}

Assert::true($index->unique);
Assert::true($index->primary);
Assert::same([$table->getColumn('book_id'), $table->getColumn('tag_id')], $index->columns);


// primary keys
$table = $reflection->getTable('book_tag');
Assert::same([$table->getColumn('book_id'), $table->getColumn('tag_id')], $table->primaryKey->columns);


// foreign keys
$table = $reflection->getTable('book_tag');
Assert::count(2, $table->foreignKeys);

$keys = $table->foreignKeys;
usort($keys, fn($a, $b) => $a->name <=> $b->name);
$key = $keys[0];
switch ($driverName) {
	case 'sqlite':
		Assert::null($key->name);
		break;
	default:
		Assert::same('book_tag_book', $key->name);
}

Assert::same([$table->getColumn('book_id')], $key->localColumns);
Assert::same('book', $key->foreignTable->name);
Assert::same([$key->foreignTable->getColumn('id')], $key->foreignColumns);
