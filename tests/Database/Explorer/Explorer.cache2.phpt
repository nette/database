<?php

/**
 * Test: Nette\Database\Table: Special case of caching
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

$res = [];

for ($i = 1; $i <= 2; ++$i) {
	foreach ($explorer->table('author') as $author) {
		$res[] = (string) $author->name;
		foreach ($author->related('book', 'author_id') as $book) {
			$res[] = (string) $book->title;
		}
	}

	foreach ($explorer->table('author')->where('id', 13) as $author) {
		$res[] = (string) $author->name;
		foreach ($author->related('book', 'author_id') as $book) {
			$res[] = (string) $book->title;
		}
	}
}

Assert::same([
	'Jakub Vrana',
	'1001 tipu a triku pro PHP',
	'JUSH',
	'David Grudl',
	'Nette',
	'Dibi',
	'Geek',
	'Geek',
	'Jakub Vrana',
	'1001 tipu a triku pro PHP',
	'JUSH',
	'David Grudl',
	'Nette',
	'Dibi',
	'Geek',
	'Geek',
], $res);
