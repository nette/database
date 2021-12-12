<?php

/**
 * Test: bug 1356
 * @dataProvider? ../../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php';

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");


$books = $explorer->table('book')->limit(1);
foreach ($books as $book) {
	$book->id;
}

$books->__destruct();


$books = $explorer->table('book')->limit(1);
foreach ($books as $book) {
	$book->title;
}

Assert::same(reformat([
	'sqlsrv' => $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION) < 11
		? 'SELECT TOP 1 * FROM [book] ORDER BY [book].[id]'
		: 'SELECT * FROM [book] ORDER BY [book].[id] OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY',
	'SELECT * FROM [book] ORDER BY [book].[id] LIMIT 1',
]), $books->getSql());
