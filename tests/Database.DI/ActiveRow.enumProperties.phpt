<?php declare(strict_types=1);

/**
 * Test: ActiveRow auto-converts BackedEnum columns based on declared property type.
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


enum UserStatus: string
{
	case Active = 'active';
	case Suspended = 'suspended';
}

enum Role: int
{
	case Admin = 1;
	case User = 2;
}

class UserRow extends ActiveRow
{
	public int $id;
	public string $email;
	public UserStatus $status;
	public ?Role $role;
}


function createExplorer(): Explorer
{
	$connection = new Connection('sqlite::memory:');
	$connection->query('CREATE TABLE users (
		id INTEGER PRIMARY KEY,
		email TEXT NOT NULL,
		status TEXT NOT NULL,
		role INTEGER NULL
	)');
	$connection->query("INSERT INTO users (email, status, role) VALUES ('a@x.com', 'active', 1)");
	$connection->query("INSERT INTO users (email, status, role) VALUES ('b@x.com', 'suspended', NULL)");

	$storage = new MemoryStorage;
	$structure = new Structure($connection, $storage);
	$conventions = new DiscoveredConventions($structure);
	$mapping = new DefaultEntityMapping(['users' => UserRow::class]);
	return new Explorer($connection, $structure, $conventions, $storage, $mapping);
}


test('BackedEnum column is converted on __get', function () {
	$explorer = createExplorer();
	$row = $explorer->table('users')->get(1);

	Assert::same(UserStatus::Active, $row->status);
	Assert::same(Role::Admin, $row->role);
});


test('nullable enum property handles NULL', function () {
	$explorer = createExplorer();
	$row = $explorer->table('users')->get(2);

	Assert::same(UserStatus::Suspended, $row->status);
	Assert::null($row->role);
});


test('non-enum typed property returns scalar unchanged', function () {
	$explorer = createExplorer();
	$row = $explorer->table('users')->get(1);

	Assert::same(1, $row->id);
	Assert::same('a@x.com', $row->email);
});


test('toArray and iterator return converted enum values', function () {
	$explorer = createExplorer();
	$row = $explorer->table('users')->get(1);

	$arr = $row->toArray();
	Assert::same(UserStatus::Active, $arr['status']);
	Assert::same(Role::Admin, $arr['role']);

	$iter = iterator_to_array($row);
	Assert::same(UserStatus::Active, $iter['status']);
	Assert::same(Role::Admin, $iter['role']);
});


test('update accepts enum values, refetched row exposes converted enum', function () {
	$explorer = createExplorer();
	$row = $explorer->table('users')->get(1);
	$row->update(['status' => UserStatus::Suspended]);

	Assert::same(UserStatus::Suspended, $row->status);

	$fresh = $explorer->table('users')->get(1);
	Assert::same(UserStatus::Suspended, $fresh->status);
});
