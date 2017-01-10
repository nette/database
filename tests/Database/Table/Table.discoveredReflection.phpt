<?php

/**
 * Test: Nette\Database\Table: Basic operations with DiscoveredReflection.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test(function () use ($context) {
	$appTags = [];
	foreach ($context->table('book') as $book) {
		$appTags[$book->title] = [
			'author' => $book->author->name,
			'tags' => [],
		];

		foreach ($book->related('book_tag') as $book_tag) {
			$appTags[$book->title]['tags'][] = $book_tag->tag->name;
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
	], $appTags);
});


test(function () use ($context) {
	$books = [];
	foreach ($context->table('author') as $author) {
		foreach ($author->related('book') as $book) {
			$books[$book->title] = $author->name;
		}
	}

	Assert::same([
		'1001 tipu a triku pro PHP' => 'Jakub Vrana',
		'JUSH' => 'Jakub Vrana',
		'Nette' => 'David Grudl',
		'Dibi' => 'David Grudl',
	], $books);
});


test(function () use ($context) {
	$book = $context->table('book')->get(1);
	Assert::same('Jakub Vrana', $book->translator->name);
});


test(function () use ($context) {
	$book = $context->table('book')->get(2);
	Assert::true(isset($book->author_id));
	Assert::false(empty($book->author_id));

	Assert::false(isset($book->translator_id));
	Assert::true(empty($book->translator_id));
	Assert::false(isset($book->test));

	Assert::false(isset($book->author));
	Assert::false(isset($book->translator));
	Assert::true(empty($book->author));
	Assert::true(empty($book->translator));
});


test(function () use ($connection, $context) {
	if (
		$connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' &&
		($lowerCase = $connection->query('SHOW VARIABLES LIKE "lower_case_table_names"')->fetch()) &&
		$lowerCase->Value != 0
	) {
		// tests case-insensitive reflection
		$books = [];
		foreach ($context->table('Author') as $author) {
			foreach ($author->related('book') as $book) {
				$books[$book->title] = $author->name;
			}
		}

		Assert::same([
			'1001 tipu a triku pro PHP' => 'Jakub Vrana',
			'JUSH' => 'Jakub Vrana',
			'Nette' => 'David Grudl',
			'Dibi' => 'David Grudl',
		], $books);
	}
});


test(function () use ($context) {
	$count = $context->table('book')->where('translator.name LIKE ?', '%David%')->count();
	Assert::same(2, $count);
	$count = $context->table('book')->where('author.name LIKE ?', '%David%')->count();
	Assert::same(2, $count);
});
