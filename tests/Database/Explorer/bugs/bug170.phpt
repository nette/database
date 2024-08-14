<?php

/**
 * Test: bug 170
 * @dataProvider? ../../databases.ini mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-bug170.sql");

Assert::noError(function () use ($explorer) {
	// this bug is about picking the right foreign key to specified table regardless FKs definition order
	$explorer->table('Operator1')->where('country.id')->count();
	$explorer->table('Operator2')->where('country.id')->count();
});
