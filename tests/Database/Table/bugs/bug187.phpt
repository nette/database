<?php

/**
 * Test: bug 187
 * @dataProvider? ../../databases.ini mysql
 */

use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require __DIR__ . '/../../connect.inc.php';

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test5.sql");

foreach ([true, false] as $published) {
	if ($published) {
		$where = '(:PhotoNonPublic.number IS NULL)';
	} else {
		$where = '(:PhotoNonPublic.number IS NOT NULL)';
	}

	$result = $context->table('Photo')->where($where);

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
