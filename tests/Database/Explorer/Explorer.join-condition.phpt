<?php

/**
 * Test: Nette\Database\Table: Additional join condition
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Nette\Database\Drivers\Engine;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

Nette\Database\Helpers::loadFromFile($explorer, __DIR__ . "/../files/{$driverName}-nette_test1.sql");
$engine = $explorer->getDatabaseEngine();

test('', function () use ($explorer, $engine) {
	$schema = $engine->isSupported(Engine::SupportSchema)
		? '[public].'
		: '';
	$sql = $explorer->table('book')->joinWhere('translator', 'translator.name', 'Geek')->select('book.*')->getSql();

	Assert::same(reformat(
		'SELECT [book].* FROM [book] ' .
		"LEFT JOIN {$schema}[author] [translator] ON [book].[translator_id] = [translator].[id] AND ([translator].[name] = ?)",
	), $sql);
});

test('', function () use ($explorer, $engine) {
	$sql = $explorer->table('tag')
		->select('tag.name, COUNT(:book_tag.book.id) AS count_of_next_volume_written_by_younger_author')
		->joinWhere(':book_tag.book.author', ':book_tag.book.author.born < next_volume_author.born')
		->alias(':book_tag.book.next_volume.author', 'next_volume_author')
		->where('tag.name', 'PHP')
		->group('tag.name')
		->getSql();
	if ($engine->isSupported(Engine::SupportSchema)) {
		Assert::same(
			reformat(
				'SELECT [tag].[name], COUNT([book].[id]) AS [count_of_next_volume_written_by_younger_author] FROM [tag] ' .
				'LEFT JOIN [public].[book_tag] [book_tag] ON [tag].[id] = [book_tag].[tag_id] ' .
				'LEFT JOIN [public].[book] [book] ON [book_tag].[book_id] = [book].[id] ' .
				'LEFT JOIN [public].[book] [book_ref] ON [book].[next_volume] = [book_ref].[id] ' .
				'LEFT JOIN [public].[author] [next_volume_author] ON [book_ref].[author_id] = [next_volume_author].[id] ' .
				'LEFT JOIN [public].[author] [author] ON [book].[author_id] = [author].[id] AND ([author].[born] < [next_volume_author].[born]) ' .
				'WHERE ([tag].[name] = ?) ' .
				'GROUP BY [tag].[name]',
			),
			$sql,
		);
	} else {
		Assert::same(
			reformat(
				'SELECT [tag].[name], COUNT([book].[id]) AS [count_of_next_volume_written_by_younger_author] FROM [tag] ' .
				'LEFT JOIN [book_tag] ON [tag].[id] = [book_tag].[tag_id] ' .
				'LEFT JOIN [book] ON [book_tag].[book_id] = [book].[id] ' .
				'LEFT JOIN [book] [book_ref] ON [book].[next_volume] = [book_ref].[id] ' .
				'LEFT JOIN [author] [next_volume_author] ON [book_ref].[author_id] = [next_volume_author].[id] ' .
				'LEFT JOIN [author] ON [book].[author_id] = [author].[id] AND ([author].[born] < [next_volume_author].[born]) ' .
				'WHERE ([tag].[name] = ?) ' .
				'GROUP BY [tag].[name]',
			),
			$sql,
		);
	}
});
