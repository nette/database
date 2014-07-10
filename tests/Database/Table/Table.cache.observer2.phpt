<?php

/**
 * Test: Nette\Database\Table: Cache observer.
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;
use Nette\Caching\Storages\MemoryStorage;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


class CacheMock extends MemoryStorage
{
	public $writes = 0;

	function write($key, $data, array $dependencies)
	{
		$this->writes++;
		return parent::write($key, $data, $dependencies);
	}
}

$cacheStorage = new CacheMock;
$context = new Nette\Database\Context($connection, $structure, $conventions, $cacheStorage);

for ($i = 0; $i < 2; $i += 1) {
	$authors = $context->table('author');
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
