<?php

/**
 * Test: Nette\Database\Table\SqlBuilder: parseJoins().
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Nette\Database\Conventions\DiscoveredConventions;
use Nette\Database\Driver;
use Nette\Database\Table\SqlBuilder;
use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test2.sql");


class SqlBuilderMock extends SqlBuilder
{
	public function parseJoins(&$joins, &$query, $inner = false): void
	{
		parent::parseJoins($joins, $query);
	}


	public function buildQueryJoins(array $joins, array $leftJoinConditions = []): string
	{
		return parent::buildQueryJoins($joins, $leftJoinConditions);
	}
}

$conventions = new DiscoveredConventions($structure);
$sqlBuilder = new SqlBuilderMock('nUsers', $explorer);
$driver = $connection->getDriver();


$joins = [];
$query = 'WHERE :nusers_ntopics.topic.priorit.id IS NULL';
$sqlBuilder->parseJoins($joins, $query);
$join = $sqlBuilder->buildQueryJoins($joins);
Assert::same('WHERE priorit.id IS NULL', $query);

$tables = $connection->getDriver()->getTables();
if (!in_array($tables[0]['name'], ['npriorities', 'ntopics', 'nusers', 'nusers_ntopics', 'nusers_ntopics_alt'], true)) {
	if ($driver->isSupported(Driver::SupportSchema)) {
		Assert::same(
			'LEFT JOIN public.nUsers_nTopics nusers_ntopics ON nUsers.nUserId = nusers_ntopics.nUserId ' .
			'LEFT JOIN public.nTopics topic ON nusers_ntopics.nTopicId = topic.nTopicId ' .
			'LEFT JOIN public.nPriorities priorit ON topic.nPriorityId = priorit.nPriorityId',
			trim($join),
		);

	} else {
		Assert::same(
			'LEFT JOIN nUsers_nTopics nusers_ntopics ON nUsers.nUserId = nusers_ntopics.nUserId ' .
			'LEFT JOIN nTopics topic ON nusers_ntopics.nTopicId = topic.nTopicId ' .
			'LEFT JOIN nPriorities priorit ON topic.nPriorityId = priorit.nPriorityId',
			trim($join),
		);
	}

} else {
	Assert::same(
		'LEFT JOIN nusers_ntopics ON nUsers.nUserId = nusers_ntopics.nUserId ' .
		'LEFT JOIN ntopics topic ON nusers_ntopics.nTopicId = topic.nTopicId ' .
		'LEFT JOIN npriorities priorit ON topic.nPriorityId = priorit.nPriorityId',
		trim($join),
	);
}


Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");
$structure->rebuild();

$sqlBuilder = new SqlBuilderMock('author', $explorer);

$joins = [];
$query = 'WHERE :book(translator).next_volume IS NULL';
$sqlBuilder->parseJoins($joins, $query);
$join = $sqlBuilder->buildQueryJoins($joins);
Assert::same('WHERE book.next_volume IS NULL', $query);
Assert::same(
	'LEFT JOIN book ON author.id = book.translator_id',
	trim($join),
);


$sqlBuilder = $driver->isSupported(Driver::SupportSchema)
	? new SqlBuilderMock('public.book', $explorer)
	: new SqlBuilderMock('book', $explorer);

$joins = [];
$query = 'WHERE :book.translator_id IS NULL AND :book:book.translator_id IS NULL';
$sqlBuilder->parseJoins($joins, $query);
$join = $sqlBuilder->buildQueryJoins($joins);
Assert::same('WHERE book_ref.translator_id IS NULL AND book_ref_ref.translator_id IS NULL', $query);

if ($driver->isSupported(Driver::SupportSchema)) {
	Assert::same(
		'LEFT JOIN public.book book_ref ON book.id = book_ref.next_volume ' .
		'LEFT JOIN public.book book_ref_ref ON book_ref.id = book_ref_ref.next_volume',
		trim($join),
	);
} else {
	Assert::same(
		'LEFT JOIN book book_ref ON book.id = book_ref.next_volume ' .
		'LEFT JOIN book book_ref_ref ON book_ref.id = book_ref_ref.next_volume',
		trim($join),
	);
}
