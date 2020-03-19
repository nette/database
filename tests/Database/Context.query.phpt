<?php

/**
 * Test: Nette\Database\Explorer query methods.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('', function () use ($context) {
	$res = $context->query('SELECT id FROM author WHERE id = ?', 11);
	Assert::type(Nette\Database\ResultSet::class, $res);
	Assert::same('SELECT id FROM author WHERE id = ?', $res->getQueryString());
	Assert::same([11], $res->getParameters());
});


test('', function () use ($context) {
	$res = $context->query('SELECT id FROM author WHERE id = ? OR id = ?', 11, 12);
	Assert::same('SELECT id FROM author WHERE id = ? OR id = ?', $res->getQueryString());
	Assert::same([11, 12], $res->getParameters());
});


test('', function () use ($context) {
	$res = $context->queryArgs('SELECT id FROM author WHERE id = ? OR id = ?', [11, 12]);
	Assert::same('SELECT id FROM author WHERE id = ? OR id = ?', $res->getQueryString());
	Assert::same([11, 12], $res->getParameters());
});
