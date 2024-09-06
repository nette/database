<?php

/**
 * Test: Nette\Database\Connection: reflection
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Database\Drivers\Engine;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer, __DIR__ . "/files/{$driverName}-nette_test1.sql");


$engine = $explorer->getDatabaseEngine();
$tables = $engine->getTables();
$tables = array_filter($tables, fn($t) => in_array($t['name'], ['author', 'book', 'book_tag', 'tag'], true));
usort($tables, fn($a, $b) => strcmp($a['name'], $b['name']));

if ($engine->isSupported(Engine::SupportSchema)) {
	Assert::same(
		[
			['name' => 'author', 'view' => false, 'fullName' => 'public.author'],
			['name' => 'book', 'view' => false, 'fullName' => 'public.book'],
			['name' => 'book_tag', 'view' => false, 'fullName' => 'public.book_tag'],
			['name' => 'tag', 'view' => false, 'fullName' => 'public.tag'],
		],
		$tables,
	);
} else {
	Assert::same([
		['name' => 'author', 'view' => false],
		['name' => 'book', 'view' => false],
		['name' => 'book_tag', 'view' => false],
		['name' => 'tag', 'view' => false],
	], $tables);
}


$columns = $engine->getColumns('author');
array_walk($columns, function (&$item) {
	Assert::type('array', $item['vendor']);
	unset($item['vendor']);
});

$expectedColumns = [
	[
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
	[
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
	[
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
	[
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
		$version = $explorer->getServerVersion();
		if (version_compare($version, '8.0', '>=')) {
			$expectedColumns[0]['size'] = null;
		}
		break;
	case 'pgsql':
		$expectedColumns[0]['nativeType'] = 'INT4';
		$expectedColumns[0]['default'] = "nextval('author_id_seq'::regclass)";
		$expectedColumns[0]['size'] = 4;
		$expectedColumns[3]['size'] = 4;
		break;
	case 'sqlite':
		$expectedColumns[0]['nativeType'] = 'INTEGER';
		$expectedColumns[0]['size'] = null;
		$expectedColumns[1]['nativeType'] = 'TEXT';
		$expectedColumns[1]['size'] = null;
		$expectedColumns[2]['nativeType'] = 'TEXT';
		$expectedColumns[2]['size'] = null;
		break;
	case 'sqlsrv':
		$expectedColumns[0]['size'] = 10;
		$expectedColumns[3]['size'] = 10;
		break;
	default:
		Assert::fail("Unsupported driver $driverName");
}

Assert::same($expectedColumns, $columns);


$indexes = $engine->getIndexes('book_tag');
switch ($driverName) {
	case 'pgsql':
		Assert::same([
			[
				'name' => 'book_tag_pkey',
				'unique' => true,
				'primary' => true,
				'columns' => [
					'book_id',
					'tag_id',
				],
			],
		], $indexes);
		break;
	case 'sqlite':
		Assert::same([
			[
				'name' => 'sqlite_autoindex_book_tag_1',
				'unique' => true,
				'primary' => true,
				'columns' => [
					'book_id',
					'tag_id',
				],
			],
		], $indexes);
		break;
	case 'sqlsrv':
		Assert::same([
			[
				'name' => 'PK_book_tag',
				'unique' => true,
				'primary' => true,
				'columns' => [
					'book_id',
					'tag_id',
				],
			],
		], $indexes);
		break;
	case 'mysql':
		Assert::same([
			[
				'name' => 'PRIMARY',
				'unique' => true,
				'primary' => true,
				'columns' => [
					'book_id',
					'tag_id',
				],
			],
			[
				'name' => 'book_tag_tag',
				'unique' => false,
				'primary' => false,
				'columns' => [
					'tag_id',
				],
			],
		], $indexes);
		break;
	default:
		Assert::fail("Unsupported driver $driverName");
}

$structure = $explorer->getStructure();
$structure->rebuild();
$primary = $structure->getPrimaryKey('book_tag');
Assert::same(['book_id', 'tag_id'], $primary);
