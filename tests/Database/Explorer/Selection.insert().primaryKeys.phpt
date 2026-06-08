<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Table\Selection: Different setup for primary keys
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test4.sql");

test('insert into table with simple primary index (autoincrement)', function () use ($explorer) {
	$simplePkAutoincrementResult = $explorer->table('simple_pk_autoincrement')->insert([
		'note' => 'Some note here',
	]);

	Assert::type(Nette\Database\Table\ActiveRow::class, $simplePkAutoincrementResult);
	Assert::same(1, $simplePkAutoincrementResult->identifier1);
	Assert::same('Some note here', $simplePkAutoincrementResult->note);

	$simplePkAutoincrementResult2 = $explorer->table('simple_pk_autoincrement')->insert([
		'note' => 'Some note here 2',
	]);

	Assert::type(Nette\Database\Table\ActiveRow::class, $simplePkAutoincrementResult2);
	Assert::same(2, $simplePkAutoincrementResult2->identifier1);
	Assert::same('Some note here 2', $simplePkAutoincrementResult2->note);
});

test('insert with an empty array leaves all columns to their defaults', function () use ($explorer) {
	$row = $explorer->table('simple_pk_autoincrement')->insert([]);

	Assert::type(Nette\Database\Table\ActiveRow::class, $row);
	Assert::same(3, $row->identifier1);
	Assert::null($row->note);
});

test('insert with an empty Traversable is a row of defaults, not an empty bulk', function () use ($explorer) {
	// $form->getValues() returns an ArrayHash; an unfilled form must still insert a row
	$row = $explorer->table('simple_pk_autoincrement')->insert(Nette\Utils\ArrayHash::from([]));

	Assert::type(Nette\Database\Table\ActiveRow::class, $row);
	Assert::same(4, $row->identifier1);
	Assert::null($row->note);
});

test('insert into table with simple primary index (no autoincrement)', function () use ($explorer) {
	$simplePkNoAutoincrementResult = $explorer->table('simple_pk_no_autoincrement')->insert([
		'identifier1' => 100,
		'note' => 'Some note here',
	]);

	Assert::type(Nette\Database\Table\ActiveRow::class, $simplePkNoAutoincrementResult);
	Assert::same(100, $simplePkNoAutoincrementResult->identifier1);
	Assert::same('Some note here', $simplePkNoAutoincrementResult->note);

	$simplePkNoAutoincrementResult2 = $explorer->table('simple_pk_no_autoincrement')->insert([
		'identifier1' => 200,
		'note' => 'Some note here 2',
	]);

	Assert::type(Nette\Database\Table\ActiveRow::class, $simplePkNoAutoincrementResult2);
	Assert::same(200, $simplePkNoAutoincrementResult2->identifier1);
	Assert::same('Some note here 2', $simplePkNoAutoincrementResult2->note);
});

test('insert into table with multi column primary index (no autoincrement)', function () use ($explorer) {
	$multiPkNoAutoincrementResult = $explorer->table('multi_pk_no_autoincrement')->insert([
		'identifier1' => 5,
		'identifier2' => 10,
		'note' => 'Some note here',
	]);

	Assert::type(Nette\Database\Table\ActiveRow::class, $multiPkNoAutoincrementResult);
	Assert::same(5, $multiPkNoAutoincrementResult->identifier1);
	Assert::same(10, $multiPkNoAutoincrementResult->identifier2);
	Assert::same('Some note here', $multiPkNoAutoincrementResult->note);

	$multiPkNoAutoincrementResult2 = $explorer->table('multi_pk_no_autoincrement')->insert([
		'identifier1' => 5,
		'identifier2' => 100,
		'note' => 'Some note here 2',
	]);

	Assert::type(Nette\Database\Table\ActiveRow::class, $multiPkNoAutoincrementResult2);
	Assert::same(5, $multiPkNoAutoincrementResult2->identifier1);
	Assert::same(100, $multiPkNoAutoincrementResult2->identifier2);
	Assert::same('Some note here 2', $multiPkNoAutoincrementResult2->note);
});

test('insert into table with multi column primary index (autoincrement)', function () use ($driverName, $explorer) {
	if (in_array($driverName, ['mysql', 'pgsql'], strict: true)) {
		$multiPkAutoincrementResult = $explorer->table('multi_pk_autoincrement')->insert([
			'identifier2' => 999,
			'note' => 'Some note here',
		]);

		Assert::type(Nette\Database\Table\ActiveRow::class, $multiPkAutoincrementResult);
		Assert::same(1, $multiPkAutoincrementResult->identifier1);
		Assert::same(999, $multiPkAutoincrementResult->identifier2);
		Assert::same('Some note here', $multiPkAutoincrementResult->note);

		$multiPkAutoincrementResult2 = $explorer->table('multi_pk_autoincrement')->insert([
			'identifier2' => 999,
			'note' => 'Some note here 2',
		]);

		Assert::type(Nette\Database\Table\ActiveRow::class, $multiPkAutoincrementResult2);
		Assert::same(2, $multiPkAutoincrementResult2->identifier1);
		Assert::same(999, $multiPkAutoincrementResult2->identifier2);
		Assert::same('Some note here 2', $multiPkAutoincrementResult2->note);
	}
});

test('insert into table without primary key', function () use ($explorer) {
	$noPkResult1 = $explorer->table('no_pk')->insert([
		'note' => 'Some note here',
	]);
	Assert::same(1, $noPkResult1);
});

test('composite primary key insert returns a lazy row', function () use ($explorer, $connection) {
	$row = $explorer->table('multi_pk_no_autoincrement')->insert([
		'identifier1' => 7,
		'identifier2' => 14,
		'note' => 'lazy composite',
	]);

	$count = 0;
	$connection->onQuery[] = function () use (&$count) { $count++; };

	Assert::same(7, $row->identifier1);
	Assert::same(14, $row->identifier2);
	Assert::same(0, $count); // both primary key columns are available without a query

	Assert::same('lazy composite', $row->note);
	Assert::same(1, $count); // the first non-primary access fetches the rest
});

test('numeric-string primary key values are normalized to int for integer columns', function () use ($explorer, $connection) {
	$row = $explorer->table('multi_pk_no_autoincrement')->insert([
		'identifier1' => '8',
		'identifier2' => '16',
		'note' => 'string ids',
	]);

	$count = 0;
	$connection->onQuery[] = function () use (&$count) { $count++; };

	Assert::same(8, $row->identifier1);
	Assert::same(16, $row->identifier2);
	Assert::same(0, $count); // normalized without fetching the row
});

test('numeric-string value in a string primary key column stays a string', function () use ($explorer, $connection) {
	$row = $explorer->table('string_pk')->insert([
		'identifier1' => '9',
		'note' => 'string pk',
	]);

	$count = 0;
	$connection->onQuery[] = function () use (&$count) { $count++; };

	Assert::same('9', $row->identifier1);
	Assert::same(0, $count);

	Assert::same('string pk', $row->note);
	Assert::same('9', $row->identifier1); // matches what a fetch returns
});
