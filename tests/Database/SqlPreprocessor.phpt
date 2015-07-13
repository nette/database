<?php

/**
 * Test: Nette\Database\SqlPreprocessor
 * @dataProvider? databases.ini
 */

use Nette\Database\SqlLiteral;
use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection


$preprocessor = new Nette\Database\SqlPreprocessor($connection);

test(function () use ($preprocessor) { // basic
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE id = ?', 11]);
	Assert::same('SELECT id FROM author WHERE id = 11', $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // arg without placeholder
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE id =', 11]);
	Assert::same('SELECT id FROM author WHERE id = 11', $sql);
	Assert::same([], $params);

	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE id =', '11']);
	Assert::same('SELECT id FROM author WHERE id = ?', $sql);
	Assert::same(['11'], $params);

	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE id =', '1\\1']);
	Assert::same('SELECT id FROM author WHERE id = ?', $sql);
	Assert::same(['1\\1'], $params);
});


test(function () use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE id = ? OR id = ?', 11, 12]);
	Assert::same('SELECT id FROM author WHERE id = 11 OR id = 12', $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE id = ?', 11, 'OR id = ?', 12]);
	Assert::same('SELECT id FROM author WHERE id = 11 OR id = 12', $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // IN
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE id IN (?)', [10, 11]]);
	Assert::same('SELECT id FROM author WHERE id IN (10, 11)', $sql);
	Assert::same([], $params);

	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE (id, name) IN (?)', [[10, 'a'], [11, 'b']]]);
	Assert::same('SELECT id FROM author WHERE (id, name) IN ((10, ?), (11, ?))', $sql);
	Assert::same(['a', 'b'], $params);


	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE', [
		'a' => [null, 1, 2, 3],
		'b' => [],
		'c NOT IN' => [null, 1, 2, 3],
		'd NOT IN' => [],
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([a] IN (NULL, 1, 2, 3)) AND (1=0) AND ([c] NOT IN (NULL, 1, 2, 3))'), $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // ?name
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE ?name = ? OR ?name = ?', 'id', 12, 'table.number', 23]);
	Assert::same(reformat('SELECT id FROM author WHERE [id] = 12 OR [table].[number] = 23'), $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // comments
	list($sql, $params) = $preprocessor->process(["SELECT id --?\nFROM author WHERE id = ?", 11]);
	Assert::same("SELECT id --?\nFROM author WHERE id = 11", $sql);
	Assert::same([], $params);

	list($sql, $params) = $preprocessor->process(["SELECT id /* ? \n */FROM author WHERE id = ? --*/", 11]);
	Assert::same("SELECT id /* ? \n */FROM author WHERE id = 11 --*/", $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // strings
	list($sql, $params) = $preprocessor->process(["SELECT id, '?' FROM author WHERE id = ?", 11]);
	Assert::same("SELECT id, '?' FROM author WHERE id = 11", $sql);
	Assert::same([], $params);

	list($sql, $params) = $preprocessor->process(['SELECT id, "?" FROM author WHERE id = ?', 11]);
	Assert::same('SELECT id, "?" FROM author WHERE id = 11', $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // where
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE', [
		'id' => null,
		'x.name <>' => 'a',
		'born' => [null, 1, 2, 3],
		'web' => [],
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NULL) AND ([x].[name] <> ?) AND ([born] IN (NULL, 1, 2, 3)) AND (1=0)'), $sql);
	Assert::same(['a'], $params);
});


test(function () use ($preprocessor) { // tuples
	list($sql, $params) = $preprocessor->process(['SELECT * FROM book_tag WHERE (book_id, tag_id) IN (?)', [
		[1, 2],
		[3, 4],
		[5, 6],
	]]);

	Assert::same(reformat('SELECT * FROM book_tag WHERE (book_id, tag_id) IN ((1, 2), (3, 4), (5, 6))'), $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // order
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author ORDER BY', [
		'id' => true,
		'name' => false,
	]]);

	Assert::same(reformat('SELECT id FROM author ORDER BY [id], [name] DESC'), $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // ?order
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author ORDER BY ?order', [
		'id' => true,
		'name' => false,
	]]);

	Assert::same(reformat('SELECT id FROM author ORDER BY [id], [name] DESC'), $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // mix of where & order
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE ? ORDER BY ?', [
		'id' => 1,
		'web' => 'web',
	], [
		'name' => false,
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] = 1) AND ([web] = ?) ORDER BY [name] DESC'), $sql);
	Assert::same(['web'], $params);
});


test(function () use ($preprocessor) { // missing parameters
	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT id FROM author WHERE id =? OR id = ?', 11]);
	}, Nette\InvalidArgumentException::class, 'There are more placeholders than passed parameters.');

	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT id FROM author WHERE id =', new SqlLiteral('? OR ?name = ?', [11]), 'id', 12]);
	}, Nette\InvalidArgumentException::class, 'There are more placeholders than passed parameters.');
});


test(function () use ($preprocessor) { // extra parameters
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


test(function () use ($preprocessor) { // unknown placeholder
	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT ?test', 11]);
	}, Nette\InvalidArgumentException::class, 'Unknown placeholder ?test.');
});


test(function () use ($preprocessor) { // SqlLiteral
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE id =', new SqlLiteral('? OR ?name = ?', [11, 'id', 12])]);
	Assert::same(reformat('SELECT id FROM author WHERE id = 11 OR [id] = 12'), $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE', new SqlLiteral('id=11'), 'OR', new SqlLiteral('id=?', [12])]);
	Assert::same('SELECT id FROM author WHERE id=11 OR id=12', $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // and
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE', [
		'id' => new SqlLiteral('NULL'),
		'born' => [1, 2, new SqlLiteral('3+1')],
		'web' => new SqlLiteral('NOW()'),
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NULL) AND ([born] IN (1, 2, 3+1)) AND ([web] = NOW())'), $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // empty and
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE', []]);

	Assert::same(reformat('SELECT id FROM author WHERE 1=1'), $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // ?and
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE ?and', [
		'id' => null,
		'born' => [1, 2],
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NULL) AND ([born] IN (1, 2))'), $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor) { // ?or
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE ?or', [
		'id' => null,
		'born' => [1, 2],
	]]);

	Assert::same(reformat('SELECT id FROM author WHERE ([id] IS NULL) OR ([born] IN (1, 2))'), $sql);
	Assert::same([], $params);
});


test(function () use ($preprocessor, $driverName) { // date time
	list($sql, $params) = $preprocessor->process(['SELECT ?', [new DateTime('2011-11-11')]]);
	Assert::same(reformat([
		'sqlite' => 'SELECT 1320966000',
		'sqlsrv' => "SELECT '2011-11-11T00:00:00'",
		"SELECT '2011-11-11 00:00:00'",
	]), $sql);
	Assert::same([], $params);


	if ($driverName === 'mysql') {
		$interval = new DateInterval('PT26H8M10S');
		$interval->invert = true;
		list($sql, $params) = $preprocessor->process(['SELECT ?', [$interval]]);
		Assert::same(reformat("SELECT '-26:08:10'"), $sql);
	}


	Assert::same([], $params);
	list($sql, $params) = $preprocessor->process(['SELECT ?', [new DateTimeImmutable('2011-11-11')]]);
	Assert::same(reformat([
		'sqlite' => 'SELECT 1320966000',
		'sqlsrv' => "SELECT '2011-11-11T00:00:00'",
		"SELECT '2011-11-11 00:00:00'",
	]), $sql);
});


test(function () use ($preprocessor) { // insert
	list($sql, $params) = $preprocessor->process(['INSERT INTO author',
		['name' => 'Catelyn Stark', 'born' => new DateTime('2011-11-11')],
	]);

	Assert::same(reformat([
		'sqlite' => 'INSERT INTO author ([name], [born]) VALUES (?, 1320966000)',
		'sqlsrv' => "INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11T00:00:00')",
		"INSERT INTO author ([name], [born]) VALUES (?, '2011-11-11 00:00:00')",
	]), $sql);
	Assert::same(['Catelyn Stark'], $params);


	list($sql, $params) = $preprocessor->process(["\r\n  INSERT INTO author",
		['name' => 'Catelyn Stark'],
	]);
	Assert::same(reformat("\r\n  INSERT INTO author ([name]) VALUES (?)"), $sql);
	Assert::same(['Catelyn Stark'], $params);


	list($sql, $params) = $preprocessor->process(['REPLACE author ?',
		['name' => 'Catelyn Stark'],
	]);
	Assert::same(reformat('REPLACE author ([name]) VALUES (?)'), $sql);
	Assert::same(['Catelyn Stark'], $params);


	list($sql, $params) = $preprocessor->process(['/* comment */  INSERT INTO author',
		['name' => 'Catelyn Stark'],
	]);
	Assert::same(reformat('/* comment */  INSERT INTO author [name]=?'), $sql); // autodetection not used
	Assert::same(['Catelyn Stark'], $params);
});


test(function () use ($preprocessor) { // ?values
	list($sql, $params) = $preprocessor->process(['INSERT INTO update ?values',
		['name' => 'Catelyn Stark'],
	]);

	Assert::same(reformat('INSERT INTO update ([name]) VALUES (?)'), $sql);
	Assert::same(['Catelyn Stark'], $params);
});


test(function () use ($preprocessor) { // multi insert
	list($sql, $params) = $preprocessor->process(['INSERT INTO author', [
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


test(function () use ($preprocessor) { // multi insert ?values
	list($sql, $params) = $preprocessor->process(['INSERT INTO author ?values', [
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


test(function () use ($preprocessor) { // update
	list($sql, $params) = $preprocessor->process(['UPDATE author SET ?', [
		'id' => 12,
		'name' => new SqlLiteral('UPPER(?)', ['John Doe']),
		new SqlLiteral('UPPER(?) = ?', ['John', 'DOE']),
	]]);

	Assert::same(reformat('UPDATE author SET [id]=12, [name]=UPPER(?), UPPER(?) = ?'), $sql);
	Assert::same(['John Doe', 'John', 'DOE'], $params);


	list($sql, $params) = $preprocessor->process(["UPDATE author SET \n",
		['id' => 12, 'name' => 'John Doe'],
	]);
	Assert::same(reformat("UPDATE author SET \n [id]=12, [name]=?"), $sql);
	Assert::same(['John Doe'], $params);


	list($sql, $params) = $preprocessor->process(['UPDATE author SET',
		['id' => 12, 'name' => 'John Doe'],
	]);
	Assert::same(reformat('UPDATE author SET [id]=12, [name]=?'), $sql);
	Assert::same(['John Doe'], $params);


	list($sql, $params) = $preprocessor->process(['UPDATE author SET a=1,',
		['id' => 12, 'name' => 'John Doe'],
	]);
	Assert::same(reformat('UPDATE author SET a=1, [id]=12, [name]=?'), $sql);
	Assert::same(['John Doe'], $params);
});


test(function () use ($preprocessor) { // ?set
	list($sql, $params) = $preprocessor->process(['UPDATE insert SET ?set',
		['id' => 12, 'name' => 'John Doe'],
	]);

	Assert::same(reformat('UPDATE insert SET [id]=12, [name]=?'), $sql);
	Assert::same(['John Doe'], $params);
});


test(function () use ($preprocessor) { // update +=
	list($sql, $params) = $preprocessor->process(['UPDATE author SET ?',
		['id+=' => 1, 'id-=' => -1],
	]);

	Assert::same(reformat('UPDATE author SET [id]=[id] + 1, [id]=[id] - -1'), $sql);
});


test(function () use ($preprocessor) { // insert & update
	list($sql, $params) = $preprocessor->process(['INSERT INTO author ? ON DUPLICATE KEY UPDATE ?',
		['id' => 12, 'name' => 'John Doe'],
		['web' => 'http://nette.org', 'name' => 'Dave Lister'],
	]);

	Assert::same(reformat('INSERT INTO author ([id], [name]) VALUES (12, ?) ON DUPLICATE KEY UPDATE [web]=?, [name]=?'), $sql);
	Assert::same(['John Doe', 'http://nette.org', 'Dave Lister'], $params);
});


test(function () use ($preprocessor) { // invalid usage of ?and, ...
	foreach (['?and', '?or', '?set', '?values', '?order'] as $mode) {
		Assert::exception(function () use ($preprocessor, $mode) {
			$preprocessor->process([$mode, 'string']);
		}, Nette\InvalidArgumentException::class, "Placeholder $mode expects array or Traversable object, string given.");
	}

	Assert::exception(function () use ($preprocessor) {
		$preprocessor->process(['SELECT ?name', ['id', 'table.id']]);
	}, Nette\InvalidArgumentException::class, 'Placeholder ?name expects string, array given.');
});


test(function () use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE ?or', [
		new SqlLiteral('max > ?', [10]),
		new SqlLiteral('min < ?', [20]),
	]]);
	Assert::same(reformat('SELECT id FROM author WHERE (max > 10) OR (min < 20)'), $sql);
});


test(function () use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(['SELECT id FROM author WHERE', new SqlLiteral('?or', [[
		new SqlLiteral('?and', [['a' => 1, 'b' => 2]]),
		new SqlLiteral('?and', [['c' => 3, 'd' => 4]]),
	]])]);
	Assert::same(reformat('SELECT id FROM author WHERE (([a] = 1) AND ([b] = 2)) OR (([c] = 3) AND ([d] = 4))'), $sql);
	Assert::same([], $params);
});


class ToString
{
	public function __toString()
	{
		return 'hello';
	}
}

test(function () use ($preprocessor) { // object
	list($sql, $params) = $preprocessor->process(['SELECT ?', new ToString]);
	Assert::same('SELECT ?', $sql);
	Assert::same(['hello'], $params);
});


Assert::exception(function () use ($preprocessor) { // object
	$preprocessor->process(['SELECT ?', new stdClass]);
}, Nette\InvalidArgumentException::class, 'Unexpected type of parameter: stdClass');


test(function () use ($preprocessor) { // resource
	list($sql, $params) = $preprocessor->process(['SELECT ?', $res = fopen(__FILE__, 'r')]);
	Assert::same('SELECT ?', $sql);
	Assert::same([$res], $params);
});
