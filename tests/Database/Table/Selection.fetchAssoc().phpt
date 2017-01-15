<?php

/**
 * Test: Nette\Database\Table: Fetch assoc.
 *
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test(function () use ($context) {
	$apps = $context->table('book')->order('title')->fetchAssoc('id=title');  // SELECT * FROM `book` ORDER BY `title`
	Assert::same([
		1 => '1001 tipu a triku pro PHP',
		4 => 'Dibi',
		2 => 'JUSH',
		3 => 'Nette',
	], $apps);
});
