<?php

/**
 * Test: Nette\Database\Table: Basic operations.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$book = $explorer->table('book')->where('id = ?', 1)->select('id, title')->fetch()->toArray();  // SELECT `id`, `title` FROM `book` WHERE (`id` = ?)
	Assert::same([
		'id' => 1,
		'title' => '1001 tipu a triku pro PHP',
	], $book);
});


test('', function () use ($explorer) {
	$book = $explorer->table('book')->select('id, title')->where('id = ?', 1)->fetch()->toArray();  // SELECT `id`, `title` FROM `book` WHERE (`id` = ?)
	Assert::same([
		'id' => 1,
		'title' => '1001 tipu a triku pro PHP',
	], $book);
});


test('', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	Assert::exception(
		fn() => $book->unknown_column,
		Nette\MemberAccessException::class,
		"Cannot read an undeclared column 'unknown_column'.",
	);
});


test('', function () use ($explorer) {
	$bookTags = [];
	foreach ($explorer->table('book') as $book) {  // SELECT * FROM `book`
		$bookTags[$book->title] = [
			'author' => $book->author->name,  // SELECT * FROM `author` WHERE (`author`.`id` IN (11, 12))
			'tags' => [],
		];

		foreach ($book->related('book_tag') as $book_tag) {  // SELECT * FROM `book_tag` WHERE (`book_tag`.`book_id` IN (1, 2, 3, 4))
			$bookTags[$book->title]['tags'][] = $book_tag->tag->name;  // SELECT * FROM `tag` WHERE (`tag`.`id` IN (21, 22, 23))
		}
	}

	Assert::same([
		'1001 tipu a triku pro PHP' => [
			'author' => 'Jakub Vrana',
			'tags' => ['PHP', 'MySQL'],
		],
		'JUSH' => [
			'author' => 'Jakub Vrana',
			'tags' => ['JavaScript'],
		],
		'Nette' => [
			'author' => 'David Grudl',
			'tags' => ['PHP'],
		],
		'Dibi' => [
			'author' => 'David Grudl',
			'tags' => ['PHP', 'MySQL'],
		],
	], $bookTags);
});


test('', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	Assert::exception(
		fn() => $book->test,
		Nette\MemberAccessException::class,
		"Cannot read an undeclared column 'test'.",
	);

	Assert::exception(
		fn() => $book->tilte,
		Nette\MemberAccessException::class,
		"Cannot read an undeclared column 'tilte', did you mean 'title'?",
	);

	Assert::exception(
		fn() => $book->ref('test'),
		Nette\MemberAccessException::class,
		'No reference found for $book->ref(test).',
	);

	Assert::exception(
		fn() => $book->related('test'),
		Nette\MemberAccessException::class,
		'No reference found for $book->related(test).',
	);
});
