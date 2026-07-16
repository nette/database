<?php declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


// in T-SQL bracketed identifiers only ] is escaped by doubling, [ stays literal
foreach ([
	new Nette\Database\Drivers\MsSqlDriver,
	new Nette\Database\Drivers\OdbcDriver,
	new Nette\Database\Drivers\SqlsrvDriver,
] as $driver) {
	Assert::same('[hello]', $driver->delimite('hello'));
	Assert::same('[a[b]', $driver->delimite('a[b'));
	Assert::same('[a]]b]', $driver->delimite('a]b'));
}

// SQLite has no escape for ] inside [...]
$sqlite = new Nette\Database\Drivers\SqliteDriver;
Assert::same('[hello]', $sqlite->delimite('hello'));
Assert::same('[a[b]', $sqlite->delimite('a[b'));
Assert::exception(
	fn() => $sqlite->delimite('a]b'),
	Nette\InvalidArgumentException::class,
	'Identifier must not contain the ] character.',
);
