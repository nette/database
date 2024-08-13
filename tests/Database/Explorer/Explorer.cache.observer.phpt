<?php

/**
 * Test: Nette\Database\Table: Cache observer.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Nette\Database\Result;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


$cache = Mockery::mock(Nette\Caching\Cache::class);
$cache->shouldReceive('load')->withAnyArgs()->once()->andReturn(['id' => true]);
$cache->shouldReceive('load')->withAnyArgs()->times(4)->andReturn(['id' => true, 'author_id' => true]);
$cache->shouldReceive('save')->with(Mockery::any(), ['id' => true, 'author_id' => true, 'title' => true]);

$explorer = new Nette\Database\Explorer($connection, $explorer->getStructure(), $explorer->getConventions(), $cache);

$queries = 0;
$connection->onQuery[] = function ($dao, Result $result) use (&$queries) {
	if (!preg_match('#SHOW|CONSTRAINT_NAME|pg_catalog|sys\.|SET|PRAGMA|FROM sqlite_#i', $result->getQuery()->getSql())) {
		$queries++;
	}
};

$authors = $explorer->table('author');
$stack = [];
foreach ($authors as $author) {
	foreach ($stack[] = $author->related('book') as $book) {
		$book->title;
	}
}

unset($book, $author);
foreach ($stack as $selection) {
	$selection->__destruct();
}

$authors->__destruct();

Assert::same(3, $queries);
Mockery::close();
