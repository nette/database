<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Table\SqlBuilder: addOrder() anmd setOrder()
 * @dataProvider? ../databases.ini
 */

use Nette\Database\Table\SqlBuilder;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer->getConnection(), __DIR__ . "/../files/{$driverName}-nette_test1.sql");

test('add multiple order conditions with parameters', function () use ($explorer) {
	$sqlBuilder = new SqlBuilder('book', $explorer);
	$sqlBuilder->addOrder('id');
	$sqlBuilder->addOrder('title DESC');
	$sqlBuilder->addOrder('FIELD(title, ?, ?)', 'a', 'b');

	Assert::same(reformat('SELECT * FROM [book] ORDER BY [id], [title] DESC, FIELD([title], ?, ?)'), $sqlBuilder->buildSelectQuery());
	Assert::same(['a', 'b'], $sqlBuilder->getParameters());
});


test('set order conditions replacing previous orders', function () use ($explorer) {
	$sqlBuilder = new SqlBuilder('book', $explorer);
	$sqlBuilder->addOrder('id');
	$sqlBuilder->addOrder('title DESC');
	$sqlBuilder->setOrder(['FIELD(title, ?, ?)'], ['a', 'b']);

	Assert::same(reformat('SELECT * FROM [book] ORDER BY FIELD([title], ?, ?)'), $sqlBuilder->buildSelectQuery());
	Assert::same(['a', 'b'], $sqlBuilder->getParameters());
});


test('implicit ORDER BY of a limited query does not leak into builder state', function () use ($explorer) {
	$sqlBuilder = new SqlBuilder('book', $explorer);
	$sqlBuilder->setLimit(5, null);

	$sql = $sqlBuilder->buildSelectQuery();
	Assert::match('%a%ORDER BY%a%', $sql);
	Assert::same([], $sqlBuilder->getOrder()); // implicit order is not a builder state
	Assert::same($sql, $sqlBuilder->buildSelectQuery()); // repeated build gives the same query
});


test('getParameters() does not mutate the builder', function () use ($explorer) {
	$sqlBuilder = new SqlBuilder('book', $explorer);
	$sqlBuilder->addWhere('id > ?', 1);
	$sqlBuilder->setLimit(5, null);

	Assert::same([1], $sqlBuilder->getParameters());
	Assert::same([], $sqlBuilder->getOrder());
});
