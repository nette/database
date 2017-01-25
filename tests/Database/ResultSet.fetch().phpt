<?php

/**
 * Test: Nette\Database\ResultSet::fetch()
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test(function () use ($context, $driverName) {
	$res = $context->query('SELECT name, name FROM author');
	switch ($driverName) {
		case 'mysql':
			$message = "Found duplicate columns in database result set: 'name' (from author).";
			break;
		case 'pgsql':
			$message = "Found duplicate columns in database result set: 'name'%a%";
			break;
		case 'sqlite':
			$message = "Found duplicate columns in database result set: 'name' (from author).";
			break;
		case 'sqlsrv':
			$message = "Found duplicate columns in database result set: 'name'.";
			break;
		default:
			Assert::fail("Unsupported driver $driverName");
	}
	Assert::error(function () use ($res) {
		$res->fetch();
	}, E_USER_NOTICE, $message);

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
	$res = $context->query('SELECT book.id, author.id, author.name, translator.name FROM book JOIN author ON (author.id = book.author_id) JOIN author translator ON (translator.id = book.translator_id)');
	switch ($driverName) {
		case 'mysql':
			$message = "Found duplicate columns in database result set: 'id' (from book, author), 'name' (from author, translator).";
			break;
		case 'pgsql':
			$message = "Found duplicate columns in database result set: 'id'%a% 'name'%a%";
			break;
		case 'sqlite':
			$message = "Found duplicate columns in database result set: 'id' (from book, author), 'name' (from author).";
			break;
		case 'sqlsrv':
			$message = "Found duplicate columns in database result set: 'id', 'name'.";
			break;
		default:
			Assert::fail("Unsupported driver $driverName");
	}
	Assert::error(function() use ($res) {
		$res->fetch();
	}, E_USER_NOTICE, $message);
});
