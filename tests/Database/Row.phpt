<?php

/**
 * Test: Nette\Database\Row.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection


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
	if (PHP_VERSION_ID > 70300) {
		Assert::false(isset($row['0'])); // this is buggy since PHP 5.4 (bug #63217) to PHP 7.2
	}

	Assert::false(isset($row[1])); // null value
	Assert::false(isset($row[2])); // is not set

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
