<?php declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


// in T-SQL bracketed identifiers only ] is escaped by doubling, [ stays literal
foreach ([
	new Nette\Database\Drivers\Engines\MSSQLEngine,
	new Nette\Database\Drivers\Engines\ODBCEngine,
	new Nette\Database\Drivers\Engines\SQLServerEngine,
] as $engine) {
	Assert::same('[hello]', $engine->delimit('hello'));
	Assert::same('[a[b]', $engine->delimit('a[b'));
	Assert::same('[a]]b]', $engine->delimit('a]b'));
}

// SQLite has no escape for ] inside [...], such a name goes into double quotes
$sqlite = new Nette\Database\Drivers\Engines\SQLiteEngine;
Assert::same('[hello]', $sqlite->delimit('hello'));
Assert::same('[a[b]', $sqlite->delimit('a[b'));
Assert::same('[a"b]', $sqlite->delimit('a"b'));
Assert::same('"a]b"', $sqlite->delimit('a]b'));
Assert::same('"a]""b"', $sqlite->delimit('a]"b'));
