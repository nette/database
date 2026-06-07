<?php declare(strict_types=1);

/**
 * Test: ActiveRow subclass can declare typed public properties for IDE/static analysis;
 * access still goes through __get thanks to unset() in the constructor.
 */

use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Connection;
use Nette\Database\Conventions\DiscoveredConventions;
use Nette\Database\DefaultEntityMapping;
use Nette\Database\Explorer;
use Nette\Database\Structure;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class UserRow extends ActiveRow
{
	public int $id;
	public string $email;
	public ?string $nickname;
}


function createExplorer(): Explorer
{
	$connection = new Connection('sqlite::memory:');
	$connection->query('CREATE TABLE users (
		id INTEGER PRIMARY KEY,
		email TEXT NOT NULL,
		nickname TEXT NULL
	)');
	$connection->query("INSERT INTO users (email, nickname) VALUES ('a@x.com', 'al')");
	$connection->query("INSERT INTO users (email, nickname) VALUES ('b@x.com', NULL)");

	$storage = new MemoryStorage;
	$structure = new Structure($connection, $storage);
	$conventions = new DiscoveredConventions($structure);
	$mapping = new DefaultEntityMapping(['users' => UserRow::class]);
	return new Explorer($connection, $structure, $conventions, $storage, $mapping);
}


test('typed properties are readable via __get', function () {
	$explorer = createExplorer();
	$row = $explorer->table('users')->get(1);

	Assert::type(UserRow::class, $row);
	Assert::same(1, $row->id);
	Assert::same('a@x.com', $row->email);
	Assert::same('al', $row->nickname);
});


test('nullable typed property returns null when NULL in database', function () {
	$explorer = createExplorer();
	$row = $explorer->table('users')->get(2);

	Assert::null($row->nickname);
});


test('isset works on typed properties', function () {
	$explorer = createExplorer();
	$row = $explorer->table('users')->get(1);
	$row2 = $explorer->table('users')->get(2);

	Assert::true(isset($row->id));
	Assert::true(isset($row->email));
	Assert::true(isset($row->nickname));
	Assert::false(isset($row2->nickname));
});


test('iterator yields all columns', function () {
	$explorer = createExplorer();
	$row = $explorer->table('users')->get(1);
	$arr = iterator_to_array($row);

	Assert::same(['id' => 1, 'email' => 'a@x.com', 'nickname' => 'al'], $arr);
});


test('plain ActiveRow without typed props still works', function () {
	$connection = new Connection('sqlite::memory:');
	$connection->query('CREATE TABLE thing (id INTEGER PRIMARY KEY, label TEXT NOT NULL)');
	$connection->query("INSERT INTO thing (label) VALUES ('x')");
	$storage = new MemoryStorage;
	$structure = new Structure($connection, $storage);
	$conventions = new DiscoveredConventions($structure);
	$explorer = new Explorer($connection, $structure, $conventions, $storage);

	$row = $explorer->table('thing')->get(1);
	Assert::type(ActiveRow::class, $row);
	Assert::same('x', $row->label);
});
