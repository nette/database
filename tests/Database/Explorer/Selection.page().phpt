<?php

/**
 * Test: Nette\Database\Table\Selection: Page
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

if ($driverName === 'sqlsrv' && $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION) < 11) {
	Tester\Environment::skip('Offset is supported since SQL Server 2012');
}

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

//public function page($page, $itemsPerPage, &$numOfPages = null)

test('first page, one item per page', function () use ($explorer) {
	$numberOfPages = 0;

	$tags = $explorer->table('tag')->page(1, 1, $numOfPages);
	Assert::equal(1, count($tags)); //one item on first page
	Assert::equal(4, $numOfPages); //four pages total

	//calling the same without the $numOfPages reference
	unset($tags);
	$tags = $explorer->table('tag')->page(1, 1);
	Assert::equal(1, count($tags)); //one item on first page
});

test('second page, three items per page', function () use ($explorer) {
	$numberOfPages = 0;

	$tags = $explorer->table('tag')->page(2, 3, $numOfPages);
	Assert::equal(1, count($tags)); //one item on second page
	Assert::equal(2, $numOfPages); //two pages total

	//calling the same without the $numOfPages reference
	unset($tags);
	$tags = $explorer->table('tag')->page(2, 3);
	Assert::equal(1, count($tags)); //one item on second page
});

test('page with no items', function () use ($explorer) {
	$tags = $explorer->table('tag')->page(10, 4);
	Assert::equal(0, count($tags)); //one item on second page
});

test('page with no items (page not in range)', function () use ($explorer) {
	$tags = $explorer->table('tag')->page(100, 4);
	Assert::equal(0, count($tags)); //one item on second page
});

test('less items than $itemsPerPage', function () use ($explorer) {
	$tags = $explorer->table('tag')->page(1, 100);
	Assert::equal(4, count($tags)); //all four items from db
});
