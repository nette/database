<?php declare(strict_types=1);

/**
 * PHPStan type tests for Database.
 * Run: vendor/bin/phpstan analyse tests/types
 */

use Nette\Database\Helpers;
use Nette\Database\ResultSet;
use Nette\Database\Row;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use function PHPStan\Testing\assertType;


function testResultSetIterator(ResultSet $resultSet): void
{
	foreach ($resultSet as $key => $row) {
		assertType('int', $key);
		assertType(Row::class, $row);
	}
}


function testActiveRowIterator(ActiveRow $activeRow): void
{
	foreach ($activeRow as $key => $value) {
		assertType('string', $key);
		assertType('mixed', $value);
	}
}


/**
 * @param Selection<ActiveRow> $selection
 */
function testSelectionIterator(Selection $selection): void
{
	foreach ($selection as $key => $row) {
		assertType('int|string', $key);
		assertType(ActiveRow::class, $row);
	}
}


/**
 * @param Selection<ActiveRow> $selection
 */
function testSelectionArrayAccess(Selection $selection): void
{
	$row = $selection['key'];
	assertType(ActiveRow::class . '|null', $row);
}


/**
 * @param Selection<ActiveRow> $selection
 */
function testSelectionFluentMethods(Selection $selection): void
{
	$result = $selection->where('id', 1);
	assertType('Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>', $result);

	$result = $selection->limit(10);
	assertType('Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>', $result);

	$result = $selection->page(1, 10);
	assertType('Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>', $result);
}


function testParseColumnType(): void
{
	$result = Helpers::parseColumnType('varchar(255)');
	assertType('array{type: string|null, length: int|null, scale: int|null, parameters: string|null}', $result);
}


/** @param Selection<ActiveRow> $selection */
function testSelectionFetch(Selection $selection): void
{
	$result = $selection->fetch();
	assertType(ActiveRow::class . '|null', $result);
}


/** @param Selection<ActiveRow> $selection */
function testSelectionGet(Selection $selection): void
{
	$result = $selection->get(1);
	assertType(ActiveRow::class . '|null', $result);
}


/** @param Selection<ActiveRow> $selection */
function testSelectionFetchAll(Selection $selection): void
{
	$result = $selection->fetchAll();
	assertType('array<Nette\Database\Table\ActiveRow>', $result);
}


function testReflectionGetTables(Nette\Database\Reflection $reflection): void
{
	assertType('list<Nette\Database\Reflection\Table>', $reflection->getTables());
}


function testResultSetFetchAll(ResultSet $resultSet): void
{
	assertType('list<Nette\Database\Row>', $resultSet->fetchAll());
}


function testResultSetFetchPairs(ResultSet $resultSet): void
{
	assertType('array<mixed>', $resultSet->fetchPairs());
}
