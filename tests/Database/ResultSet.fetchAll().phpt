<?php

/**
 * Test: Nette\Database\ResultSet: Fetch all.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


$res = $connection->query('SELECT id FROM book ORDER BY id');

match ($driverName) {
	'sqlite' => Assert::same(0, $res->getRowCount()), // sqlite: rowCount for SELECT queries is not supported
	'sqlsrv' => Assert::same(-1, $res->getRowCount()), // sqlsrv: for real row count, PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL must be used when $pdo->prepare(). Nette\Database doesn't allowes it now.
	default => Assert::same(4, $res->getRowCount()),
};

Assert::same(1, $res->getColumnCount());
Assert::same('SELECT id FROM book ORDER BY id', $res->getQueryString());

Assert::equal([
	Nette\Database\Row::from(['id' => 1]),
	Nette\Database\Row::from(['id' => 2]),
	Nette\Database\Row::from(['id' => 3]),
	Nette\Database\Row::from(['id' => 4]),
], $res->fetchAll());

Assert::equal([
	Nette\Database\Row::from(['id' => 1]),
	Nette\Database\Row::from(['id' => 2]),
	Nette\Database\Row::from(['id' => 3]),
	Nette\Database\Row::from(['id' => 4]),
], $res->fetchAll());
