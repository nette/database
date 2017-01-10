<?php

/**
 * Test: Nette\Database\Conventions\StaticConventions.
 */

declare(strict_types=1);

use Nette\Database\Conventions\StaticConventions;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test(function () {
	$conventions = new StaticConventions;
	Assert::same('id', $conventions->getPrimary('book'));
	Assert::same(['author', 'book_id'], $conventions->getHasManyReference('book', 'author'));
	Assert::same(['translator', 'book_id'], $conventions->getHasManyReference('book', 'translator'));
	Assert::same(['book', 'book_id'], $conventions->getBelongsToReference('author', 'book'));
});


test(function () {
	$conventions = new StaticConventions('%s_id');
	Assert::same('book_id', $conventions->getPrimary('book'));
});


test(function () {
	$conventions = new StaticConventions('id_%s', 'id_%s');
	Assert::same('id_book', $conventions->getPrimary('book'));
	Assert::same(['author', 'id_book'], $conventions->getHasManyReference('book', 'author'));
	Assert::same(['book', 'id_book'], $conventions->getBelongsToReference('author', 'book'));
});


test(function () {
	$conventions = new StaticConventions('%sId', '%sId');
	Assert::same('bookId', $conventions->getPrimary('book'));
	Assert::same(['author', 'bookId'], $conventions->getHasManyReference('book', 'author'));
	Assert::same(['book', 'bookId'], $conventions->getBelongsToReference('author', 'book'));
});


test(function () {
	$conventions = new StaticConventions('id', '%2$s_%1$s_id');
	Assert::same('id', $conventions->getPrimary('book'));
	Assert::same(['author', 'author_book_id'], $conventions->getHasManyReference('book', 'author'));
	Assert::same(['book', 'author_book_id'], $conventions->getBelongsToReference('author', 'book'));
});


test(function () {
	$conventions = new StaticConventions('id', '%s_id', 'prefix_%s');
	Assert::same(['prefix_author', 'book_id'], $conventions->getHasManyReference('prefix_book', 'author'));
	Assert::same(['prefix_book', 'book_id'], $conventions->getBelongsToReference('prefix_author', 'book'));
});


test(function () {
	$conventions = new StaticConventions('id', '%s_id', '%s_suffix');
	Assert::same(['author_suffix', 'book_id'], $conventions->getHasManyReference('book_suffix', 'author'));
	Assert::same(['book_suffix', 'book_id'], $conventions->getBelongsToReference('author_suffix', 'book'));
});
