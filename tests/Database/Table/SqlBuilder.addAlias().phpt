<?php

/**
 * Test: Nette\Database\Table\SqlBuilder: addAlias().
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;
use Nette\Database\ISupplementalDriver;
use Nette\Database\Table\SqlBuilder;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

class SqlBuilderMock extends SqlBuilder
{
	public function parseJoins(&$joins, &$query, $inner = FALSE): void
	{
		parent::parseJoins($joins, $query);
	}
	public function buildQueryJoins(array $joins, array $leftJoinConditions = []): string
	{
		return parent::buildQueryJoins($joins, $leftJoinConditions);
	}
}

$driver = $connection->getSupplementalDriver();


test(function() use ($context, $driver) { // test duplicated table names throw exception
	$authorTable = ($driver->isSupported(ISupplementalDriver::SUPPORT_SCHEMA) ? 'public.' : '' ) . 'author';
	$sqlBuilder = new SqlBuilderMock($authorTable, $context);
	$sqlBuilder->addAlias(':book(translator)', 'book1');
	$sqlBuilder->addAlias(':book:book_tag', 'book2');
	Assert::exception(function() use ($sqlBuilder) {
		$sqlBuilder->addAlias(':book', 'book1');
	}, Nette\InvalidArgumentException::class, "Table alias 'book1' from chain ':book' is already in use by chain ':book(translator)'. Please add/change alias for one of them.");

	Assert::exception(function() use ($sqlBuilder) { // reserved by base table name
		$sqlBuilder->addAlias(':book', 'author');
	}, Nette\InvalidArgumentException::class, "Table alias 'author' from chain ':book' is already in use by chain '$authorTable'. Please add/change alias for one of them.");

	Assert::exception(function() use ($sqlBuilder) {
		$sqlBuilder->addAlias(':book', 'book1');
	}, Nette\InvalidArgumentException::class, "Table alias 'book1' from chain ':book' is already in use by chain ':book(translator)'. Please add/change alias for one of them.");

	$sqlBuilder->addAlias(':book', 'tag');
	Assert::exception(function() use ($sqlBuilder) {
		$query = 'WHERE book1:book_tag.tag.id IS NULL';
		$joins = [];
		$sqlBuilder->parseJoins($joins, $query);
	}, Nette\InvalidArgumentException::class, "Table alias 'tag' from chain '.book1:book_tag.tag' is already in use by chain ':book'. Please add/change alias for one of them.");

	Assert::exception(function() use ($sqlBuilder) {
		$query = 'WHERE :book(translator).id IS NULL AND :book.id IS NULL';
		$joins = [];
		$sqlBuilder->parseJoins($joins, $query);
	}, Nette\InvalidArgumentException::class, "Table alias 'book' from chain ':book' is already in use by chain ':book(translator)'. Please add/change alias for one of them.");
});


test(function() use ($context, $driver) { // test same table chain with another alias
	$sqlBuilder = new SqlBuilderMock('author', $context);
	$sqlBuilder->addAlias(':book(translator)', 'translated_book');
	$sqlBuilder->addAlias(':book(translator)', 'translated_book2');
	$query = 'WHERE translated_book.translator_id IS NULL AND translated_book2.id IS NULL';
	$joins = [];
	$sqlBuilder->parseJoins($joins, $query);
	$join = $sqlBuilder->buildQueryJoins($joins);

	Assert::same(
		'LEFT JOIN book translated_book ON author.id = translated_book.translator_id ' .
		'LEFT JOIN book translated_book2 ON author.id = translated_book2.translator_id',
		trim($join)
	);
});


test(function() use ($context, $driver) { // test nested alias
	if ($driver->isSupported(ISupplementalDriver::SUPPORT_SCHEMA)) {
		$sqlBuilder = new SqlBuilderMock('public.author', $context);
	} else {
		$sqlBuilder = new SqlBuilderMock('author', $context);
	}
	$sqlBuilder->addAlias(':book(translator)', 'translated_book');
	$sqlBuilder->addAlias('translated_book.next_volume', 'next');
	$query = 'WHERE next.translator_id IS NULL';
	$joins = [];
	$sqlBuilder->parseJoins($joins, $query);
	$join = $sqlBuilder->buildQueryJoins($joins);
	if ($driver->isSupported(ISupplementalDriver::SUPPORT_SCHEMA)) {
		Assert::same(
			'LEFT JOIN book translated_book ON author.id = translated_book.translator_id ' .
			'LEFT JOIN public.book next ON translated_book.next_volume = next.id',
			trim($join)
		);

	} else {
		Assert::same(
			'LEFT JOIN book translated_book ON author.id = translated_book.translator_id ' .
			'LEFT JOIN book next ON translated_book.next_volume = next.id',
			trim($join)
		);
	}
});
