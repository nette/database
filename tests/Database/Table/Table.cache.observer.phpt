<?php

/**
 * Test: Nette\Database\Table: Cache observer.
 * @dataProvider? ../databases.ini
 */

use Nette\Caching\Storages\MemoryStorage;
use Tester\Assert;
use Nette\Database\ResultSet;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


class CacheMock extends MemoryStorage
{

}

$cacheStorage = new CacheMock;
$context = new Nette\Database\Context($connection, $structure, $conventions, $cacheStorage);

$queries = 0;
$connection->onQuery[] = function($dao, ResultSet $result) use (& $queries) {
	if (!preg_match('#SHOW|CONSTRAINT_NAME|pg_catalog|sys\.|SET|PRAGMA|FROM sqlite_#i', $result->getQueryString())) {
		$queries++;
	}
};

$authors = $context->table('author');
$stack = array();
foreach ($authors as $author) {
	foreach ($stack[] = $author->related('book') as $book) {
		$book->title;
	}
}

unset($book, $author);
foreach ($stack as $selection) $selection->__destruct();
$authors->__destruct();

Assert::same(2, $queries);
Mockery::close();
