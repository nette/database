<?php

/**
 * Test: Nette\Database\Conventions\StaticConventions.
 */

use Nette\Database\Conventions\StaticConventions;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test(function () {
	$conventions = new StaticConventions;
	Assert::same('id', $conventions->getPrimary('book'));
	Assert::same(array('author', 'book_id'), $conventions->getHasManyReference('book', 'author'));
	Assert::same(array('translator', 'book_id'), $conventions->getHasManyReference('book', 'translator'));
	Assert::same(array('book', 'book_id'), $conventions->getBelongsToReference('author', 'book'));
});


test(function () {
	$conventions = new StaticConventions('%s_id');
	Assert::same('book_id', $conventions->getPrimary('book'));
});


test(function () {
	$conventions = new StaticConventions('id_%s', 'id_%s');
	Assert::same('id_book', $conventions->getPrimary('book'));
	Assert::same(array('author', 'id_book'), $conventions->getHasManyReference('book', 'author'));
	Assert::same(array('book', 'id_book'), $conventions->getBelongsToReference('author', 'book'));
});


test(function () {
	$conventions = new StaticConventions('%sId', '%sId');
	Assert::same('bookId', $conventions->getPrimary('book'));
	Assert::same(array('author', 'bookId'), $conventions->getHasManyReference('book', 'author'));
	Assert::same(array('book', 'bookId'), $conventions->getBelongsToReference('author', 'book'));
});


test(function () {
	$conventions = new StaticConventions('id', '%2$s_%1$s_id');
	Assert::same('id', $conventions->getPrimary('book'));
	Assert::same(array('author', 'author_book_id'), $conventions->getHasManyReference('book', 'author'));
	Assert::same(array('book', 'author_book_id'), $conventions->getBelongsToReference('author', 'book'));
});


test(function () {
	$conventions = new StaticConventions('id', '%s_id', 'prefix_%s');
	Assert::same(array('prefix_author', 'book_id'), $conventions->getHasManyReference('prefix_book', 'author'));
	Assert::same(array('prefix_book', 'book_id'), $conventions->getBelongsToReference('prefix_author', 'book'));
});


test(function () {
	$conventions = new StaticConventions('id', '%s_id', '%s_suffix');
	Assert::same(array('author_suffix', 'book_id'), $conventions->getHasManyReference('book_suffix', 'author'));
	Assert::same(array('book_suffix', 'book_id'), $conventions->getBelongsToReference('author_suffix', 'book'));
});
