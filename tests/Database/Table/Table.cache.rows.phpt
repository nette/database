<?php

/**
 * Test: Nette\Database\Table: Rows invalidating.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


$selections = [];
foreach ($selections[] = $context->table('book') as $book) {
	$book->author->name;
	$selections[] = $book->author->getTable();
}
foreach ($selections as $selection) {
	$selection->__destruct();
}

$authors = [];
foreach ($context->table('book') as $book) {
	$authors[] = $book->author;
}

$webs = [];
foreach ($authors as $author) {
	$webs[$author->web] = NULL;
}
ksort($webs);
Assert::same([
	'http://davidgrudl.com/',
	'http://www.vrana.cz/',
], array_keys($webs));


$bookSelection = $context->table('book')->order('id');
$book = $bookSelection->fetch();
$book->author_id;
$bookSelection->__destruct();

$bookSelection = $context->table('book')->order('id');
$books = [];
$books[] = $bookSelection->fetch();
$books[] = $bookSelection->fetch()->toArray();
$books[] = $bookSelection->fetch()->toArray();
Assert::same(1, $books[0]['id']);
Assert::same(2, $books[1]['id']);
Assert::same(3, $books[2]['id']);


$row = $context->table('author')->insert([
	'name' => 'Eddard Stark',
	'web' => 'http://example.com',
]);  // INSERT INTO `author` (`name`, `web`) VALUES ('Eddard Stark', 'http://example.com')
Assert::true(is_array($row->toArray()));
// id = 14


$row = $context->table('author')->where('id', 14)->fetch();
Assert::true(is_array($row->toArray()));
