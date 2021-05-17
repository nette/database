<?php

/**
 * Test: Nette\Database\Table: Cache observer.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Nette\Caching\Storages\MemoryStorage;
use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


class CacheMock extends MemoryStorage
{
	public $writes = 0;


	public function write(string $key, $data, array $dependencies): void
	{
		$this->writes++;
		parent::write($key, $data, $dependencies);
	}
}

$cacheStorage = new CacheMock;
$explorer = new Nette\Database\Explorer($connection, $structure, $conventions, $cacheStorage);

for ($i = 0; $i < 2; ++$i) {
	$authors = $explorer->table('author');
	foreach ($authors as $author) {
		$author->name;
	}

	if ($i === 0) {
		$authors->where('web IS NOT NULL');
		foreach ($authors as $author) {
			$author->web;
		}
		$authors->__destruct();
	} else {
		$sql = $authors->getSql();
	}
}

Assert::equal(reformat('SELECT [id], [name] FROM [author]'), $sql);
Assert::same(2, $cacheStorage->writes);
