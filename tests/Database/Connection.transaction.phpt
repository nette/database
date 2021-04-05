<?php

/**
 * Test: Nette\Database\Connection transaction methods.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Database\Connection;
use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('', function () use ($connection) {
	$connection->beginTransaction();
	$connection->query('DELETE FROM book');
	$connection->rollBack();

	Assert::same(3, $connection->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('', function () use ($connection) {
	Assert::exception(function () use ($connection) {
		$connection->transaction(function (Connection $connection) {
			$connection->query('DELETE FROM book');
			throw new Exception('my exception');
		});
	}, \Throwable::class, 'my exception');

	Assert::same(3, $connection->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('', function () use ($connection) {
	$connection->beginTransaction();
	$connection->query('DELETE FROM book');
	$connection->commit();

	Assert::null($connection->fetchField('SELECT id FROM book WHERE id = ', 3));
});
