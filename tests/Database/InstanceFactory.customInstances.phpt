<?php

/**
 * Test: NNette\Database\Table\InstanceFactory custom instances.
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

class AuthorRow extends \Nette\Database\Table\ActiveRow
{
	public function getBooks()
	{
		return $this->related('book', 'author_id');
	}
}

class AuthorSelection extends \Nette\Database\Table\Selection {}
class AuthorGroupedSelection extends \Nette\Database\Table\GroupedSelection {}
class BookRow extends \Nette\Database\Table\ActiveRow {}
class BookSelection extends \Nette\Database\Table\Selection {}
class BookGroupedSelection extends \Nette\Database\Table\GroupedSelection {}

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");

$context->setInstanceFactory(new \Nette\Database\Table\InstanceFactory([
		'activeRow' => [
			'author' => 'AuthorRow',
			'book' => 'BookRow'
		],
		'selection' => [
			'author' => 'AuthorSelection',
			'book' => 'BookSelection'
		],
		'groupedSelection' => [
			'author' => 'AuthorGroupedSelection',
			'book' => 'BookGroupedSelection'
		]
	]
));

// Not custom instances (defaults)
test(function () use ($context) { // ActiveRow
	$row = $context->table('book_tag')->where('book_id', 2)->fetch();
	Assert::type(\Nette\Database\Table\ActiveRow::class, $row);
});

test(function () use ($context) { // Selection
	$row = $context->table('book_tag');
	Assert::type(\Nette\Database\Table\Selection::class, $row);
});

test(function () use ($context) { // GroupedSelection
	$row = $context->table('book')->wherePrimary(4)->fetch();
	$tags = $row->related('book_tag');
	Assert::type(\Nette\Database\Table\GroupedSelection::class, $tags);

	foreach ($tags as $tag) {
		Assert::type(\Nette\Database\Table\ActiveRow::class, $tag);
	}
});

// Custom instances
test(function () use ($context) { // custom ActiveRow
	$row = $context->table('author')->wherePrimary(11)->fetch();
	Assert::type(AuthorRow::class, $row);
});

test(function () use ($context) { // custom Selection
	$row = $context->table('author');
	Assert::type(AuthorSelection::class, $row);
});

test(function () use ($context) { // custom GroupedSelection
	$row = $context->table('author')->wherePrimary(11)->fetch();
	Assert::type(BookGroupedSelection::class, $row->getBooks());
});

test(function () use ($context) { // multi references
	$row = $context->table('author')->wherePrimary(11)->fetch();

	foreach ($row->getBooks() as $book) {
		Assert::type(BookRow::class, $book);
		Assert::type(AuthorRow::class, $book->ref('author', 'author_id'));
		Assert::type(AuthorRow::class, $book->author);

		$subBooks = $book->author->related('book');
		Assert::type(BookGroupedSelection::class, $subBooks);
		foreach ($book->author->related('book') as $subBook) {
			Assert::type(BookRow::class, $subBook);
			Assert::type(AuthorRow::class, $subBook->author);
			Assert::type(AuthorRow::class, $subBook->ref('author', 'author_id'));
		}
	}
});
