<?php

/**
 * Test: Nette\Database\SqlPreprocessor
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Database\SqlLiteral;
use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection


$preprocessor = new Nette\Database\SqlPreprocessor($connection);

test('basic', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id = ?', 11]);
	Assert::same('SELECT id FROM author WHERE id = ?', $sql);
	Assert::same([11], $params);
});


test('no parameters', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['UNKNOWN a = ?, b = ?, c = ?, d = ?, e = ?', 123, 'abc', true, false, null]);
	Assert::same("UNKNOWN a = 123, b = 'abc', c = 1, d = 0, e = NULL", $sql);
	Assert::same([], $params);
});


test('recognizes command in braces', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['(SELECT ?) UNION (SELECT ?)', 1, 2]);
	Assert::same('(SELECT ?) UNION (SELECT ?)', $sql);
	Assert::same([1, 2], $params);
});


test('arg without placeholder', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id =', 11]);
	Assert::same('SELECT id FROM author WHERE id = ?', $sql);
	Assert::same([11], $params);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id =', '11']);
	Assert::same('SELECT id FROM author WHERE id = ?', $sql);
	Assert::same(['11'], $params);
});


test('', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id = ? OR id = ?', 11, 12]);
	Assert::same('SELECT id FROM author WHERE id = ? OR id = ?', $sql);
	Assert::same([11, 12], $params);
});


test('', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id = ?', 11, 'OR id = ?', 12]);
	Assert::same('SELECT id FROM author WHERE id = ? OR id = ?', $sql);
	Assert::same([11, 12], $params);
});


test('IN', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id IN (?)', [10, 11]]);
	Assert::same('SELECT id FROM author WHERE id IN (?, ?)', $sql);
	Assert::same([10, 11], $params);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE (id, name) IN (?)', [[10, 'a'], [11, 'b']]]);
	Assert::same('SELECT id FROM author WHERE (id, name) IN ((?, ?), (?, ?))', $sql);
	Assert::same([10, 'a', 11, 'b'], $params);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE', [
		'a' => [null, 1, 2, 3],
		'b' => [],
		'c NOT IN' => [null, 1, 2, 3],
		'd NOT IN' => [],
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([a] IN (NULL, ?, ?, ?)) AND (1=0) AND ([c] NOT IN (NULL, ?, ?, ?))'), $sql);
	Assert::same([1, 2, 3, 1, 2, 3], $params);
});


test('?name', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE ?name = ? OR ?name = ?', 'id', 12, 'table.number', 23]);
	Assert::same(reformat('SELECT id FROM author WHERE [id] = ? OR [table].[number] = ?'), $sql);
	Assert::same([12, 23], $params);
});


test('comments', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(["SELECT id --?\nFROM author WHERE id = ?", 11]);
	Assert::same("SELECT id --?\nFROM author WHERE id = ?", $sql);
	Assert::same([11], $params);

	[$sql, $params] = $preprocessor->process(["SELECT id /* ? \n */FROM author WHERE id = ? --*/", 11]);
	Assert::same("SELECT id /* ? \n */FROM author WHERE id = ? --*/", $sql);
	Assert::same([11], $params);
});


test('strings', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(["SELECT id, '?' FROM author WHERE id = ?", 11]);
	Assert::same("SELECT id, '?' FROM author WHERE id = ?", $sql);
	Assert::same([11], $params);

	[$sql, $params] = $preprocessor->process(['SELECT id, "?" FROM author WHERE id = ?', 11]);
	Assert::same('SELECT id, "?" FROM author WHERE id = ?', $sql);
	Assert::same([11], $params);
});


test('where', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE', [
		'id' => null,
		'x.name <>' => 'a',
		'born NOT' => null,
		'born' => [null, 1, 2, 3],
		'web' => [],
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NULL) AND ([x].[name] <> ?) AND ([born] IS NOT NULL) AND ([born] IN (NULL, ?, ?, ?)) AND (1=0)'), $sql);
	Assert::same(['a', 1, 2, 3], $params);
});

test('where is not null', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE', [
		'id NOT' => null,
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NOT NULL)'), $sql);
	Assert::same([], $params);
});

test('tuples', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT * FROM book_tag WHERE (book_id, tag_id) IN (?)', [
		[1, 2],
		[3, 4],
		[5, 6],
	]]);

	Assert::same(reformat('SELECT * FROM book_tag WHERE (book_id, tag_id) IN ((?, ?), (?, ?), (?, ?))'), $sql);
	Assert::same([1, 2, 3, 4, 5, 6], $params);
});


test('order', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author ORDER BY', [
		'id' => true,
		'name' => false,
	]]);

	Assert::same(reformat('SELECT id FROM author ORDER BY [id], [name] DESC'), $sql);
	Assert::same([], $params);
});


test('?order', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author ORDER BY ?order', [
		'id' => true,
		'name' => false,
	]]);

	Assert::same(reformat('SELECT id FROM author ORDER BY [id], [name] DESC'), $sql);
	Assert::same([], $params);
});


test('mix of where & order', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE ? ORDER BY ?', [
		'id' => 1,
		'web' => 'web',
	], [
		'name' => false,
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] = ?) AND ([web] = ?) ORDER BY [name] DESC'), $sql);
	Assert::same([1, 'web'], $params);
});


test('missing parameters', function () use ($preprocessor) {
	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT id FROM author WHERE id =? OR id = ?', 11]);
	}, Nette\InvalidArgumentException::class, 'There are more placeholders than passed parameters.');

	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT id FROM author WHERE id =', new SqlLiteral('? OR ?name = ?', [11]), 'id', 12]);
	}, Nette\InvalidArgumentException::class, 'There are more placeholders than passed parameters.');
});


test('extra parameters', function () use ($preprocessor) {
	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT id FROM author WHERE id =', 11, 12]);
	}, Nette\InvalidArgumentException::class, 'There are more parameters than placeholders.');

	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT id FROM author WHERE id =?', 11, 12]);
	}, Nette\InvalidArgumentException::class, 'There are more parameters than placeholders.');

	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT id FROM author WHERE id =', 'a', 'b']);
	}, Nette\InvalidArgumentException::class, 'There are more parameters than placeholders.');

	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT id FROM author WHERE id =', '?', 11, 'OR id = ?', 12]);
	}, Nette\InvalidArgumentException::class, 'There are more parameters than placeholders.');
});


test('unknown placeholder', function () use ($preprocessor) {
	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT ?test', 11]);
	}, Nette\InvalidArgumentException::class, 'Unknown placeholder ?test.');
});


test('SqlLiteral', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id =', new SqlLiteral('? OR ?name = ?', [11, 'id', 12])]);
	Assert::same(reformat('SELECT id FROM author WHERE id = ? OR [id] = ?'), $sql);
	Assert::same([11, 12], $params);
});


test('', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE', new SqlLiteral('id=11'), 'OR', new SqlLiteral('id=?', [12])]);
	Assert::same('SELECT id FROM author WHERE id=11 OR id=?', $sql);
	Assert::same([12], $params);
});


test('and', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE', [
		'id' => new SqlLiteral('NULL'),
		'born' => [1, 2, new SqlLiteral('3+1')],
		'web' => new SqlLiteral('NOW()'),
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NULL) AND ([born] IN (?, ?, 3+1)) AND ([web] = NOW())'), $sql);
	Assert::same([1, 2], $params);
});


test('empty and', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE', []]);

	Assert::same(reformat('SELECT id FROM author WHERE 1=1'), $sql);
	Assert::same([], $params);
});


test('?and', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE ?and', [
		'id' => null,
		'born' => [1, 2],
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NULL) AND ([born] IN (?, ?))'), $sql);
	Assert::same([1, 2], $params);
});


test('?or', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE ?or', [
		'id' => null,
		'born' => [1, 2],
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NULL) OR ([born] IN (?, ?))'), $sql);
	Assert::same([1, 2], $params);
});


test('date time', function () use ($preprocessor, $driverName) {
	[$sql, $params] = $preprocessor->process(['SELECT ?', [new DateTime('2011-11-11')]]);
	Assert::same(reformat([
		'sqlite' => 'SELECT 1320966000',
		'sqlsrv' => "SELECT '2011-11-11T00:00:00'",
		"SELECT '2011-11-11 00:00:00'",
	]), $sql);
	Assert::same([], $params);

	if ($driverName === 'mysql') {
		$interval = new DateInterval('PT26H8M10S');
		$interval->invert = true;
		[$sql, $params] = $preprocessor->process(['SELECT ?', [$interval]]);
		Assert::same(reformat("SELECT '-26:08:10'"), $sql);
	}

	Assert::same([], $params);
	[$sql, $params] = $preprocessor->process(['SELECT ?', [new DateTimeImmutable('2011-11-11')]]);
	Assert::same(reformat([
		'sqlite' => 'SELECT 1320966000',
		'sqlsrv' => "SELECT '2011-11-11T00:00:00'",
		"SELECT '2011-11-11 00:00:00'",
	]), $sql);
});


test('insert', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['INSERT INTO author',
		['name' => 'Catelyn Stark', 'born' => new DateTime('2011-11-11')],
	]);

	Assert::same(reformat([
		'sqlite' => 'INSERT INTO author ([name], [born]) VALUES (?, 1320966000)',
		'sqlsrv' => "INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11T00:00:00')",
		"INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11 00:00:00')",
	]), $sql);
	Assert::same(['Catelyn Stark'], $params);

	[$sql, $params] = $preprocessor->process(["\r\n  INSERT INTO author",
		['name' => 'Catelyn Stark'],
	]);
	Assert::same(reformat("\r\n  INSERT INTO author ([name]) VALUES (?)"), $sql);
	Assert::same(['Catelyn Stark'], $params);

	[$sql, $params] = $preprocessor->process(['REPLACE author ?',
		['name' => 'Catelyn Stark'],
	]);
	Assert::same(reformat('REPLACE author ([name]) VALUES (?)'), $sql);
	Assert::same(['Catelyn Stark'], $params);

	[$sql, $params] = $preprocessor->process(['/* comment */  INSERT INTO author',
		['name' => 'Catelyn Stark'],
	]);
	Assert::same(reformat("/* comment */  INSERT INTO author 'Catelyn Stark'"), $sql); // autodetection not used
	Assert::same([], $params);
});


test('?values', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['INSERT INTO update ?values',
		['name' => 'Catelyn Stark'],
	]);

	Assert::same(reformat('INSERT INTO update ([name]) VALUES (?)'), $sql);
	Assert::same(['Catelyn Stark'], $params);
});


test('automatic detection failed', function () use ($preprocessor) {
	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['INSERT INTO author (name) SELECT name FROM user WHERE id IN (?)', [11, 12]]);
	}, Nette\InvalidArgumentException::class, 'Automaticaly detected multi-insert, but values aren\'t array. If you need try to change mode like "?[and|or|set|values|order|list]". Mode "values" was used.');
});


test('multi insert', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['INSERT INTO author', [
		['name' => 'Catelyn Stark', 'born' => new DateTime('2011-11-11')],
		['name' => 'Sansa Stark', 'born' => new DateTime('2021-11-11')],
	]]);

	Assert::same(reformat([
		'sqlite' => 'INSERT INTO author ([name], [born]) SELECT ?, 1320966000 UNION ALL SELECT ?, 1636585200',
		'sqlsrv' => "INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11T00:00:00'), (?, '2021-11-11T00:00:00')",
		"INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11 00:00:00'), (?, '2021-11-11 00:00:00')",
	]), $sql);
	Assert::same(['Catelyn Stark', 'Sansa Stark'], $params);
});


test('multi insert & Rows', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['INSERT INTO author', [
		Nette\Database\Row::from(['name' => 'Catelyn Stark', 'born' => new DateTime('2011-11-11')]),
		Nette\Database\Row::from(['name' => 'Sansa Stark', 'born' => new DateTime('2021-11-11')]),
	]]);

	Assert::same(reformat([
		'sqlite' => 'INSERT INTO author ([name], [born]) SELECT ?, 1320966000 UNION ALL SELECT ?, 1636585200',
		'sqlsrv' => "INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11T00:00:00'), (?, '2021-11-11T00:00:00')",
		"INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11 00:00:00'), (?, '2021-11-11 00:00:00')",
	]), $sql);
	Assert::same(['Catelyn Stark', 'Sansa Stark'], $params);
});


test('multi insert respects keys', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['INSERT INTO author', [
		['name' => 'Catelyn Stark', 'born' => new DateTime('2011-11-11')],
		['born' => new DateTime('2021-11-11'), 'name' => 'Sansa Stark'],
	]]);

	Assert::same(reformat([
		'sqlite' => 'INSERT INTO author ([name], [born]) SELECT ?, 1320966000 UNION ALL SELECT ?, 1636585200',
		'sqlsrv' => "INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11T00:00:00'), (?, '2021-11-11T00:00:00')",
		"INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11 00:00:00'), (?, '2021-11-11 00:00:00')",
	]), $sql);
	Assert::same(['Catelyn Stark', 'Sansa Stark'], $params);
});


test('multi insert ?values', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['INSERT INTO author ?values', [
		['name' => 'Catelyn Stark', 'born' => new DateTime('2011-11-11')],
		['name' => 'Sansa Stark', 'born' => new DateTime('2021-11-11')],
	]]);

	Assert::same(reformat([
		'sqlite' => 'INSERT INTO author ([name], [born]) SELECT ?, 1320966000 UNION ALL SELECT ?, 1636585200',
		'sqlsrv' => "INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11T00:00:00'), (?, '2021-11-11T00:00:00')",

		"INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11 00:00:00'), (?, '2021-11-11 00:00:00')",
	]), $sql);
	Assert::same(['Catelyn Stark', 'Sansa Stark'], $params);
});


test('update', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['UPDATE author SET ?', [
		'id' => 12,
		'name' => new SqlLiteral('UPPER(?)', ['John Doe']),
		new SqlLiteral('UPPER(?) = ?', ['John', 'DOE']),
	]]);

	Assert::same(reformat('UPDATE author SET [id]=?, [name]=UPPER(?), UPPER(?) = ?'), $sql);
	Assert::same([12, 'John Doe', 'John', 'DOE'], $params);

	[$sql, $params] = $preprocessor->process(["UPDATE author SET \n",
		['id' => 12, 'name' => 'John Doe'],
	]);
	Assert::same(reformat("UPDATE author SET \n [id]=?, [name]=?"), $sql);
	Assert::same([12, 'John Doe'], $params);

	[$sql, $params] = $preprocessor->process(['UPDATE author SET',
		['id' => 12, 'name' => 'John Doe'],
	]);
	Assert::same(reformat('UPDATE author SET [id]=?, [name]=?'), $sql);
	Assert::same([12, 'John Doe'], $params);

	[$sql, $params] = $preprocessor->process(['UPDATE author SET a=1,', // autodetection not used
		['id' => 12, 'name' => 'John Doe'],
	]);
	Assert::same(reformat('UPDATE author SET a=1, ?, ?'), $sql);
	Assert::same([12, 'John Doe'], $params);
});


test('?set', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['UPDATE insert SET ?set',
		['id' => 12, 'name' => 'John Doe'],
	]);

	Assert::same(reformat('UPDATE insert SET [id]=?, [name]=?'), $sql);
	Assert::same([12, 'John Doe'], $params);
});


test('update +=', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['UPDATE author SET ?',
		['id+=' => 1, 'id-=' => -1],
	]);

	Assert::same(reformat('UPDATE author SET [id]=[id] + ?, [id]=[id] - ?'), $sql);
	Assert::same([1, -1], $params);
});


test('insert & update', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['INSERT INTO author ? ON DUPLICATE KEY UPDATE ?',
		['id' => 12, 'name' => 'John Doe'],
		['web' => 'http://nette.org', 'name' => 'Dave Lister'],
	]);

	Assert::same(reformat('INSERT INTO author ([id], [name]) VALUES (?, ?) ON DUPLICATE KEY UPDATE [web]=?, [name]=?'), $sql);
	Assert::same([12, 'John Doe', 'http://nette.org', 'Dave Lister'], $params);
});


test('invalid usage of ?and, ...', function () use ($preprocessor) {
	foreach (['?and', '?or', '?set', '?values', '?order'] as $mode) {
		Assert::exception(function () use ($preprocessor, $mode) {
			$preprocessor->process([$mode, 'string']);
		}, Nette\InvalidArgumentException::class, "Placeholder $mode expects array or Traversable object, string given.");
	}

	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT ?name', ['id', 'table.id']]);
	}, Nette\InvalidArgumentException::class, 'Placeholder ?name expects string, array given.');
});


test('', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE ?or', [
		new SqlLiteral('max > ?', [10]),
		new SqlLiteral('min < ?', [20]),
	]]);
	Assert::same(reformat('SELECT id FROM author WHERE (max > ?) OR (min < ?)'), $sql);
	Assert::same([10, 20], $params);
});


test('', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE', new SqlLiteral('?or', [[
		new SqlLiteral('?and', [['a' => 1, 'b' => 2]]),
		new SqlLiteral('?and', [['c' => 3, 'd' => 4]]),
	]])]);
	Assert::same(reformat('SELECT id FROM author WHERE (([a] = ?) AND ([b] = ?)) OR (([c] = ?) AND ([d] = ?))'), $sql);
	Assert::same([1, 2, 3, 4], $params);
});


class ToString
{
	public function __toString()
	{
		return 'hello';
	}
}

test('object', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT ?', new ToString]);
	Assert::same('SELECT ?', $sql);
	Assert::same(['hello'], $params);
});


Assert::exception(function () use ($preprocessor) { // object
	$preprocessor->process(['SELECT ?', new stdClass]);
}, Nette\InvalidArgumentException::class, 'Unexpected type of parameter: stdClass');


test('resource', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT ?', $res = fopen(__FILE__, 'r')]);
	Assert::same('SELECT ?', $sql);
	Assert::same([$res], $params);
});
