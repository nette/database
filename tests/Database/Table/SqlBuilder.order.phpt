<?php

/**
 * Test: Nette\Database\Table\SqlBuilder: addOrder() anmd setOrder()
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;
use Nette\Database\Table\SqlBuilder;

require __DIR__ . '/../connect.inc.php'; // create $connection


test(function () use ($context) {
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addOrder('id');
	$sqlBuilder->addOrder('title DESC');
	$sqlBuilder->addOrder('FIELD(title, ?, ?)', 'a', 'b');

	Assert::same(reformat('SELECT * FROM [book] ORDER BY [id], [title] DESC, FIELD([title], ?, ?)'), $sqlBuilder->buildSelectQuery());
	Assert::same(['a', 'b'], $sqlBuilder->getParameters());
});


test(function () use ($context) {
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addOrder('id');
	$sqlBuilder->addOrder('title DESC');
	$sqlBuilder->setOrder(['FIELD(title, ?, ?)'], ['a', 'b']);

	Assert::same(reformat('SELECT * FROM [book] ORDER BY FIELD([title], ?, ?)'), $sqlBuilder->buildSelectQuery());
	Assert::same(['a', 'b'], $sqlBuilder->getParameters());
});
