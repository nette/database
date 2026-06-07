<?php declare(strict_types=1);

/**
 * Test: EntityMapping integration with Explorer, ActiveRow and SqlBuilder.
 * Uses a trivial uppercase mapping to make it obvious when translation is applied.
 */

use Nette\Database\Connection;
use Nette\Database\EntityMapping;
use Nette\Database\Explorer;
use Nette\Database\Structure;
use Nette\Database\Conventions\DiscoveredConventions;
use Nette\Caching\Storages\MemoryStorage;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/**
 * Trivial mapping: properties are UPPER_CASE versions of column names.
 */
class UpperCaseMapping implements EntityMapping
{
	public function getClassName(string $table): ?string
	{
		return null;
	}


	public function getPropertyName(string $column): string
	{
		return strtoupper($column);
	}


	public function getColumnName(string $property): string
	{
		return strtolower($property);
	}
}


function createExplorer(EntityMapping $mapping): Explorer
{
	$connection = new Connection('sqlite::memory:');
	$connection->query('CREATE TABLE user_account (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		first_name TEXT NOT NULL,
		last_name TEXT NOT NULL,
		email_address TEXT NOT NULL
	)');
	$connection->query("INSERT INTO user_account (first_name, last_name, email_address) VALUES ('John', 'Doe', 'john@example.com')");
	$connection->query("INSERT INTO user_account (first_name, last_name, email_address) VALUES ('Jane', 'Smith', 'jane@example.com')");

	$storage = new MemoryStorage;
	$structure = new Structure($connection, $storage);
	$conventions = new DiscoveredConventions($structure);
	return new Explorer($connection, $structure, $conventions, $storage, $mapping);
}


test('__get translates property name to column name', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$row = $explorer->table('user_account')->fetch();

	Assert::same('John', $row->FIRST_NAME);
	Assert::same('Doe', $row->LAST_NAME);
	Assert::same('john@example.com', $row->EMAIL_ADDRESS);
	Assert::same(1, $row->ID);
});


test('__get suggestion is in property names', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$row = $explorer->table('user_account')->fetch();

	Assert::exception(
		fn() => $row->FIRST_NAM,
		Nette\MemberAccessException::class,
		"Cannot read an undeclared column 'FIRST_NAM', did you mean 'FIRST_NAME'?",
	);
});


test('__isset translates property name', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$row = $explorer->table('user_account')->fetch();

	Assert::true(isset($row->FIRST_NAME));
	Assert::true(isset($row->ID));
	Assert::false(isset($row->NONEXISTENT));
});


test('toArray returns translated keys', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$row = $explorer->table('user_account')->fetch();

	Assert::same(['ID', 'FIRST_NAME', 'LAST_NAME', 'EMAIL_ADDRESS'], array_keys($row->toArray()));
	Assert::same('John', $row->toArray()['FIRST_NAME']);
});


test('getIterator returns translated keys', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$row = $explorer->table('user_account')->fetch();

	Assert::same(['ID', 'FIRST_NAME', 'LAST_NAME', 'EMAIL_ADDRESS'], array_keys(iterator_to_array($row)));
});


test('update translates property keys to column names', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$row = $explorer->table('user_account')->get(1);

	$row->update(['FIRST_NAME' => 'Johnny', 'LAST_NAME' => 'Updated']);

	Assert::same('Johnny', $row->FIRST_NAME);
	Assert::same('Updated', $row->LAST_NAME);

	$fresh = $explorer->table('user_account')->get(1);
	Assert::same('Johnny', $fresh->FIRST_NAME);
});


test('update handles compound assignment operators', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$connection = $explorer->getConnection();
	$connection->query('CREATE TABLE product (id INTEGER PRIMARY KEY, total_score INTEGER NOT NULL)');
	$connection->query('INSERT INTO product (total_score) VALUES (10)');

	$row = $explorer->table('product')->get(1);
	$row->update(['TOTAL_SCORE+=' => 5]);

	$fresh = $explorer->table('product')->get(1);
	Assert::same(15, $fresh->TOTAL_SCORE);
});


test('where translates via tryDelimite', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$row = $explorer->table('user_account')
		->where('FIRST_NAME', 'Jane')
		->fetch();

	Assert::same('Jane', $row->FIRST_NAME);
	Assert::same('Smith', $row->LAST_NAME);
});


test('order by translates via tryDelimite', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$rows = array_values($explorer->table('user_account')
		->order('FIRST_NAME DESC')
		->fetchAll());

	Assert::same('John', $rows[0]->FIRST_NAME);
	Assert::same('Jane', $rows[1]->FIRST_NAME);
});


test('Selection::insert translates property keys', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$row = $explorer->table('user_account')->insert([
		'FIRST_NAME' => 'Alice',
		'LAST_NAME' => 'Wonder',
		'EMAIL_ADDRESS' => 'alice@example.com',
	]);

	Assert::same('Alice', $row->FIRST_NAME);
	Assert::same('Wonder', $row->LAST_NAME);
});


test('Selection::insertMany translates property keys', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$explorer->table('user_account')->insertMany([
		['FIRST_NAME' => 'Bob', 'LAST_NAME' => 'Builder', 'EMAIL_ADDRESS' => 'bob@example.com'],
		['FIRST_NAME' => 'Cara', 'LAST_NAME' => 'Coder', 'EMAIL_ADDRESS' => 'cara@example.com'],
	]);

	$rows = array_values($explorer->table('user_account')->where('FIRST_NAME', ['Bob', 'Cara'])->order('FIRST_NAME')->fetchAll());
	Assert::count(2, $rows);
	Assert::same('Bob', $rows[0]->FIRST_NAME);
	Assert::same('Cara', $rows[1]->FIRST_NAME);
});


test('Selection::update translates property keys', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$affected = $explorer->table('user_account')
		->where('ID', 1)
		->update(['FIRST_NAME' => 'Renamed']);

	Assert::same(1, $affected);
	Assert::same('Renamed', $explorer->table('user_account')->get(1)->FIRST_NAME);
});


test('Selection::update translates compound assignment', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$connection = $explorer->getConnection();
	$connection->query('CREATE TABLE counter (id INTEGER PRIMARY KEY, total_score INTEGER NOT NULL)');
	$connection->query('INSERT INTO counter (total_score) VALUES (10)');

	$explorer->table('counter')->where('ID', 1)->update(['TOTAL_SCORE+=' => 7]);

	Assert::same(17, $explorer->table('counter')->get(1)->TOTAL_SCORE);
});


test('round-trip: toArray feeds insert', function () {
	$explorer = createExplorer(new UpperCaseMapping);
	$source = $explorer->table('user_account')->get(1)->toArray();
	unset($source['ID']);
	$source['EMAIL_ADDRESS'] = 'copy@example.com';

	$row = $explorer->table('user_account')->insert($source);
	Assert::same('John', $row->FIRST_NAME);
	Assert::same('copy@example.com', $row->EMAIL_ADDRESS);
});
