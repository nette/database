<?php

/**
 * Test: Nette\Database\SqlPreprocessor
 * @dataProvider? databases.ini
 */

use Tester\Assert;
use Nette\Database\SqlLiteral;

require __DIR__ . '/connect.inc.php'; // create $connection


$preprocessor = new Nette\Database\SqlPreprocessor($connection);

test(function() use ($preprocessor) { // basic
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE id = ?', 11));
	Assert::same( 'SELECT id FROM author WHERE id = 11', $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // arg without placeholder
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE id =', 11));
	Assert::same( 'SELECT id FROM author WHERE id = 11', $sql );
	Assert::same( array(), $params );

	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE id =', '11'));
	Assert::same( "SELECT id FROM author WHERE id = '11'", $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE id = ? OR id = ?', 11, 12));
	Assert::same( 'SELECT id FROM author WHERE id = 11 OR id = 12', $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE id = ?', 11, 'OR id = ?', 12));
	Assert::same( 'SELECT id FROM author WHERE id = 11 OR id = 12', $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE id =', '?', 11, 'OR id = ?', 12));
	Assert::same( 'SELECT id FROM author WHERE id = 11 OR id = 12', $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE id =', '? OR id = ?', 11, 12));
	Assert::same( 'SELECT id FROM author WHERE id = 11 OR id = 12', $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // IN
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE id IN (?)', array(10, 11)));
	Assert::same( 'SELECT id FROM author WHERE id IN (10, 11)', $sql );
	Assert::same( array(), $params );

	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE (id, name) IN (?)', array(array(10, 'a'), array(11, 'b'))));
	Assert::same( "SELECT id FROM author WHERE (id, name) IN ((10, 'a'), (11, 'b'))", $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // ?name
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE ?name = ? OR ?name = ?', 'id', 12, 'table.number', 23));
	Assert::same( reformat('SELECT id FROM author WHERE [id] = 12 OR [table].[number] = 23'), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // comments
	list($sql, $params) = $preprocessor->process(array("SELECT id --?\nFROM author WHERE id = ?", 11));
	Assert::same( "SELECT id --?\nFROM author WHERE id = 11", $sql );
	Assert::same( array(), $params );

	list($sql, $params) = $preprocessor->process(array("SELECT id /* ? \n */FROM author WHERE id = ? --*/", 11));
	Assert::same( "SELECT id /* ? \n */FROM author WHERE id = 11 --*/", $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // strings
	list($sql, $params) = $preprocessor->process(array("SELECT id, '?' FROM author WHERE id = ?", 11));
	Assert::same( "SELECT id, '?' FROM author WHERE id = 11", $sql );
	Assert::same( array(), $params );

	list($sql, $params) = $preprocessor->process(array('SELECT id, "?" FROM author WHERE id = ?', 11));
	Assert::same( 'SELECT id, "?" FROM author WHERE id = 11', $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // where
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE', array(
		'id' => NULL,
		'x.name <>' => 'a',
		'born' => array(NULL, 1, 2, 3),
		'web' => array(),
	)));

	Assert::same( reformat("SELECT id FROM author WHERE ([id] IS NULL) AND ([x].[name] <> 'a') AND ([born] IN (NULL, 1, 2, 3)) AND (1=0)"), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // tuples
	list($sql, $params) = $preprocessor->process(array('SELECT * FROM book_tag WHERE (book_id, tag_id) IN (?)', array(
		array(1, 2),
		array(3, 4),
		array(5, 6),
	)));

	Assert::same( reformat("SELECT * FROM book_tag WHERE (book_id, tag_id) IN ((1, 2), (3, 4), (5, 6))"), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // order
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author ORDER BY', array(
		'id' => TRUE,
		'name' => FALSE,
	)));

	Assert::same( reformat('SELECT id FROM author ORDER BY [id], [name] DESC'), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // ?order
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author ORDER BY ?order', array(
		'id' => TRUE,
		'name' => FALSE,
	)));

	Assert::same( reformat('SELECT id FROM author ORDER BY [id], [name] DESC'), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // missing parameters
	Assert::exception(function() use ($preprocessor) {
		$preprocessor->process(array('SELECT id FROM author WHERE id =', '? OR id = ?', 11));
	}, 'Nette\InvalidArgumentException', 'There are more placeholders than passed parameters.');

	Assert::exception(function() use ($preprocessor) {
		$preprocessor->process(array('SELECT id FROM author WHERE id =', new SqlLiteral('? OR ?name = ?', array(11)), 'id', 12));
	}, 'Nette\InvalidArgumentException', 'There are more placeholders than passed parameters.');
});


test(function() use ($preprocessor) { // extra parameters
	Assert::exception(function() use ($preprocessor) {
		$preprocessor->process(array('SELECT id FROM author WHERE id =', 11, 12));
	}, 'Nette\InvalidArgumentException', 'There are more parameters than placeholders.');

	Assert::exception(function() use ($preprocessor) {
		$preprocessor->process(array('SELECT id FROM author WHERE id =?', 11, 12));
	}, 'Nette\InvalidArgumentException', 'There are more parameters than placeholders.');
});


test(function() use ($preprocessor) { // unknown placeholder
	Assert::exception(function() use ($preprocessor) {
		$preprocessor->process(array('SELECT ?test', 11));
	}, 'Nette\InvalidArgumentException', 'Unknown placeholder ?test.');
});


test(function() use ($preprocessor) { // SqlLiteral
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE id =', new SqlLiteral('? OR ?name = ?', array(11, 'id', 12)) ));
	Assert::same( reformat('SELECT id FROM author WHERE id = 11 OR [id] = 12'), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE', new SqlLiteral('id=11'), 'OR', new SqlLiteral('id=?', array(12))));
	Assert::same( 'SELECT id FROM author WHERE id=11 OR id=12', $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // and
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE', array(
		'id' => new SqlLiteral('NULL'),
		'born' => array(1, 2, new SqlLiteral('3+1')),
		'web' => new SqlLiteral('NOW()'),
	)));

	Assert::same( reformat('SELECT id FROM author WHERE ([id] IS NULL) AND ([born] IN (1, 2, 3+1)) AND ([web] = NOW())'), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // empty and
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE', array()));

	Assert::same( reformat('SELECT id FROM author WHERE 1=1'), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // ?and
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE ?and', array(
		'id' => NULL,
		'born' => array(1, 2),
	)));

	Assert::same( reformat('SELECT id FROM author WHERE ([id] IS NULL) AND ([born] IN (1, 2))'), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // ?or
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE ?or', array(
		'id' => NULL,
		'born' => array(1, 2),
	)));

	Assert::same( reformat('SELECT id FROM author WHERE ([id] IS NULL) OR ([born] IN (1, 2))'), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor, $driverName) { // insert
	list($sql, $params) = $preprocessor->process(array('INSERT INTO author',
		array('name' => 'Catelyn Stark', 'born' => new DateTime('2011-11-11')),
	));

	Assert::same( reformat(array(
		'sqlite' => "INSERT INTO author ([name], [born]) VALUES ('Catelyn Stark', 1320966000)",
		"INSERT INTO author ([name], [born]) VALUES ('Catelyn Stark', '2011-11-11 00:00:00')",
	)), $sql );
	Assert::same( array(), $params );


	list($sql, $params) = $preprocessor->process(array("\r\n  INSERT INTO author",
		array('name' => 'Catelyn Stark'),
	));
	Assert::same( reformat("\r\n  INSERT INTO author ([name]) VALUES ('Catelyn Stark')"), $sql );


	list($sql, $params) = $preprocessor->process(array('REPLACE author ?',
		array('name' => 'Catelyn Stark'),
	));
	Assert::same( reformat("REPLACE author ([name]) VALUES ('Catelyn Stark')"), $sql );


	list($sql, $params) = $preprocessor->process(array("/* comment */  INSERT INTO author",
		array('name' => 'Catelyn Stark'),
	));
	Assert::same( reformat("/* comment */  INSERT INTO author [name]='Catelyn Stark'"), $sql ); // autodetection not used
});


test(function() use ($preprocessor, $driverName) { // ?values
	list($sql, $params) = $preprocessor->process(array('INSERT INTO update ?values',
		array('name' => 'Catelyn Stark'),
	));

	Assert::same( reformat("INSERT INTO update ([name]) VALUES ('Catelyn Stark')"), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor, $driverName) { // multi insert
	list($sql, $params) = $preprocessor->process(array('INSERT INTO author', array(
		array('name' => 'Catelyn Stark', 'born' => new DateTime('2011-11-11')),
		array('name' => 'Sansa Stark', 'born' => new DateTime('2021-11-11'))
	)));

	Assert::same( reformat(array(
		'sqlite' => "INSERT INTO author ([name], [born]) SELECT 'Catelyn Stark', 1320966000 UNION ALL SELECT 'Sansa Stark', 1636585200",
		"INSERT INTO author ([name], [born]) VALUES ('Catelyn Stark', '2011-11-11 00:00:00'), ('Sansa Stark', '2021-11-11 00:00:00')",
	)), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor, $driverName) { // multi insert ?values
	list($sql, $params) = $preprocessor->process(array('INSERT INTO author ?values', array(
		array('name' => 'Catelyn Stark', 'born' => new DateTime('2011-11-11')),
		array('name' => 'Sansa Stark', 'born' => new DateTime('2021-11-11'))
	)));

	Assert::same( reformat(array(
		'sqlite' => "INSERT INTO author ([name], [born]) SELECT 'Catelyn Stark', 1320966000 UNION ALL SELECT 'Sansa Stark', 1636585200",
		"INSERT INTO author ([name], [born]) VALUES ('Catelyn Stark', '2011-11-11 00:00:00'), ('Sansa Stark', '2021-11-11 00:00:00')",
	)), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // update
	list($sql, $params) = $preprocessor->process(array('UPDATE author SET ?', array(
		'id' => 12,
		'name' => new SqlLiteral('UPPER(?)', array('John Doe')),
		new SqlLiteral('UPPER(?) = ?', array('John', 'DOE')),
	)));

	Assert::same( reformat("UPDATE author SET [id]=12, [name]=UPPER('John Doe'), UPPER('John') = 'DOE'"), $sql );
	Assert::same( array(), $params );


	list($sql, $params) = $preprocessor->process(array("UPDATE author SET \n",
		array('id' => 12, 'name' => 'John Doe'),
	));
	Assert::same( reformat("UPDATE author SET \n [id]=12, [name]='John Doe'"), $sql );


	list($sql, $params) = $preprocessor->process(array('UPDATE author SET',
		array('id' => 12, 'name' => 'John Doe'),
	));
	Assert::same( reformat("UPDATE author SET [id]=12, [name]='John Doe'"), $sql );


	list($sql, $params) = $preprocessor->process(array('UPDATE author SET a=1,',
		array('id' => 12, 'name' => 'John Doe'),
	));
	Assert::same( reformat("UPDATE author SET a=1, [id]=12, [name]='John Doe'"), $sql );
});


test(function() use ($preprocessor) { // ?set
	list($sql, $params) = $preprocessor->process(array('UPDATE insert SET ?set',
		array('id' => 12, 'name' => 'John Doe'),
	));

	Assert::same( reformat("UPDATE insert SET [id]=12, [name]='John Doe'"), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // update +=
	list($sql, $params) = $preprocessor->process(array('UPDATE author SET ?',
		array('id+=' => 1, 'id-=' => -1),
	));

	Assert::same( reformat("UPDATE author SET [id]=[id] + 1, [id]=[id] - -1"), $sql );
});


test(function() use ($preprocessor, $driverName) { // insert & update
	list($sql, $params) = $preprocessor->process(array('INSERT INTO author ? ON DUPLICATE KEY UPDATE ?',
		array('id' => 12, 'name' => 'John Doe'),
		array('web' => 'http://nette.org', 'name' => 'Dave Lister'),
	));

	Assert::same( reformat("INSERT INTO author ([id], [name]) VALUES (12, 'John Doe') ON DUPLICATE KEY UPDATE [web]='http://nette.org', [name]='Dave Lister'"), $sql );
	Assert::same( array(), $params );
});


test(function() use ($preprocessor) { // invalid usage of ?and, ...
	foreach (array('?and', '?or', '?set', '?values', '?order') as $mode) {
		Assert::exception(function() use ($preprocessor, $mode) {
			$preprocessor->process(array($mode, 'string'));
		}, 'Nette\InvalidArgumentException', "Placeholder $mode expects array or Traversable object, string given.");
	}

	Assert::exception(function() use ($preprocessor) {
		$preprocessor->process(array('SELECT ?name', array('id', 'table.id')));
	}, 'Nette\InvalidArgumentException', 'Placeholder ?name expects string, array given.');
});


test(function() use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE ?or', array(
		new SqlLiteral('max > ?', array(10)),
		new SqlLiteral('min < ?', array(20)),
	)));
	Assert::same( reformat('SELECT id FROM author WHERE (max > 10) OR (min < 20)'), $sql );
});


test(function() use ($preprocessor) {
	list($sql, $params) = $preprocessor->process(array('SELECT id FROM author WHERE', new SqlLiteral('?or', array(array(
		new SqlLiteral('?and', array(array('a' => 1, 'b' => 2))),
		new SqlLiteral('?and', array(array('c' => 3, 'd' => 4))),
	)))));
	Assert::same( reformat('SELECT id FROM author WHERE (([a] = 1) AND ([b] = 2)) OR (([c] = 3) AND ([d] = 4))'), $sql );
	Assert::same( array(), $params );
});
