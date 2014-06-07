<?php

/**
 * Test: Nette\Database\Conventions\DiscoveredConventions::getBelongsToReference().
 *
 * @author     Jan Skrasek
 */

use Nette\Database\Conventions\DiscoveredConventions;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


// basic test
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn(array(
		'author_id' => 'authors',
		'translator_id' => 'authors',
		'another_id' => 'another_table',
	))->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(array('authors', 'author_id'), $conventions->getBelongsToReference('books', 'author'));
	Assert::same(array('authors', 'translator_id'), $conventions->getBelongsToReference('books', 'translator'));
});


// tests order of table columns with foreign keys
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn(array(
		'translator_id' => 'authors',
		'author_id' => 'authors',
	))->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(array('authors', 'author_id'), $conventions->getBelongsToReference('books', 'author'));
	Assert::same(array('authors', 'translator_id'), $conventions->getBelongsToReference('books', 'translator'));
});


// tests case insensivity
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn(array(
		'author_id' => 'authors',
		'translator_id' => 'authors',
	))->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(array('authors', 'author_id'), $conventions->getBelongsToReference('books', 'Author'));
	Assert::same(array('authors', 'translator_id'), $conventions->getBelongsToReference('books', 'Translator'));
});


// tests case insensivity and prefixes
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('getBelongsToReference')->with('nBooks')->andReturn(array(
		'authorId' => 'nAuthors',
		'translatorId' => 'nAuthors',
	))->twice();

	$conventions = new DiscoveredConventions($structure);

	Assert::same(array('nAuthors', 'authorId'), $conventions->getBelongsToReference('nBooks', 'author'));
	Assert::same(array('nAuthors', 'translatorId'), $conventions->getBelongsToReference('nBooks', 'translator'));
});


// tests rebuilt
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('isRebuilt')->andReturn(FALSE);
	$structure->shouldReceive('rebuild');
	$structure->shouldReceive('getBelongsToReference')->andReturn(array())->once();
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn(array(
		'author_id' => 'authors',
		'translator_id' => 'authors',
		'another_id' => 'another_table',
	))->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(array('authors', 'author_id'), $conventions->getBelongsToReference('books', 'author'));
	Assert::same(array('authors', 'translator_id'), $conventions->getBelongsToReference('books', 'translator'));
});


// tests already rebuilt structure
test(function() {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('isRebuilt')->andReturn(TRUE);
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn(array())->once();

	$conventions = new DiscoveredConventions($structure);
	Assert::null($conventions->getBelongsToReference('books', 'author'));
});
