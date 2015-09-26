<?php

/**
 * @dataProvider? ../../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");

test(function () use ($context) {

	for ($i=0; $i<2; $i++) {
		$booksSelection = $context->table('book')->wherePrimary(1);
		$book = $booksSelection->fetch();
		$book->id;

		if ($i === 1) {
			$book->delete();
			Assert::exception(function () use ($book) {
				$book->toArray();
			}, 'Nette\InvalidStateException', 'Database refetch failed; row does not exist!');
		}

		$booksSelection->__destruct();
	}
});

test(function () use ($context) {

	for ($i=0; $i<2; $i++) {
		$booksSelection = $context->table('book')->wherePrimary(2);
		$book = $booksSelection->fetch();
		$book->id;

		if ($i === 1) {
			$book->getTable()->createSelectionInstance()
				->wherePrimary($book->id)
				->delete();

			Assert::exception(function () use ($book) {
				$book->toArray();
			}, 'Nette\InvalidStateException', 'Database refetch failed; row does not exist!');
		}

		$booksSelection->__destruct();
	}
});

test(function () use ($context) {

	for ($i=0; $i<2; $i++) {
		$booksSelection = $context->table('book')->wherePrimary(3);
		$book = $booksSelection->fetch();
		$book->id;

		if ($i === 1) {
			$context->query('DELETE FROM book WHERE id = 3');
			Assert::exception(function () use ($book) {
				$book->title;
			}, 'Nette\InvalidStateException', 'Database refetch failed; row does not exist!');
		}

		$booksSelection->__destruct();
	}
});
