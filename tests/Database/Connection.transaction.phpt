<?php

/**
 * Test: Nette\Database\Connection transaction methods.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Database\Explorer;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('rolls back transaction after manual control', function () use ($connection) {
	$connection->beginTransaction();
	$connection->query('DELETE FROM book');
	$connection->rollBack();

	Assert::same(3, $connection->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('rolls back transaction on exception', function () use ($connection) {
	Assert::exception(
		fn() => $connection->transaction(function (Explorer $connection) {
			$connection->query('DELETE FROM book');
			throw new Exception('my exception');
		}),
		Throwable::class,
		'my exception',
	);

	Assert::same(3, $connection->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('commits transaction successfully', function () use ($connection) {
	$connection->beginTransaction();
	$connection->query('DELETE FROM book');
	$connection->commit();

	Assert::null($connection->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('nested transaction() call fail', function () use ($connection) {
	$base = (int) $connection->query('SELECT COUNT(*) AS cnt FROM author')->fetchField();

	Assert::exception(
		fn() => $connection->transaction(function (Explorer $connection) {
			$connection->query('INSERT INTO author', [
				'name' => 'A',
				'web' => '',
			]);

			$connection->transaction(function (Explorer $connection2) {
				$connection2->query('INSERT INTO author', [
					'name' => 'B',
					'web' => '',
				]);
				throw new Exception('my exception');
			});
		}),
		Throwable::class,
		'my exception',
	);

	Assert::same(0, $connection->query('SELECT COUNT(*) AS cnt FROM author')->fetchField() - $base);
});


test('nested transaction() call success', function () use ($connection) {
	$base = (int) $connection->query('SELECT COUNT(*) AS cnt FROM author')->fetchField();

	$connection->transaction(function (Explorer $connection) {
		$connection->query('INSERT INTO author', [
			'name' => 'A',
			'web' => '',
		]);

		$connection->transaction(fn() => $connection->query('INSERT INTO author', [
			'name' => 'B',
			'web' => '',
		]));
	});

	Assert::same(2, $connection->query('SELECT COUNT(*) AS cnt FROM author')->fetchField() - $base);
});


test('beginTransaction(), commit() & rollBack() calls are forbidden in transaction()', function () use ($connection) {
	Assert::exception(
		fn() => $connection->transaction(fn() => $connection->beginTransaction()),
		LogicException::class,
		Explorer::class . '::beginTransaction() call is forbidden inside a transaction() callback',
	);

	Assert::exception(
		fn() => $connection->transaction(fn() => $connection->commit()),
		LogicException::class,
		Explorer::class . '::commit() call is forbidden inside a transaction() callback',
	);

	Assert::exception(
		fn() => $connection->transaction(fn() => $connection->rollBack()),
		LogicException::class,
		Explorer::class . '::rollBack() call is forbidden inside a transaction() callback',
	);
});
