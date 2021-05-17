<?php

/**
 * Test: Nette\Database\Table: limit.
 * @dataProvider? ../databases.ini, != sqlsrv
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


Assert::same(
	reformat('SELECT * FROM [author] ORDER BY [author].[id] LIMIT 2'),
	$explorer->table('author')->limit(2)->getSql()
);

Assert::same(
	reformat('SELECT * FROM [author] ORDER BY [author].[id] LIMIT 2 OFFSET 10'),
	$explorer->table('author')->limit(2, 10)->getSql()
);

Assert::same(
	reformat('SELECT * FROM [author] ORDER BY [name] LIMIT 2'),
	$explorer->table('author')->order('name')->limit(2)->getSql()
);

Assert::same(
	reformat('SELECT * FROM [author] ORDER BY [author].[id] LIMIT 10'),
	$explorer->table('author')->page(1, 10)->getSql()
);

Assert::same(
	reformat('SELECT * FROM [author] ORDER BY [author].[id] LIMIT 0'),
	$explorer->table('author')->page(0, 10, $count)->getSql()
);
Assert::same(1, $count);

Assert::same(
	reformat('SELECT * FROM [author] ORDER BY [author].[id] LIMIT 10 OFFSET 10'),
	$explorer->table('author')->page(2, 10, $count)->getSql()
);
Assert::same(1, $count);

Assert::same(
	reformat('SELECT * FROM [author] ORDER BY [author].[id] LIMIT 2 OFFSET 2'),
	$explorer->table('author')->page(2, 2, $count)->getSql()
);
Assert::same(2, $count);
