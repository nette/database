<?php

/**
 * Test: Nette\Database\Conventions\DiscoveredConventions::getHasManyReference().
 */

declare(strict_types=1);

use Nette\Database\Conventions\DiscoveredConventions;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


// basic test singular
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn([
		'book' => ['author_id', 'translator_id'],
		'book_topics' => ['author_id'],
		'another' => ['author_id'],
	])->times(3);
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn([
		'book' => ['author_id'],
	]);

	$conventions = new DiscoveredConventions($structure);

	// match by key = target_table
	Assert::same(['book', 'author_id'], $conventions->getHasManyReference('author', 'book'));

	// match by key = target table
	Assert::same(['book_topics', 'author_id'], $conventions->getHasManyReference('author', 'book_topics'));

	// test too many column candidates
	Assert::exception(function () use ($conventions) {
		$conventions->getHasManyReference('author', 'boo');
	}, Nette\Database\Conventions\AmbiguousReferenceKeyException::class);

	// test one column candidate
	Assert::same(['book', 'author_id'], $conventions->getHasManyReference('author', 'boo'));
});


// basic test singular with schema
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('getHasManyReference')->with('public.author')->andReturn([
		'public.book' => ['author_id', 'translator_id'],
		'public.book_topics' => ['author_id'],
		'public.another' => ['author_id'],
	])->times(6);
	$structure->shouldReceive('getHasManyReference')->with('public.author')->andReturn([
		'public.book' => ['author_id'],
	])->times(2);

	$conventions = new DiscoveredConventions($structure);

	// match by key = target ns table
	Assert::same(['public.book', 'author_id'], $conventions->getHasManyReference('public.author', 'public.book'));

	// match by key = target table
	Assert::same(['public.book', 'author_id'], $conventions->getHasManyReference('public.author', 'book'));

	// match by key = target ns table
	Assert::same(['public.book_topics', 'author_id'], $conventions->getHasManyReference('public.author', 'public.book_topics'));

	// match by key = target table
	Assert::same(['public.book_topics', 'author_id'], $conventions->getHasManyReference('public.author', 'book_topics'));

	// test too many column candidates, ns table name
	Assert::exception(function () use ($conventions) {
		$conventions->getHasManyReference('public.author', 'public.boo');
	}, Nette\Database\Conventions\AmbiguousReferenceKeyException::class);

	// test too many column candidates
	Assert::exception(function () use ($conventions) {
		$conventions->getHasManyReference('public.author', 'boo');
	}, Nette\Database\Conventions\AmbiguousReferenceKeyException::class);

	// test one column candidate, ns table name
	Assert::same(['public.book', 'author_id'], $conventions->getHasManyReference('public.author', 'public.boo'));

	// test one column candidate
	Assert::same(['public.book', 'author_id'], $conventions->getHasManyReference('public.author', 'boo'));
});


// basic test plural
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('getHasManyReference')->with('authors')->andReturn([
		'books' => ['author_id', 'translator_id'],
	])->once();
	$structure->shouldReceive('getHasManyReference')->with('authors')->andReturn([
		'books' => ['author_id'],
		'book_topics' => ['author_id'],
		'another' => ['author_id'],
	])->twice();

	$conventions = new DiscoveredConventions($structure);

	Assert::same(['books', 'author_id'], $conventions->getHasManyReference('authors', 'books'));
	Assert::same(['book_topics', 'author_id'], $conventions->getHasManyReference('authors', 'topics'));

	// test too many candidates
	Assert::exception(function () use ($conventions) {
		$conventions->getHasManyReference('authors', 'boo');
	}, Nette\Database\Conventions\AmbiguousReferenceKeyException::class);
});


// tests column match with source table
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn([
		'book' => ['author_id', 'tran_id'],
	])->once();
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn([
		'book' => ['auth_id', 't_id'],
	])->once();
	$structure->shouldReceive('getHasManyReference')->with('authors')->andReturn([
		'books' => ['auth_id', 't_id'],
	])->once();

	$conventions = new DiscoveredConventions($structure);

	// prefer the match of source table with joining column
	Assert::same(['book', 'author_id'], $conventions->getHasManyReference('author', 'book'));
	// prefer the first possible column
	Assert::same(['book', 'auth_id'], $conventions->getHasManyReference('author', 'book'));

	// no propper match of key and target table name
	Assert::exception(function () use ($conventions) {
		$conventions->getHasManyReference('authors', 'book');
	}, Nette\Database\Conventions\AmbiguousReferenceKeyException::class);
});


// tests case insensivity and prefixes
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('getHasManyReference')->with('nAuthors')->andReturn([
		'nBooks' => ['authorId', 'translatorId'],
	])->once();

	$conventions = new DiscoveredConventions($structure);

	Assert::same(['nBooks', 'authorId'], $conventions->getHasManyReference('nAuthors', 'nbooks'));
});


// tests rebuilt
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('isRebuilt')->andReturn(FALSE);
	$structure->shouldReceive('rebuild');
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn([])->once();
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn([
		'book' => ['author_id', 'translator_id'],
	])->once();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(['book', 'author_id'], $conventions->getHasManyReference('author', 'book'));
});


// tests already rebuilt structure
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('isRebuilt')->andReturn(TRUE);
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn([])->once();

	$conventions = new DiscoveredConventions($structure);
	Assert::null($conventions->getHasManyReference('author', 'book'));
});


Mockery::close();
