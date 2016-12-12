<?php

/**
 * Test: Nette\Database\Table\Selection: Page
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

if ($driverName === 'sqlsrv') {
	Tester\Environment::skip('Offset is supported since SQL Server 2012'); // and requires to set order
}

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

//public function page($page, $itemsPerPage, & $numOfPages = NULL)

test(function () use ($context) { //first page, one item per page
	$numberOfPages = 0;

	$tags = $context->table('tag')->page(1, 1, $numOfPages)->order('id');
	Assert::equal(1, count($tags)); //one item on first page
	Assert::equal(4, $numOfPages); //four pages total

	//calling the same without the $numOfPages reference
	unset($tags);
	$tags = $context->table('tag')->page(1, 1)->order('id');
	Assert::equal(1, count($tags)); //one item on first page
});

test(function () use ($context) { //second page, three items per page
	$numberOfPages = 0;

	$tags = $context->table('tag')->page(2, 3, $numOfPages)->order('id');
	Assert::equal(1, count($tags)); //one item on second page
	Assert::equal(2, $numOfPages); //two pages total

	//calling the same without the $numOfPages reference
	unset($tags);
	$tags = $context->table('tag')->page(2, 3)->order('id');
	Assert::equal(1, count($tags)); //one item on second page
});

test(function () use ($context) { //page with no items
	$tags = $context->table('tag')->page(10, 4)->order('id');
	Assert::equal(0, count($tags)); //one item on second page
});

test(function () use ($context) { //page with no items (page not in range)
	$tags = $context->table('tag')->page(100, 4)->order('id');
	Assert::equal(0, count($tags)); //one item on second page
});

test(function () use ($context) { //less items than $itemsPerPage
	$tags = $context->table('tag')->page(1, 100)->order('id');
	Assert::equal(4, count($tags)); //all four items from db
});

// SQL Server throw PDOException 'The number of rows provided for a FETCH clause must be greater then zero.'
if ($driverName !== 'sqlsrv') {
	Assert::error(function () use ($context) { //invalid params
		$tags = $context->table('tag')->page('foo', 'bar')->order('id');
		Assert::equal(0, count($tags)); //no items
	}, PHP_VERSION_ID >= 70100 ? array(array(E_WARNING, 'A non-numeric value encountered'), array(E_WARNING, 'A non-numeric value encountered')) : array());
}
