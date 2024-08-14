<?php

/**
 * Test: bug 187
 * @dataProvider? ../../databases.ini mysql
 */

declare(strict_types=1);

use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-bug187.sql");

foreach ([true, false] as $published) {
	$where = $published
		? '(:PhotoNonPublic.number IS NULL)'
		: '(:PhotoNonPublic.number IS NOT NULL)';

	$result = $explorer->table('Photo')->where($where);

	foreach ($result as $photoRow) {
		/** @var ActiveRow $photoRow */
		$related = $photoRow->related('PhotoNonPublic');

		if ($related->count() != 0) {
			$related->fetch()->toArray();
		}
	}

	// destructing -> saveCacheState
	$result->__destruct();
	$related->__destruct();
}

Assert::true(true);
