<?php

/**
 * Test: Nette\Database\Table: Join.
 * @dataProvider? ../databases.ini
 */

use Nette\Database\ISupplementalDriver;
use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");
$driver = $connection->getSupplementalDriver();


test(function () use ($context) {
	$apps = [];
	foreach ($context->table('book')->order('author.name, title') as $book) {  // SELECT `book`.* FROM `book` LEFT JOIN `author` ON `book`.`author_id` = `author`.`id` ORDER BY `author`.`name`, `title`
		$apps[$book->title] = $book->author->name;  // SELECT * FROM `author` WHERE (`author`.`id` IN (12, 11))
	}

	Assert::same([
		'Dibi' => 'David Grudl',
		'Nette' => 'David Grudl',
		'1001 tipu a triku pro PHP' => 'Jakub Vrana',
		'JUSH' => 'Jakub Vrana',
	], $apps);
});


test(function () use ($context, $driver) {
	$joinSql = $context->table('book_tag')->where('book_id', 1)->select('tag.*')->getSql();

	if ($driver->isSupported(ISupplementalDriver::SUPPORT_SCHEMA)) {
		Assert::same(
			reformat('SELECT [tag].* FROM [book_tag] LEFT JOIN [public].[tag] [tag] ON [book_tag].[tag_id] = [tag].[id] WHERE ([book_id] = ?)'),
			$joinSql
		);
	} else {
		Assert::same(
			reformat('SELECT [tag].* FROM [book_tag] LEFT JOIN [tag] ON [book_tag].[tag_id] = [tag].[id] WHERE ([book_id] = ?)'),
			$joinSql
		);
	}
});


test(function () use ($context, $driver) {
	$joinSql = $context->table('book_tag')->where('book_id', 1)->select('Tag.id')->getSql();

	if ($driver->isSupported(ISupplementalDriver::SUPPORT_SCHEMA)) {
		Assert::same(
			reformat('SELECT [Tag].[id] FROM [book_tag] LEFT JOIN [public].[tag] [Tag] ON [book_tag].[tag_id] = [Tag].[id] WHERE ([book_id] = ?)'),
			$joinSql
		);
	} else {
		Assert::same(
			reformat('SELECT [Tag].[id] FROM [book_tag] LEFT JOIN [tag] [Tag] ON [book_tag].[tag_id] = [Tag].[id] WHERE ([book_id] = ?)'),
			$joinSql
		);
	}
});


test(function () use ($context) {
	$tags = [];
	foreach ($context->table('book_tag')->where('book.author.name', 'Jakub Vrana')->group('book_tag.tag_id')->order('book_tag.tag_id') as $book_tag) {  // SELECT `book_tag`.* FROM `book_tag` INNER JOIN `book` ON `book_tag`.`book_id` = `book`.`id` INNER JOIN `author` ON `book`.`author_id` = `author`.`id` WHERE (`author`.`name` = ?) GROUP BY `book_tag`.`tag_id`
		$tags[] = $book_tag->tag->name;  // SELECT * FROM `tag` WHERE (`tag`.`id` IN (21, 22, 23))
	}

	Assert::same([
		'PHP',
		'MySQL',
		'JavaScript',
	], $tags);
});


test(function () use ($context) {
	Assert::same(2, $context->table('author')->where('author_id', 11)->count(':book.id')); // SELECT COUNT(book.id) FROM `author` LEFT JOIN `book` ON `author`.`id` = `book`.`author_id` WHERE (`author_id` = 11)
});


test(function () use ($connection, $structure, $driverName) {
	$context = new Nette\Database\Context(
		$connection,
		$structure,
		new Nette\Database\Conventions\DiscoveredConventions($structure)
	);

	$books = $context->table('book')->select('book.*, author.name, translator.name');
	Assert::error(function() use($books) {
		iterator_to_array($books);
	}, E_USER_NOTICE);
});
