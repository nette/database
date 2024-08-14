<?php

/**
 * Test: Nette\Database\Table: Update operations
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


$author = $explorer->table('author')->get(12);  // SELECT * FROM `author` WHERE (`id` = ?)
$author->update([
	'name' => 'Tyrion Lannister',
]);  // UPDATE `author` SET `name`='Tyrion Lannister' WHERE (`id` = 12)

$book = $explorer->table('book');

$book1 = $book->get(1);  // SELECT * FROM `book` WHERE (`id` = ?)
Assert::same('Jakub Vrana', $book1->author->name);  // SELECT * FROM `author` WHERE (`author`.`id` IN (11))


$book2 = $book->insert([
	'author_id' => $author->getPrimary(),
	'title' => 'Game of Thrones',
]);  // INSERT INTO `book` (`author_id`, `title`) VALUES (12, 'Game of Thrones')

Assert::same('Tyrion Lannister', $book2->author->name);  // SELECT * FROM `author` WHERE (`author`.`id` IN (12))


$book2->update([
	'author_id' => $explorer->table('author')->get(12),  // SELECT * FROM `author` WHERE (`id` = ?)
]);  // UPDATE `book` SET `author_id`=11 WHERE (`id` = '5')

Assert::same('Tyrion Lannister', $book2->author->name);  // NO SQL, SHOULD BE CACHED

$book2->update([
	'author_id' => $explorer->table('author')->get(11),  // SELECT * FROM `author` WHERE (`id` = ?)
]);  // UPDATE `book` SET `author_id`=11 WHERE (`id` = '5')

Assert::same('Jakub Vrana', $book2->author->name);  // SELECT * FROM `author` WHERE (`author`.`id` IN (11))

$book2->update([
	'author_id' => new Nette\Database\SqlLiteral('10 + 3'),
]);  // UPDATE `book` SET `author_id`=13 WHERE (`id` = '5')

Assert::same('Geek', $book2->author->name);  // SELECT * FROM `author` WHERE (`author`.`id` IN (13))
Assert::same(13, $book2->author_id);


$tag = $explorer->table('tag')->insert([
	'name' => 'PC Game',
]);  // INSERT INTO `tag` (`name`) VALUES ('PC Game')

$tag->update([
	'name' => 'Xbox Game',
]);  // UPDATE `tag` SET `name`='Xbox Game' WHERE (`id` = '24')


$bookTag = $book2->related('book_tag')->insert([
	'tag_id' => $tag,
]);  // INSERT INTO `book_tag` (`tag_id`, `book_id`) VALUES (24, '5')


$app = $explorer->table('book')->get(5);  // SELECT * FROM `book` WHERE (`id` = ?)
$tags = iterator_to_array($app->related('book_tag'));  // SELECT * FROM `book_tag` WHERE (`book_tag`.`book_id` IN (5))
Assert::same('Xbox Game', reset($tags)->tag->name);  // SELECT * FROM `tag` WHERE (`tag`.`id` IN (24))


$tag2 = $explorer->table('tag')->insert([
	'name' => 'PS4 Game',
]);  // INSERT INTO `tag` (`name`) VALUES ('PS4 Game')

// SQL Server throw PDOException because does not allow to update identity column
if ($driverName !== 'sqlsrv') {
	$tag2->update([
		'id' => 1,
	]);  // UPDATE `tag` SET `id`=1 WHERE (`id` = (?))
	Assert::same(1, $tag2->id);
}


$book_tag = $explorer->table('book_tag')->get([
	'book_id' => 5,
	'tag_id' => 25,
]);  // SELECT * FROM `book_tag` WHERE (`book_id` = (?) AND `tag_id` = (?))
$book_tag->update(new ArrayIterator([
	'tag_id' => 21,
]));  // UPDATE `book_tag` SET `tag_id`=21 WHERE (`book_id` = (?) AND `tag_id` = (?))
Assert::same(21, $book_tag->tag_id);
