<?php

/**
 * Test: Nette\Database\Explorer query methods.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$res = $explorer->query('SELECT id FROM author WHERE id = ?', 11);
	Assert::type(Nette\Database\Result::class, $res);
	Assert::same('SELECT id FROM author WHERE id = ?', $res->getQuery()->getSql());
	Assert::same([11], $res->getQuery()->getParameters());
});


test('', function () use ($explorer) {
	$res = $explorer->query('SELECT id FROM author WHERE id = ? OR id = ?', 11, 12);
	Assert::same('SELECT id FROM author WHERE id = ? OR id = ?', $res->getQuery()->getSql());
	Assert::same([11, 12], $res->getQuery()->getParameters());
});


test('', function () use ($explorer) {
	$res = @$explorer->queryArgs('SELECT id FROM author WHERE id = ? OR id = ?', [11, 12]); // is deprecated
	Assert::same('SELECT id FROM author WHERE id = ? OR id = ?', $res->getQuery()->getSql());
	Assert::same([11, 12], $res->getQuery()->getParameters());
});
