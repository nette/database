<?php

/**
 * Test: Nette\Database\Table\Selection: Insert operations
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


$book = $explorer->table('author')->insert([
	'name' => $explorer->literal('LOWER(?)', 'Eddard Stark'),
	'web' => 'http://example.com',
	'born' => new DateTime('2011-11-11'),
]);  // INSERT INTO `author` (`name`, `web`) VALUES (LOWER('Eddard Stark'), 'http://example.com', '2011-11-11 00:00:00')
// id = 14

Assert::same('eddard stark', $book->name);
Assert::equal(new Nette\Database\DateTime('2011-11-11'), $book->born);


$books = $explorer->table('book');

$book1 = $books->get(1);  // SELECT * FROM `book` WHERE (`id` = ?)
Assert::same('Jakub Vrana', $book1->author->name);  // SELECT * FROM `author` WHERE (`author`.`id` IN (11))

$book2 = $books->insert([
	'title' => 'Dragonstone',
	'author_id' => $explorer->table('author')->get(14),  // SELECT * FROM `author` WHERE (`id` = ?)
]);  // INSERT INTO `book` (`title`, `author_id`) VALUES ('Dragonstone', 14)

Assert::same('eddard stark', $book2->author->name);  // SELECT * FROM `author` WHERE (`author`.`id` IN (11, 15))


// SQL Server throw exception because does not allow insert explicit value for IDENTITY column.
// This exception is about primary key violation.
if ($driverName !== 'sqlsrv') {
	Assert::exception(
		fn() => $explorer->table('author')->insert([
			'id' => 14,
			'name' => 'Jon Snow',
			'web' => 'http://example.com',
		]),
		Nette\Database\UniqueConstraintViolationException::class,
	);
}


// SQL Server 2008 doesn't know CONCAT()
if ($driverName !== 'sqlsrv') {
	$selection = match ($driverName) {
		'mysql' => $explorer->table('author')->select('NULL, id, NULL, CONCAT(?, name), NULL', 'Biography: '),
		'pgsql' => $explorer->table('author')->select('nextval(?), id, NULL, ? || name, NULL', 'book_id_seq', 'Biography: '),
		'sqlite' => $explorer->table('author')->select('NULL, id, NULL, ? || name, NULL', 'Biography: '),
		//'sqlsrv' => $explorer->table('author')->select('id, NULL, CONCAT(?, name), NULL', 'Biography: '),
		default => Assert::fail("Unsupported driver $driverName"),
	};

	$explorer->table('book')->insert($selection);
	Assert::same(4, $explorer->table('book')->where('title LIKE', 'Biography%')->count('*'));
}


// Insert into table without primary key
$inserted = $explorer->table('note')->insert([
	'book_id' => 1,
	'note' => 'Good one!',
]);
Assert::same(1, $inserted);
