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
	}, Throwable::class, 'my exception');

	Assert::same(3, $connection->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('', function () use ($connection) {
	$connection->beginTransaction();
	$connection->query('DELETE FROM book');
	$connection->commit();

	Assert::null($connection->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('nested transaction() call fail', function () use ($connection) {
	$base = (int) $connection->query('SELECT COUNT(*) AS cnt FROM author')->fetchField();

	Assert::exception(function () use ($connection) {
		$connection->transaction(function (Connection $connection) {
			$connection->query('INSERT INTO author', [
				'name' => 'A',
				'web' => '',
			]);

			$connection->transaction(function (Connection $connection2) {
				$connection2->query('INSERT INTO author', [
					'name' => 'B',
					'web' => '',
				]);
				throw new Exception('my exception');
			});
		});
	}, Throwable::class, 'my exception');

	Assert::same(0, $connection->query('SELECT COUNT(*) AS cnt FROM author')->fetchField() - $base);
});


test('nested transaction() call success', function () use ($connection) {
	$base = (int) $connection->query('SELECT COUNT(*) AS cnt FROM author')->fetchField();

	$connection->transaction(function (Connection $connection) {
		$connection->query('INSERT INTO author', [
			'name' => 'A',
			'web' => '',
		]);

		$connection->transaction(function (Connection $connection2) {
			$connection2->query('INSERT INTO author', [
				'name' => 'B',
				'web' => '',
			]);
		});
	});

	Assert::same(2, $connection->query('SELECT COUNT(*) AS cnt FROM author')->fetchField() - $base);
});


test('beginTransaction(), commit() & rollBack() calls are forbidden in transaction()', function () use ($connection) {
	Assert::exception(function () use ($connection) {
		$connection->transaction(function (Connection $connection) {
			$connection->beginTransaction();
		});
	}, LogicException::class, Connection::class . '::beginTransaction() call is forbidden inside a transaction() callback');

	Assert::exception(function () use ($connection) {
		$connection->transaction(function (Connection $connection) {
			$connection->commit();
		});
	}, LogicException::class, Connection::class . '::commit() call is forbidden inside a transaction() callback');

	Assert::exception(function () use ($connection) {
		$connection->transaction(function (Connection $connection) {
			$connection->rollBack();
		});
	}, LogicException::class, Connection::class . '::rollBack() call is forbidden inside a transaction() callback');
});
