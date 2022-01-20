<?php

/**
 * Test: Nette\Database\Connection: reflection
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Database\Driver;
use Nette\Database\Reflection\Column;
use Nette\Database\Reflection\Index;
use Nette\Database\Reflection\Table;
use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


$driver = $connection->getDriver();
$tables = $driver->getTables();
$tables = array_filter($tables, fn($t) => in_array($t->name, ['author', 'book', 'book_tag', 'tag'], true));
usort($tables, fn($a, $b) => strcmp($a->name, $b->name));

if ($driver->isSupported(Driver::SupportSchema)) {
	Assert::equal(
		[
			new Table(name: 'author', view: false, fullName: 'public.author'),
			new Table(name: 'book', view: false, fullName: 'public.book'),
			new Table(name: 'book_tag', view: false, fullName: 'public.book_tag'),
			new Table(name: 'tag', view: false, fullName: 'public.tag'),
		],
		$tables,
	);
} else {
	Assert::equal([
		new Table(name: 'author', view: false),
		new Table(name: 'book', view: false),
		new Table(name: 'book_tag', view: false),
		new Table(name: 'tag', view: false),
	], $tables);
}


$columns = $driver->getColumns('author');
array_walk($columns, function (&$item) {
	$item->vendor = [];
});

$expectedColumns = [
	[
		'name' => 'id',
		'table' => 'author',
		'nativeType' => 'int',
		'size' => 11,
		'nullable' => false,
		'default' => null,
		'autoIncrement' => true,
		'primary' => true,
	],
	[
		'name' => 'name',
		'table' => 'author',
		'nativeType' => 'varchar',
		'size' => 30,
		'nullable' => false,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	[
		'name' => 'web',
		'table' => 'author',
		'nativeType' => 'varchar',
		'size' => 100,
		'nullable' => false,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
	[
		'name' => 'born',
		'table' => 'author',
		'nativeType' => 'date',
		'size' => null,
		'nullable' => true,
		'default' => null,
		'autoIncrement' => false,
		'primary' => false,
	],
];

switch ($driverName) {
	case 'mysql':
		$version = $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
		if (version_compare($version, '8.0', '>=')) {
			$expectedColumns[0]['size'] = null;
		}
		break;
	case 'pgsql':
		$expectedColumns[0]['nativeType'] = 'int4';
		$expectedColumns[0]['default'] = "nextval('author_id_seq'::regclass)";
		$expectedColumns[0]['size'] = null;
		break;
	case 'sqlite':
		$expectedColumns[0]['nativeType'] = 'INTEGER';
		$expectedColumns[0]['size'] = null;
		$expectedColumns[1]['nativeType'] = 'TEXT';
		$expectedColumns[1]['size'] = null;
		$expectedColumns[2]['nativeType'] = 'TEXT';
		$expectedColumns[2]['size'] = null;
		$expectedColumns[3]['nativeType'] = 'DATE';
		break;
	case 'sqlsrv':
		$expectedColumns[0]['size'] = null;
		$expectedColumns[1]['size'] = null;
		$expectedColumns[2]['size'] = null;
		break;
	default:
		Assert::fail("Unsupported driver $driverName");
}

$expectedColumns = array_map(fn($data) => new Column(...$data), $expectedColumns);
Assert::equal($expectedColumns, $columns);


$indexes = $driver->getIndexes('book_tag');
switch ($driverName) {
	case 'pgsql':
		Assert::equal([
			new Index(
				name: 'book_tag_pkey',
				unique: true,
				primary: true,
				columns: [
					'book_id',
					'tag_id',
				],
			),
		], $indexes);
		break;
	case 'sqlite':
		Assert::equal([
			new Index(
				name: 'sqlite_autoindex_book_tag_1',
				unique: true,
				primary: true,
				columns: [
					'book_id',
					'tag_id',
				],
			),
		], $indexes);
		break;
	case 'sqlsrv':
		Assert::equal([
			new Index(
				name: 'PK_book_tag',
				unique: true,
				primary: true,
				columns: [
					'book_id',
					'tag_id',
				],
			),
		], $indexes);
		break;
	case 'mysql':
		Assert::equal([
			new Index(
				name: 'PRIMARY',
				unique: true,
				primary: true,
				columns: [
					'book_id',
					'tag_id',
				],
			),
			new Index(
				name: 'book_tag_tag',
				unique: false,
				primary: false,
				columns: [
					'tag_id',
				],
			),
		], $indexes);
		break;
	default:
		Assert::fail("Unsupported driver $driverName");
}

$structure->rebuild();
$primary = $structure->getPrimaryKey('book_tag');
Assert::same(['book_id', 'tag_id'], $primary);
