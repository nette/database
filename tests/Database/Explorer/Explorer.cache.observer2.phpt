<?php

/**
 * Test: Nette\Database\Table: Cache observer.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


class CacheMock extends Cache
{
	public int $writes = 0;


	public function save(mixed $key, mixed $data, ?array $dependencies = null): mixed
	{
		$this->writes++;
		return parent::save($key, $data, $dependencies);
	}
}

$cache = new CacheMock(new MemoryStorage);
$explorer->setCache($cache);

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

Assert::same(reformat('SELECT [id], [name] FROM [author]'), $sql);
Assert::same(3, $cache->writes); // Structure + 2x Selection
