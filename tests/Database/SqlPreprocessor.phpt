<?php

/**
 * Test: Nette\Database\SqlPreprocessor
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Database\SqlLiteral;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
$preprocessor = new Nette\Database\SqlPreprocessor($connection);

test('Processes basic SQL query with single parameter', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id = ?', 11]);
	Assert::same('SELECT id FROM author WHERE id = ?', $sql);
	Assert::same([11], $params);
});


test('Substitutes parameters directly for non-parameterized queries', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['UNKNOWN a = ?, b = ?, c = ?, d = ?, e = ?', 123, 'abc', true, false, null]);
	Assert::same("UNKNOWN a = 123, b = 'abc', c = 1, d = 0, e = NULL", $sql);
	Assert::same([], $params);
});


test('Handles subqueries in parentheses correctly', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['(SELECT ?) UNION (SELECT ?)', 1, 2]);
	Assert::same('(SELECT ?) UNION (SELECT ?)', $sql);
	Assert::same([1, 2], $params);
});


test('Supports parameter without explicit placeholder', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id =', 11]);
	Assert::same('SELECT id FROM author WHERE id = ?', $sql);
	Assert::same([11], $params);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id =', '11']);
	Assert::same('SELECT id FROM author WHERE id = ?', $sql);
	Assert::same(['11'], $params);
});


test('Handles multiple parameters in WHERE clause', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id = ? OR id = ?', 11, 12]);
	Assert::same('SELECT id FROM author WHERE id = ? OR id = ?', $sql);
	Assert::same([11, 12], $params);
});


test('Processes parameters split across multiple query parts', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id = ?', 11, 'OR id = ?', 12]);
	Assert::same('SELECT id FROM author WHERE id = ? OR id = ?', $sql);
	Assert::same([11, 12], $params);
});


test('Processes array conditions after WHERE clause', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id = ? AND ?', 12, ['a' => 2]]);
	Assert::same(reformat('SELECT id FROM author WHERE id = ? AND [a]=?'), $sql);
	Assert::same([12, 2], $params);
});


test('Handles IN operator with array values', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id IN (?)', [10, 11]]);
	Assert::same('SELECT id FROM author WHERE id IN (?, ?)', $sql);
	Assert::same([10, 11], $params);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE (id, name) IN (?)', [[10, 'a'], [11, 'b']]]);
	Assert::same('SELECT id FROM author WHERE (id, name) IN ((?, ?), (?, ?))', $sql);
	Assert::same([10, 'a', 11, 'b'], $params);

	[$sql, $params] = $preprocessor->process(['SELECT * FROM table WHERE ? AND id IN (?) AND ?', ['a' => 111], [3, 4], ['b' => 222]]);
	Assert::same(reformat('SELECT * FROM table WHERE ([a] = ?) AND id IN (?, ?) AND ([b] = ?)'), $sql);
	Assert::same([111, 3, 4, 222], $params);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id IN ?', [10, 11]]); // without ()
	Assert::same('SELECT id FROM author WHERE id IN (?, ?)', $sql);
	Assert::same([10, 11], $params);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id IN (?)', 10]); // single item in ()
	Assert::same('SELECT id FROM author WHERE id IN (?)', $sql);
	Assert::same([10], $params);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id IN (?)', [10, 11]]); // array in ()
	Assert::same('SELECT id FROM author WHERE id IN (?, ?)', $sql);
	Assert::same([10, 11], $params);
});


test('Processes named column placeholders (?name)', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE ?name = ? OR ?name = ?', 'id', 12, 'table.number', 23]);
	Assert::same(reformat('SELECT id FROM author WHERE [id] = ? OR [table].[number] = ?'), $sql);
	Assert::same([12, 23], $params);
});


test('Preserves comments in SQL queries', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(["SELECT id --?\nFROM author WHERE id = ?", 11]);
	Assert::same("SELECT id --?\nFROM author WHERE id = ?", $sql);
	Assert::same([11], $params);

	[$sql, $params] = $preprocessor->process(["SELECT id /* ? \n */FROM author WHERE id = ? --*/", 11]);
	Assert::same("SELECT id /* ? \n */FROM author WHERE id = ? --*/", $sql);
	Assert::same([11], $params);
});


test('Preserves string literals containing question marks', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(["SELECT id, '?' FROM author WHERE id = ?", 11]);
	Assert::same("SELECT id, '?' FROM author WHERE id = ?", $sql);
	Assert::same([11], $params);

	[$sql, $params] = $preprocessor->process(['SELECT id, "?" FROM author WHERE id = ?', 11]);
	Assert::same('SELECT id, "?" FROM author WHERE id = ?', $sql);
	Assert::same([11], $params);
});


test('Auto-detects operator in WHERE conditions', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE', [
		'col_null' => null,
		'x.col_val' => 'a',
		'col_arr' => [1, 2],
	]]);

	Assert::same(reformat('SELECT id FROM tbl WHERE ([col_null] IS NULL) AND ([x].[col_val] = ?) AND ([col_arr] IN (?, ?))'), $sql);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE', [
		'col_null NOT' => null,
		'x.col_val NOT' => 'a', // not supported
		'col_arr NOT' => [1, 2],
	]]);

	Assert::same(reformat('SELECT id FROM tbl WHERE ([col_null] IS NOT NULL) AND ([x].[col_val] NOT ?) AND ([col_arr] NOT IN (?, ?))'), $sql);
});


test('Supports explicit operators in WHERE conditions', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE', [
		'col_is =' => 1,
		'col_not <>' => 1,
		'col_like LIKE' => 'a',
		'col_like NOT LIKE' => 'a', // not supported
		'col_null =' => null, // always false
		'col_arr =' => [1, 2], // not supported
	]]);

	Assert::same(reformat('SELECT id FROM tbl WHERE ([col_is] = ?) AND ([col_not] <> ?) AND ([col_like] LIKE ?) AND ([col_like] NOT ?) AND ([col_null] = NULL) AND ([col_arr] = IN (?, ?))'), $sql);
});


test('Redundant WHERE operators', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE', [
		'col_arr IN' => [],
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE (1=0)'), $sql);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE', [
		'col_arr NOT IN' => [],
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE ()'), $sql); // buggy
});


test('Empty WHERE conditions', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE', [
		'col_empty' => [],
		'foo',
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE (1=0) AND (?)'), $sql);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE', [
		'col_empty' => [],
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE (1=0)'), $sql);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE', [
		'col_empty IN' => [],
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE (1=0)'), $sql);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE', [
		'col_empty NOT' => [],
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE ()'), $sql); // buggy

	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE', [
		'col_empty NOT IN' => [],
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE ()'), $sql); // buggy
});


test('Empty WHERE conditions joined with OR', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE ?or', [
		'col_empty' => [],
		new SqlLiteral('foo'),
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE (1=0) OR (foo)'), $sql);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE ?or', [
		'col_empty' => [],
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE (1=0)'), $sql);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE ?or', [
		'col_empty' => [],
		'col_empty NOT' => [],
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE (1=0)'), $sql); // buggy

	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE ?or', [
		'col_empty IN' => [],
		'col_empty NOT IN' => [],
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE (1=0)'), $sql); // buggy
});


test('WHERE conditions with indexed items', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE', [
		new SqlLiteral('foo'),
		'foo', // using values other than `SqlLiteral` is not useful, but it would be dangerous to interpret strings as SQL literals
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE (foo) AND (?)'), $sql);
	Assert::same(['foo'], $params);

	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE ?or', [
		new SqlLiteral('foo'),
		'foo',
	]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE (foo) OR (?)'), $sql);
	Assert::same(['foo'], $params);
});


test('Combines WHERE conditions with array & direct SQL', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM tbl WHERE id=?', 10, 'AND ?and', ['c1' => null, 'c2' => 2], 'AND ?or', ['c3' => null, 'c4' => 4]]);
	Assert::same(reformat('SELECT id FROM tbl WHERE id=? AND ([c1] IS NULL) AND ([c2] = ?) AND ([c3] IS NULL) OR ([c4] = ?)'), $sql); // is not properly clamped
	Assert::same([10, 2, 4], $params);
});


test('multi-value IN conditions (tuples)', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT * FROM book_tag WHERE (book_id, tag_id) IN (?)', [
		[1, 2],
		[3, 4],
		[5, 6],
	]]);

	Assert::same(reformat('SELECT * FROM book_tag WHERE (book_id, tag_id) IN ((?, ?), (?, ?), (?, ?))'), $sql);
	Assert::same([1, 2, 3, 4, 5, 6], $params);
});


test('ORDER BY with multiple columns', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author ORDER BY', [
		'id' => true,
		'name' => false,
	]]);

	Assert::same(reformat('SELECT id FROM author ORDER BY [id], [name] DESC'), $sql);
	Assert::same([], $params);
});


test('ORDER BY with ?order placeholder', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author ORDER BY ?order', [
		'id' => true,
		'name' => false,
	]]);

	Assert::same(reformat('SELECT id FROM author ORDER BY [id], [name] DESC'), $sql);
	Assert::same([], $params);
});


test('WHERE conditions with ORDER BY', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE ? ORDER BY ?', [
		'id' => 1,
		'web' => 'web',
	], [
		'name' => false,
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] = ?) AND ([web] = ?) ORDER BY [name] DESC'), $sql);
	Assert::same([1, 'web'], $params);
});


test('Detects missing required parameters', function () use ($preprocessor) {
	Assert::exception(
		fn() => $preprocessor->process(['SELECT id FROM author WHERE id =? OR id = ?', 11]),
		Nette\InvalidArgumentException::class,
		'There are more placeholders than passed parameters.',
	);

	Assert::exception(
		fn() => $preprocessor->process(['SELECT id FROM author WHERE id =', new SqlLiteral('? OR ?name = ?', [11]), 'id', 12]),
		Nette\InvalidArgumentException::class,
		'There are more placeholders than passed parameters.',
	);
});


test('Detects extra parameters', function () use ($preprocessor) {
	Assert::exception(
		fn() => $preprocessor->process(['SELECT id FROM author WHERE id =', 11, 12]),
		Nette\InvalidArgumentException::class,
		'There are more parameters than placeholders.',
	);

	Assert::exception(
		fn() => $preprocessor->process(['SELECT id FROM author WHERE id =?', 11, 12]),
		Nette\InvalidArgumentException::class,
		'There are more parameters than placeholders.',
	);

	Assert::exception(
		fn() => $preprocessor->process(['SELECT id FROM author WHERE id =', 'a', 'b']),
		Nette\InvalidArgumentException::class,
		'There are more parameters than placeholders.',
	);

	Assert::exception(
		fn() => $preprocessor->process(['SELECT id FROM author WHERE id =', '?', 11, 'OR id = ?', 12]),
		Nette\InvalidArgumentException::class,
		'There are more parameters than placeholders.',
	);
});


test('Detects unknown placeholder format', function () use ($preprocessor) {
	Assert::exception(
		fn() => $preprocessor->process(['SELECT ?test', 11]),
		Nette\InvalidArgumentException::class,
		'Unknown placeholder ?test.',
	);
});


test('SQL literals with parameters', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE id =', new SqlLiteral('? OR ?name = ?', [11, 'id', 12])]);
	Assert::same(reformat('SELECT id FROM author WHERE id = ? OR [id] = ?'), $sql);
	Assert::same([11, 12], $params);
});


test('SQL literals in query', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE', new SqlLiteral('id=11'), 'OR', new SqlLiteral('id=?', [12])]);
	Assert::same('SELECT id FROM author WHERE id=11 OR id=?', $sql);
	Assert::same([12], $params);
});


test('WHERE conditions with SQL literals', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE', [
		'id' => new SqlLiteral('NULL'),
		'born' => [1, 2, new SqlLiteral('3+1')],
		'web' => new SqlLiteral('NOW()'),
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NULL) AND ([born] IN (?, ?, 3+1)) AND ([web] = NOW())'), $sql);
	Assert::same([1, 2], $params);
});


test('empty WHERE conditions array', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE', []]);

	Assert::same(reformat('SELECT id FROM author WHERE 1=1'), $sql);
	Assert::same([], $params);
});


test('AND operator in WHERE conditions', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE ?and', [
		'id' => null,
		'born' => [1, 2],
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NULL) AND ([born] IN (?, ?))'), $sql);
	Assert::same([1, 2], $params);
});


test('OR operator in WHERE conditions', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE ?or', [
		'id' => null,
		'born' => [1, 2],
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NULL) OR ([born] IN (?, ?))'), $sql);
	Assert::same([1, 2], $params);
});


test('date and time value formatting', function () use ($preprocessor, $driverName) {
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


test('INSERT query with single row', function () use ($preprocessor) {
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
	Assert::same(reformat("/* comment */  INSERT INTO author [name]='Catelyn Stark'"), $sql); // autodetection not used
	Assert::same([], $params);
});


test('?values placeholder in INSERT', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['INSERT INTO update ?values',
		['name' => 'Catelyn Stark'],
	]);

	Assert::same(reformat('INSERT INTO update ([name]) VALUES (?)'), $sql);
	Assert::same(['Catelyn Stark'], $params);
});


test('Detects incorrect multi-insert usage', function () use ($preprocessor) {
	Assert::exception(
		fn() => $preprocessor->process(['INSERT INTO author (name) SELECT name FROM user WHERE id ?', [11, 12]]),
		Nette\InvalidArgumentException::class,
		'Automaticaly detected multi-insert, but values aren\'t array. If you need try to change mode like "?[and|or|set|values|order|list]". Mode "values" was used.',
	);
});


test('multi-row INSERT query', function () use ($preprocessor) {
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


test('multi-row INSERT with Database\Row objects', function () use ($preprocessor) {
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


test('Preserves column order in multi-row INSERT', function () use ($preprocessor) {
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


test('?values placeholder with multiple rows', function () use ($preprocessor) {
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


test('UPDATE query with multiple columns', function () use ($preprocessor) {
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

	[$sql, $params] = $preprocessor->process(['UPDATE author SET a=1,',
		['id' => 12, 'name' => 'John Doe'],
	]);
	Assert::same(reformat('UPDATE author SET a=1, [id]=?, [name]=?'), $sql);
	Assert::same([12, 'John Doe'], $params);
});


test('?set placeholder in UPDATE', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['UPDATE insert SET ?set',
		['id' => 12, 'name' => 'John Doe'],
	]);

	Assert::same(reformat('UPDATE insert SET [id]=?, [name]=?'), $sql);
	Assert::same([12, 'John Doe'], $params);
});


test('increment/decrement operators in UPDATE', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['UPDATE author SET ?',
		['id+=' => 1, 'id-=' => -1],
	]);

	Assert::same(reformat('UPDATE author SET [id]=[id] + ?, [id]=[id] - ?'), $sql);
	Assert::same([1, -1], $params);
});


test('INSERT with ON DUPLICATE KEY UPDATE', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['INSERT INTO author ? ON DUPLICATE KEY UPDATE ?',
		['id' => 12, 'name' => 'John Doe'],
		['web' => 'http://nette.org', 'name' => 'Dave Lister'],
	]);

	Assert::same(reformat('INSERT INTO author ([id], [name]) VALUES (?, ?) ON DUPLICATE KEY UPDATE [web]=?, [name]=?'), $sql);
	Assert::same([12, 'John Doe', 'http://nette.org', 'Dave Lister'], $params);
});


test('Validates parameters for special placeholders', function () use ($preprocessor) {
	foreach (['?and', '?or', '?set', '?values', '?order'] as $mode) {
		Assert::exception(
			fn() => $preprocessor->process([$mode, 'string']),
			Nette\InvalidArgumentException::class,
			"Placeholder $mode expects array or Traversable object, string given.",
		);
	}

	Assert::exception(
		fn() => $preprocessor->process(['SELECT ?name', ['id', 'table.id']]),
		Nette\InvalidArgumentException::class,
		'Placeholder ?name expects string, array given.',
	);
});


test('nested SQL literals with special placeholders', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE ?or', [
		new SqlLiteral('max > ?', [10]),
		new SqlLiteral('min < ?', [20]),
	]]);
	Assert::same(reformat('SELECT id FROM author WHERE (max > ?) OR (min < ?)'), $sql);
	Assert::same([10, 20], $params);
});


test('complex nested conditions with SQL literals', function () use ($preprocessor) {
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

test('objects with __toString implementation', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT ?', new ToString]);
	Assert::same('SELECT ?', $sql);
	Assert::same(['hello'], $params);
});


Assert::exception(
	fn() => $preprocessor->process(['SELECT ?', new stdClass]),
	Nette\InvalidArgumentException::class,
	'Unexpected type of parameter: stdClass',
);


test('resource parameters', function () use ($preprocessor) {
	[$sql, $params] = $preprocessor->process(['SELECT ?', $res = fopen(__FILE__, 'r')]);
	Assert::same('SELECT ?', $sql);
	Assert::same([$res], $params);
});
