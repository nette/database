<?php declare(strict_types=1);

/**
 * Test: entity-mapping name translation is validated against the actual schema when
 * the structure cache is being (re)built: a column unreachable through the mapping
 * (camelCase column under camelCase mapping, colliding column pair, underscore
 * directly before a digit) fails loudly instead of silently, and the failure aborts
 * caching so it repeats until fixed; the except map is the escape hatch.
 */

use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\{Connection, DefaultEntityMapping, Explorer, Structure};
use Nette\Database\Conventions\DiscoveredConventions;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createExplorer(array $schema, bool $camelCase, array $except = []): Explorer
{
	$connection = new Connection('sqlite::memory:');
	foreach ($schema as $sql) {
		$connection->query($sql);
	}
	$storage = new MemoryStorage;
	$structure = new Structure($connection, $storage);
	return new Explorer(
		$connection,
		$structure,
		new DiscoveredConventions($structure),
		$storage,
		new DefaultEntityMapping([], camelCase: $camelCase, except: $except),
	);
}


test('snake_case schema passes and works', function () {
	$explorer = createExplorer(['CREATE TABLE clean (id INTEGER PRIMARY KEY AUTOINCREMENT, foo_bar INT)'], camelCase: true);
	$explorer->table('clean')->insert(['fooBar' => 7]);
	Assert::same(7, $explorer->table('clean')->fetch()->fooBar);
});


test('a camelCase column fails loudly when the structure cache is built, even when accessing another table', function () {
	$connection = new Connection('sqlite::memory:');
	$connection->query('CREATE TABLE broken (id INTEGER PRIMARY KEY AUTOINCREMENT, realCamel INT)');
	$connection->query('CREATE TABLE clean (id INTEGER PRIMARY KEY AUTOINCREMENT, foo_bar INT)');
	$storage = new MemoryStorage;
	$mapping = new DefaultEntityMapping([], camelCase: true);

	$structure = new Structure($connection, $storage);
	$explorer = new Explorer($connection, $structure, new DiscoveredConventions($structure), $storage, $mapping);
	Assert::exception(
		fn() => $explorer->table('clean'), // validation covers the whole schema
		Nette\InvalidStateException::class,
		"Column 'broken.realCamel' cannot be addressed through entity mapping: its property name 'realCamel' translates back to column 'real_camel'. Rename the column or adjust the mapping.",
	);

	// the failure prevents caching: the next request (a fresh Structure over the same storage) repeats the rebuild and fails loudly again
	$structure2 = new Structure($connection, $storage);
	$explorer2 = new Explorer($connection, $structure2, new DiscoveredConventions($structure2), $storage, $mapping);
	Assert::exception(
		fn() => $explorer2->table('clean'),
		Nette\InvalidStateException::class,
	);
});


test('a colliding column pair fails loudly', function () {
	$explorer = createExplorer(
		['CREATE TABLE colliding (id INTEGER PRIMARY KEY AUTOINCREMENT, foo_bar INT, fooBar INT)'],
		camelCase: true,
	);
	Assert::exception(
		fn() => $explorer->table('colliding'),
		Nette\InvalidStateException::class,
		"Column 'colliding.fooBar' cannot be addressed through entity mapping: its property name 'fooBar' translates back to column 'foo_bar'. Rename the column or adjust the mapping.",
	);
});


test('digit runs attached to the preceding word are invertible and pass', function () {
	$explorer = createExplorer(
		['CREATE TABLE digits (id INTEGER PRIMARY KEY AUTOINCREMENT, address2 INT, sha256_hash TEXT, oauth2_token TEXT, utf8mb4_name TEXT, col_v2 INT)'],
		camelCase: true,
	);
	$explorer->table('digits')->insert(['address2' => 1, 'sha256Hash' => 'x', 'oauth2Token' => 'y', 'utf8mb4Name' => 'z', 'colV2' => 2]);
	$row = $explorer->table('digits')->fetch();
	Assert::same(1, $row->address2);
	Assert::same('x', $row->sha256Hash);
	Assert::same(2, $row->colV2);
});


test('an underscore directly before a digit fails with a digit hint', function () {
	$explorer = createExplorer(
		['CREATE TABLE legacy (id INTEGER PRIMARY KEY AUTOINCREMENT, address_2 INT)'],
		camelCase: true,
	);
	Assert::exception(
		fn() => $explorer->table('legacy'),
		Nette\InvalidStateException::class,
		"Column 'legacy.address_2' cannot be addressed through entity mapping: its property name 'address2' translates back to column 'address2'. An underscore directly before a digit has no camelCase form; map the column explicitly via the mapping's except option, or rename it.",
	);
});


test('except map makes such a column addressable in both directions', function () {
	$explorer = createExplorer(
		['CREATE TABLE legacy (id INTEGER PRIMARY KEY AUTOINCREMENT, address_2 INT)'],
		camelCase: true,
		except: ['address2' => 'address_2'],
	);
	$explorer->table('legacy')->insert(['address2' => 5]);
	$row = $explorer->table('legacy')->where('address2', 5)->fetch();
	Assert::same(5, $row->address2);
	Assert::same(['id' => 1, 'address2' => 5], $row->toArray());
	$row->update(['address2' => 6]);
	Assert::same(6, $explorer->table('legacy')->fetch()->address2);
});


test('except pair whose column collides with a real column fails loudly', function () {
	$explorer = createExplorer(
		['CREATE TABLE digits (id INTEGER PRIMARY KEY AUTOINCREMENT, address2 INT)'],
		camelCase: true,
		except: ['address2' => 'address_2'],
	);
	Assert::exception(
		fn() => $explorer->table('digits'),
		Nette\InvalidStateException::class,
		"Column 'digits.address2' cannot be addressed through entity mapping: its property name 'address2' translates back to column 'address_2'. Rename the column or adjust the mapping.",
	);
});


test('except map works without camelCase conversion', function () {
	$explorer = createExplorer(
		['CREATE TABLE legacy (id INTEGER PRIMARY KEY AUTOINCREMENT, address_2 INT)'],
		camelCase: false,
		except: ['address2' => 'address_2'],
	);
	$explorer->table('legacy')->insert(['address2' => 3]);
	Assert::same(3, $explorer->table('legacy')->fetch()->address2);
});


test('identity mapping (camelCase off) accepts any schema', function () {
	$explorer = createExplorer([
		'CREATE TABLE broken (id INTEGER PRIMARY KEY AUTOINCREMENT, realCamel INT)',
		'CREATE TABLE colliding (id INTEGER PRIMARY KEY AUTOINCREMENT, foo_bar INT, fooBar INT)',
	], camelCase: false);
	$explorer->table('broken')->insert(['realCamel' => 1]);
	Assert::same(1, $explorer->table('broken')->fetch()->realCamel);
	Assert::same(0, $explorer->table('colliding')->count('*'));
});


test('a valid schema is validated only while the cache is cold', function () {
	$connection = new Connection('sqlite::memory:');
	$connection->query('CREATE TABLE clean (id INTEGER PRIMARY KEY AUTOINCREMENT, foo_bar INT)');
	$storage = new MemoryStorage;

	// first "request" builds and caches the structure, validation runs
	$structure = new Structure($connection, $storage);
	$explorer = new Explorer(
		$connection,
		$structure,
		new DiscoveredConventions($structure),
		$storage,
		new DefaultEntityMapping([], camelCase: true),
	);
	$explorer->table('clean')->count('*');
	Assert::true($structure->isRebuilt());

	// second "request" over the warm cache does not rebuild, so validation does not run
	$structure2 = new Structure($connection, $storage);
	$explorer2 = new Explorer(
		$connection,
		$structure2,
		new DiscoveredConventions($structure2),
		$storage,
		new DefaultEntityMapping([], camelCase: true),
	);
	$explorer2->table('clean')->count('*');
	Assert::false($structure2->isRebuilt());
});
