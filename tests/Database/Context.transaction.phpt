<?php

/**
 * Test: Nette\Database\Connection transaction methods.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('', function () use ($context) {
	$context->beginTransaction();
	$context->query('DELETE FROM book');
	$context->rollBack();

	Assert::same(3, $context->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('', function () use ($context) {
	Assert::exception(function () use ($context) {
		$context->transaction(function () use ($context) {
			$context->query('DELETE FROM book');
			throw new Exception('my exception');
		});
	}, \Throwable::class, 'my exception');

	Assert::same(3, $context->fetchField('SELECT id FROM book WHERE id = ', 3));
});


test('', function () use ($context) {
	$context->beginTransaction();
	$context->query('DELETE FROM book');
	$context->commit();

	Assert::null($context->fetchField('SELECT id FROM book WHERE id = ', 3));
});
