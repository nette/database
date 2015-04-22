<?php

/**
 * Test: Nette\Database\Table & StaticConventions: bug causing infinite recursion
 * @dataProvider? ../../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php'; // create $connection

$conventions = new \Nette\Database\Conventions\StaticConventions();
$cacheStorage = new \Nette\Caching\Storages\MemoryStorage();
$context = new \Nette\Database\Context($context->getConnection(), $context->getStructure(), $conventions, $cacheStorage);

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");


test(function() use ($context) {
	$book = $context->table('book')->where('id = ?', 1)->fetch();
	Assert::exception(function () use ($book) {
		$book->unknown_column;
	}, 'Nette\MemberAccessException', "Cannot read an undeclared column 'unknown_column'.");
});
