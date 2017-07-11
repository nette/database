<?php

/**
 * Test: Nette\Database\Connection preprocess.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection


Assert::same(['SELECT name FROM author', []], $connection->preprocess('SELECT name FROM author'));

Assert::same(['SELECT ?', ['string']], $connection->preprocess('SELECT ?', 'string'));
