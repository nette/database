<?php

/**
 * Test: bug #49
 * @dataProvider? ../../databases.ini mysql
 */

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php';

$context->query('CREATE DATABASE IF NOT EXISTS nette_test');
$context->query('USE nette_test');
$context->query('CREATE TABLE `TABLE 30` (id int)');

Assert::same(
	reformat('SELECT * FROM `TABLE 30`'),
	$context->table('TABLE 30')->getSql()
);
