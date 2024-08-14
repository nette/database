<?php

/**
 * Test: Nette\Database\Helpers::loadFromFile().
 * @dataProvider? databases.ini  mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . '/files/mysql-delimiter.sql');

$arr = $connection->query('SELECT name, id FROM author ORDER BY id')->fetchAll();
Assert::equal([
	Nette\Database\Row::from(['name' => 'Jakub Vrana', 'id' => 11]),
	Nette\Database\Row::from(['name' => 'David Grudl', 'id' => 12]),
], $arr);
