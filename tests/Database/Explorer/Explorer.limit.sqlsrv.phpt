<?php

/**
 * Test: Nette\Database\Table: limit.
 * @dataProvider? ../databases.ini, sqlsrv
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

$version2008 = $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION) < 11;

Assert::same(
	$version2008
		? 'SELECT TOP 2 * FROM [author] ORDER BY [author].[id]'
		: 'SELECT * FROM [author] ORDER BY [author].[id] OFFSET 0 ROWS FETCH NEXT 2 ROWS ONLY',
	$explorer->table('author')->limit(2)->getSql(),
);

Assert::same(
	$version2008
		? 'SELECT TOP 2 * FROM [author] ORDER BY [name]'
		: 'SELECT * FROM [author] ORDER BY [name] OFFSET 0 ROWS FETCH NEXT 2 ROWS ONLY',
	$explorer->table('author')->order('name')->limit(2)->getSql(),
);

Assert::same(
	$version2008
		? 'SELECT TOP 10 * FROM [author] ORDER BY [author].[id]'
		: 'SELECT * FROM [author] ORDER BY [author].[id] OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY',
	$explorer->table('author')->page(1, 10)->getSql(),
);

Assert::same(
	$version2008
		? 'SELECT TOP 0 * FROM [author] ORDER BY [author].[id]'
		: 'SELECT * FROM [author] ORDER BY [author].[id] OFFSET 0 ROWS FETCH NEXT 0 ROWS ONLY',
	$explorer->table('author')->page(0, 10)->getSql(),
);

if ($version2008) {
	Assert::exception(
		fn() => $explorer->table('author')->page(2, 10, $count)->getSql(),
		Nette\NotSupportedException::class,
		'Offset is not supported by this database.',
	);

	Assert::exception(
		fn() => $explorer->table('author')->page(2, 2, $count)->getSql(),
		Nette\NotSupportedException::class,
		'Offset is not supported by this database.',
	);

} else {
	Assert::same(
		reformat('SELECT * FROM [author] ORDER BY [author].[id] OFFSET 10 ROWS FETCH NEXT 10 ROWS ONLY'),
		$explorer->table('author')->page(2, 10, $count)->getSql(),
	);
	Assert::same(1, $count);

	Assert::same(
		reformat('SELECT * FROM [author] ORDER BY [author].[id] OFFSET 2 ROWS FETCH NEXT 2 ROWS ONLY'),
		$explorer->table('author')->page(2, 2, $count)->getSql(),
	);
	Assert::same(2, $count);
}
