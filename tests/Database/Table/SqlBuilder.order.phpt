<?php

/**
 * Test: Nette\Database\Table\SqlBuilder: addOrder() anmd setOrder()
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;
use Nette\Database\Table\SqlBuilder;

require __DIR__ . '/../connect.inc.php'; // create $connection


test(function() use ($context) {
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addOrder('id');
	$sqlBuilder->addOrder('title DESC');
	$sqlBuilder->addOrder('FIELD(title, ?, ?)', 'a', 'b');

	Assert::same(reformat('SELECT * FROM [book] ORDER BY [id], [title] DESC, FIELD([title], ?, ?)'), $sqlBuilder->buildSelectQuery());
	Assert::same(array('a', 'b'), $sqlBuilder->getParameters());
});


test(function() use ($context) {
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addOrder('id');
	$sqlBuilder->addOrder('title DESC');
	$sqlBuilder->setOrder(array('FIELD(title, ?, ?)'), array('a', 'b'));

	Assert::same(reformat('SELECT * FROM [book] ORDER BY FIELD([title], ?, ?)'), $sqlBuilder->buildSelectQuery());
	Assert::same(array('a', 'b'), $sqlBuilder->getParameters());
});
