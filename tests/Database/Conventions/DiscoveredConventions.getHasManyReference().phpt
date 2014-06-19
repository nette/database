<?php

/**
 * Test: Nette\Database\Conventions\DiscoveredConventions::getHasManyReference().
 *
 * @author     Jan Skrasek
 */

use Nette\Database\Conventions\DiscoveredConventions;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


// basic test singular
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn(array(
		'book' => array('author_id', 'translator_id'),
		'book_topics' => array('author_id'),
		'another' => array('author_id'),
	))->times(3);
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn(array(
		'book' => array('author_id'),
	));

	$conventions = new DiscoveredConventions($structure);

	// match by key = target_table
	Assert::same(array('book', 'author_id'), $conventions->getHasManyReference('author', 'book'));

	// match by key = target table
	Assert::same(array('book_topics', 'author_id'), $conventions->getHasManyReference('author', 'book_topics'));

	// test too many column candidates
	Assert::throws(function() use ($conventions) {
		$conventions->getHasManyReference('author', 'boo');
	}, 'Nette\Database\Conventions\AmbiguousReferenceKeyException');

	// test one column candidate
	Assert::same(array('book', 'author_id'), $conventions->getHasManyReference('author', 'boo'));
});


// basic test plural
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('getHasManyReference')->with('authors')->andReturn(array(
		'books' => array('author_id', 'translator_id'),
	))->once();
	$structure->shouldReceive('getHasManyReference')->with('authors')->andReturn(array(
		'books' => array('author_id'),
		'book_topics' => array('author_id'),
		'another' => array('author_id'),
	))->twice();

	$conventions = new DiscoveredConventions($structure);

	Assert::same(array('books', 'author_id'), $conventions->getHasManyReference('authors', 'books'));
	Assert::same(array('book_topics', 'author_id'), $conventions->getHasManyReference('authors', 'topics'));

	// test too many candidates
	Assert::throws(function() use ($conventions) {
		$conventions->getHasManyReference('authors', 'boo');
	}, 'Nette\Database\Conventions\AmbiguousReferenceKeyException');
});


// tests column match with source table
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn(array(
		'book' => array('author_id', 'tran_id'),
	))->once();
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn(array(
		'book' => array('auth_id', 't_id'),
	))->once();
	$structure->shouldReceive('getHasManyReference')->with('authors')->andReturn(array(
		'books' => array('auth_id', 't_id'),
	))->once();

	$conventions = new DiscoveredConventions($structure);

	// prefer the match of source table with joining column
	Assert::same(array('book', 'author_id'), $conventions->getHasManyReference('author', 'book'));
	// prefer the first possible column
	Assert::same(array('book', 'auth_id'), $conventions->getHasManyReference('author', 'book'));

	// no propper match of key and target table name
	Assert::throws(function() use ($conventions) {
		$conventions->getHasManyReference('authors', 'book');
	}, 'Nette\Database\Conventions\AmbiguousReferenceKeyException');
});


// tests case insensivity and prefixes
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('getHasManyReference')->with('nAuthors')->andReturn(array(
		'nBooks' => array('authorId', 'translatorId'),
	))->once();

	$conventions = new DiscoveredConventions($structure);

	Assert::same(array('nBooks', 'authorId'), $conventions->getHasManyReference('nAuthors', 'nbooks'));
});


// tests rebuilt
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('isRebuilt')->andReturn(FALSE);
	$structure->shouldReceive('rebuild');
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn(array())->once();
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn(array(
		'book' => array('author_id', 'translator_id'),
	))->once();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(array('book', 'author_id'), $conventions->getHasManyReference('author', 'book'));
});


// tests already rebuilt structure
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('isRebuilt')->andReturn(TRUE);
	$structure->shouldReceive('getHasManyReference')->with('author')->andReturn(array())->once();

	$conventions = new DiscoveredConventions($structure);
	Assert::null($conventions->getHasManyReference('author', 'book'));
});


Mockery::close();
