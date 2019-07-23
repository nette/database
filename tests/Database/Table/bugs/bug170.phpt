<?php

/**
 * Test: bug 170
 * @dataProvider? ../../databases.ini mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php';

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-bug170.sql");

Assert::noError(function () use ($context) {
	// this bug is about picking the right foreign key to specified table regardless FKs definition order
	$context->table('Operator1')->where('country.id')->count();
	$context->table('Operator2')->where('country.id')->count();
});
