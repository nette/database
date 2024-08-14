<?php

/**
 * Test: bug #216
 * @dataProvider? ../../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");

$book = $explorer->table('author')->insert([
	'name' => $explorer->literal('LOWER(?)', 'Eddard Stark'),
	'web' => 'http://example.com',
	'born' => new DateTime('2011-11-11'),
]);  // INSERT INTO `author` (`name`, `web`) VALUES (LOWER('Eddard Stark'), 'http://example.com', '2011-11-11 00:00:00')
// id = 14

Assert::type(Nette\Database\Table\ActiveRow::class, $book);
Assert::equal('eddard stark', $book->name);
Assert::equal(new Nette\Database\DateTime('2011-11-11'), $book->born);
