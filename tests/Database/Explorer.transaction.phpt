<?php

/**
 * Test: Nette\Database\Connection transaction methods.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Database\Explorer;
use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('', function () use ($explorer) {
	$explorer->beginTransaction();
	$explorer->query('DELETE FROM book');
	$explorer->rollBack();

	Assert::same(3, $explorer->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('', function () use ($explorer) {
	Assert::exception(function () use ($explorer) {
		$explorer->transaction(function (Explorer $explorer) {
			$explorer->query('DELETE FROM book');
			throw new Exception('my exception');
		});
	}, Throwable::class, 'my exception');

	Assert::same(3, $explorer->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('', function () use ($explorer) {
	$explorer->beginTransaction();
	$explorer->query('DELETE FROM book');
	$explorer->commit();

	Assert::null($explorer->fetchField('SELECT id FROM book WHERE id = ', 3));
});
