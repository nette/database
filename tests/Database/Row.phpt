<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Row.
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();

test('numeric field', function () use ($connection) {
	$row = $connection->fetch("SELECT 123 AS {$connection->getDriver()->delimite('123')}, NULL as nullcol");
	Assert::same(123, $row->{123});
	Assert::same(123, $row->{'123'});
	Assert::true(isset($row->{123}));
	Assert::same(123, $row->{123} ?? 'default');
	Assert::false(isset($row->{1}));
	Assert::same('default', $row->{1} ?? 'default');
	Assert::same('default', $row->nullcol ?? 'default');

	Assert::same(123, $row[0]);
	Assert::true(isset($row[0]));
	Assert::false(isset($row[123]));
	Assert::false(isset($row['0'])); // this is buggy since PHP 5.4 (bug #63217) to PHP 7.2

	Assert::false(isset($row[1])); // null value
	Assert::false(isset($row[2])); // is not set

	$falsy = $connection->fetch("SELECT 0 AS a, '' AS b");
	Assert::true(isset($falsy[0])); // falsy value is set
	Assert::true(isset($falsy[1]));
	Assert::false(isset($falsy[2]));

	Assert::error(
		fn() => $row->{2},
		Nette\MemberAccessException::class,
		"Cannot read an undeclared column '2'.",
	);

	Assert::error(
		fn() => $row[2],
		Nette\MemberAccessException::class,
		"Cannot read an undeclared column '2'.",
	);
});


test('isset is not confused by a column named key', function () use ($connection) {
	$row = $connection->fetch("SELECT 123 AS {$connection->getDriver()->delimite('key')}");
	Assert::true(isset($row->key));
	Assert::false(isset($row->missing));
	Assert::same('default', $row->missing ?? 'default');
	Assert::false(isset($row['missing']));
});


test('named field', function () use ($connection) {
	$row = $connection->fetch('SELECT 123 AS title');
	Assert::same(123, $row->title);
	Assert::same(123, $row[0]);
	Assert::same(123, $row['title']);
	Assert::false(isset($row[1])); // null value

	Assert::error(
		fn() => $row->tilte,
		Nette\MemberAccessException::class,
		"Cannot read an undeclared column 'tilte', did you mean 'title'?",
	);

	Assert::error(
		fn() => $row[2],
		Nette\MemberAccessException::class,
		"Cannot read an undeclared column '2'.",
	);
});
