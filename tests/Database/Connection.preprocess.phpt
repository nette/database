<?php

/**
 * Test: Nette\Database\Connection preprocess.
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection


Assert::same(array('SELECT name FROM author', array()), $connection->preprocess('SELECT name FROM author'));

Assert::same(array("SELECT 'string'", array()), $connection->preprocess('SELECT ?', 'string'));
