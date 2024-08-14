<?php

/**
 * Test: Nette\Database\Table: Refetching rows with all columns
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


$books = $explorer->table('book')->order('id DESC')->limit(2);
foreach ($books as $book) {
	$book->title;
}

$books->__destruct();

$res = [];
$books = $explorer->table('book')->order('id DESC')->limit(2);
foreach ($books as $book) {
	$res[] = (string) $book->title;
}

Assert::same(['Dibi', 'Nette'], $res);

$explorer->table('book')->insert([
	'title' => 'New book #1',
	'author_id' => 11,
]);
$explorer->table('book')->insert([
	'title' => 'New book #2',
	'author_id' => 11,
]);

$res = [];
foreach ($books as $book) {
	$res[] = (string) $book->title;
	$res[] = (string) $book->author->name;
}

Assert::same(['Dibi', 'David Grudl', 'Nette', 'David Grudl'], $res);
