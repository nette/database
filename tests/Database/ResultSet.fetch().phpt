<?php

/**
 * Test: Nette\Database\ResultSet::fetch()
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test(function () use ($context) {
	$res = $context->query('SELECT name, name FROM author');

	Assert::error(function () use ($res) {
		$res->fetch();
	}, E_USER_NOTICE);

	$res->fetch();
});


test(function () use ($context, $driverName) { // tests closeCursor()
	if ($driverName === 'mysql') {
		$context->query('CREATE DEFINER = CURRENT_USER PROCEDURE `testProc`(IN param int(10) unsigned) BEGIN SELECT * FROM book WHERE id != param; END;;');
		$context->getConnection()->getPdo()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);

		$res = $context->query('CALL testProc(1)');
		foreach ($res as $row) {}

		$res = $context->query('SELECT * FROM book');
		foreach ($res as $row) {}
	}
});

test(function () use ($context, $driverName) {

	$result = $context->query('SELECT book.id, author.id, author.name, translator.name FROM book JOIN author ON (author.id = book.author_id) JOIN author translator ON (translator.id = book.translator_id)');
	//Found duplicate columns in database result set: 'id' from book, author; 'name' from author, translator.
	Assert::error(function() use($result) {
		iterator_to_array($result);
	}, E_USER_NOTICE);
});
