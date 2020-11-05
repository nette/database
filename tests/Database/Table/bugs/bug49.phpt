<?php

/**
 * Test: bug #49
 * @dataProvider? ../../databases.ini mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php';

$explorer->query('CREATE DATABASE IF NOT EXISTS nette_test');
$explorer->query('USE nette_test');
$explorer->query('CREATE TABLE `TABLE 30` (id int)');

Assert::same(
	reformat('SELECT * FROM `TABLE 30`'),
	$explorer->table('TABLE 30')->getSql()
);
