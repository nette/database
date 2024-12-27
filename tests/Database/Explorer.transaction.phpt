<?php

/**
 * Test: Nette\Database\Connection transaction methods.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Database\Explorer;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('rolls back explorer transaction after manual control', function () use ($explorer) {
	$explorer->beginTransaction();
	$explorer->query('DELETE FROM book');
	$explorer->rollBack();

	Assert::same(3, $explorer->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('rolls back explorer transaction on exception', function () use ($explorer) {
	Assert::exception(
		fn() => $explorer->transaction(function (Explorer $explorer) {
			$explorer->query('DELETE FROM book');
			throw new Exception('my exception');
		}),
		Throwable::class,
		'my exception',
	);

	Assert::same(3, $explorer->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('commits explorer transaction successfully', function () use ($explorer) {
	$explorer->beginTransaction();
	$explorer->query('DELETE FROM book');
	$explorer->commit();

	Assert::null($explorer->fetchField('SELECT id FROM book WHERE id = ', 3));
});
