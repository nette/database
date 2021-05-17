<?php

/**
 * @dataProvider? ../../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../../files/{$driverName}-nette_test1.sql");

test('', function () use ($explorer) {
	for ($i = 0; $i < 2; $i++) {
		$booksSelection = $explorer->table('book')->wherePrimary(1);
		$book = $booksSelection->fetch();
		$book->id;

		if ($i === 1) {
			$book->delete();
			Assert::exception(function () use ($book) {
				$book->toArray();
			}, Nette\InvalidStateException::class, "Database refetch failed; row with signature '1' does not exist!");
		}

		$booksSelection->__destruct();
	}
});

test('', function () use ($explorer) {
	for ($i = 0; $i < 2; $i++) {
		$booksSelection = $explorer->table('book')->wherePrimary(2);
		$book = $booksSelection->fetch();
		$book->id;

		if ($i === 1) {
			$book->getTable()->createSelectionInstance()
				->wherePrimary($book->id)
				->delete();

			Assert::exception(function () use ($book) {
				$book->toArray();
			}, Nette\InvalidStateException::class, "Database refetch failed; row with signature '2' does not exist!");
		}

		$booksSelection->__destruct();
	}
});

test('', function () use ($explorer) {
	$books = [];
	for ($i = 0; $i < 2; $i++) {
		$booksSelection = $explorer->table('book')->where('id IN ?', [3, 4])->order('id');
		foreach ($booksSelection as $book) {
			$books[] = $book->id;

			if ($i === 1) {
				$explorer->query('DELETE FROM book WHERE id = 4'); //After refetch second row is skipped
				$book->title; // cause refetch
			}

			$booksSelection->__destruct();
		}
	}
	Assert::same([
		3,
		4,
		3,
	], $books);
});

test('', function () use ($explorer) {
	for ($i = 0; $i < 2; $i++) {
		$booksSelection = $explorer->table('book')->wherePrimary(3);
		$book = $booksSelection->fetch();
		$book->id;

		if ($i === 1) {
			$explorer->query('DELETE FROM book WHERE id = 3');
			Assert::exception(function () use ($book) {
				$book->title;
			}, Nette\InvalidStateException::class, "Database refetch failed; row with signature '3' does not exist!");
		}

		$booksSelection->__destruct();
	}
});
