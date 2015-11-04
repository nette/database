<?php

/**
 * Test: Nette\Database\Table: limit.
 * @dataProvider? ../databases.ini, sqlsrv
 */

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

$version2008 = $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION) < 11;

Assert::same(
	$version2008
		? 'SELECT TOP 2 * FROM [author]'
		: 'SELECT * FROM [author] OFFSET 0 ROWS FETCH NEXT 2 ROWS ONLY',
	$context->table('author')->limit(2)->getSql()
);

Assert::same(
	$version2008
		? 'SELECT TOP 2 * FROM [author] ORDER BY [name]'
		: 'SELECT * FROM [author] ORDER BY [name] OFFSET 0 ROWS FETCH NEXT 2 ROWS ONLY',
	$context->table('author')->order('name')->limit(2)->getSql()
);

Assert::same(
	$version2008
		? 'SELECT TOP 10 * FROM [author]'
		: 'SELECT * FROM [author] OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
	$context->table('author')->page(1, 10)->getSql()
);

Assert::same(
	$version2008
		? 'SELECT TOP 10 * FROM [author]'
		: 'SELECT * FROM [author] OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
	$context->table('author')->page(0, 10)->getSql()
);

if ($version2008) {
	Assert::exception(function () use ($context) {
		$context->table('author')->page(2, 10, $count)->getSql();
	}, 'Nette\NotSupportedException', 'Offset is not supported by this database.');

	Assert::exception(function () use ($context) {
		$context->table('author')->page(2, 2, $count)->getSql();
	}, 'Nette\NotSupportedException', 'Offset is not supported by this database.');

} else {
	Assert::same(
		reformat('SELECT * FROM [author] OFFSET 10 ROWS FETCH NEXT 10 ROWS ONLY'),
		$context->table('author')->page(2, 10, $count)->getSql()
	);
	Assert::same(1, $count);

	Assert::same(
		reformat('SELECT * FROM [author] OFFSET 2 ROWS FETCH NEXT 2 ROWS ONLY'),
		$context->table('author')->page(2, 2, $count)->getSql()
	);
	Assert::same(2, $count);
}
