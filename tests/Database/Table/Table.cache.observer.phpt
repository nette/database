<?php

/**
 * Test: Nette\Database\Table: Cache observer.
 * @dataProvider? ../databases.ini
 */

use Nette\Database\ResultSet;
use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


$cacheStorage = Mockery::mock(Nette\Caching\Istorage::class);
$cacheStorage->shouldReceive('read')->withAnyArgs()->once()->andReturn(['id' => TRUE]);
$cacheStorage->shouldReceive('read')->withAnyArgs()->times(4)->andReturn(['id' => TRUE, 'author_id' => TRUE]);
$cacheStorage->shouldReceive('write')->with(Mockery::any(), ['id' => TRUE, 'author_id' => TRUE, 'title' => TRUE], []);

$context = new Nette\Database\Context($connection, $structure, $conventions, $cacheStorage);

$queries = 0;
$connection->onQuery[] = function ($dao, ResultSet $result) use (&$queries) {
	if (!preg_match('#SHOW|CONSTRAINT_NAME|pg_catalog|sys\.|SET|PRAGMA|FROM sqlite_#i', $result->getQueryString())) {
		$queries++;
	}
};

$authors = $context->table('author');
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
