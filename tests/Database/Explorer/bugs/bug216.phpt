<?php

/**
 * Test: bug #216
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

//Prepare connection
$options = Tester\Environment::loadData() + ['user' => null, 'password' => null];

try {
	$connection = new Nette\Database\Connection($options['dsn'], $options['user'], $options['password']);
} catch (PDOException $e) {
	Tester\Environment::skip("Connection to '$options[dsn]' failed. Reason: " . $e->getMessage());
}

if (strpos($options['dsn'], 'sqlite::memory:') === false) {
	Tester\Environment::lock($options['dsn'], TEMP_DIR);
}

$driverName = $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
$cacheMemoryStorage = new Nette\Caching\Storages\MemoryStorage;

$structure = new Nette\Database\Structure($connection, $cacheMemoryStorage);
$conventions = new Nette\Database\Conventions\StaticConventions;
$explorer = new Nette\Database\Explorer($connection, $structure, $conventions, $cacheMemoryStorage);

//Testing
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

$book = $explorer->table('author')->insert([
	'name' => $explorer->literal('LOWER(?)', 'Eddard Stark'),
	'web' => 'http://example.com',
	'born' => new \DateTime('2011-11-11'),
]);  // INSERT INTO `author` (`name`, `web`) VALUES (LOWER('Eddard Stark'), 'http://example.com', '2011-11-11 00:00:00')
// id = 14

Assert::type(Nette\Database\Table\ActiveRow::class, $book);
Assert::equal('eddard stark', $book->name);
Assert::equal(new Nette\Utils\DateTime('2011-11-11'), $book->born);
