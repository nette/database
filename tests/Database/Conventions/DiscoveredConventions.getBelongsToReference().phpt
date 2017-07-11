<?php

/**
 * Test: Nette\Database\Conventions\DiscoveredConventions::getBelongsToReference().
 */

use Nette\Database\Conventions\DiscoveredConventions;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


// basic test
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn([
		'author_id' => 'authors',
		'translator_id' => 'authors',
		'another_id' => 'another_table',
	])->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(['authors', 'author_id'], $conventions->getBelongsToReference('books', 'author'));
	Assert::same(['authors', 'translator_id'], $conventions->getBelongsToReference('books', 'translator'));
});

// basic test
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('getBelongsToReference')->with('public.books')->andReturn([
		'author_id' => 'public.authors',
		'translator_id' => 'public.authors',
		'another_id' => 'public.another_table',
	])->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(['public.authors', 'author_id'], $conventions->getBelongsToReference('public.books', 'author'));
	Assert::same(['public.authors', 'translator_id'], $conventions->getBelongsToReference('public.books', 'translator'));
});

// tests order of table columns with foreign keys
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn([
		'translator_id' => 'authors',
		'author_id' => 'authors',
	])->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(['authors', 'author_id'], $conventions->getBelongsToReference('books', 'author'));
	Assert::same(['authors', 'translator_id'], $conventions->getBelongsToReference('books', 'translator'));
});


// tests case insensivity
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn([
		'author_id' => 'authors',
		'translator_id' => 'authors',
	])->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(['authors', 'author_id'], $conventions->getBelongsToReference('books', 'Author'));
	Assert::same(['authors', 'translator_id'], $conventions->getBelongsToReference('books', 'Translator'));
});


// tests case insensivity and prefixes
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('getBelongsToReference')->with('nBooks')->andReturn([
		'authorId' => 'nAuthors',
		'translatorId' => 'nAuthors',
	])->twice();

	$conventions = new DiscoveredConventions($structure);

	Assert::same(['nAuthors', 'authorId'], $conventions->getBelongsToReference('nBooks', 'author'));
	Assert::same(['nAuthors', 'translatorId'], $conventions->getBelongsToReference('nBooks', 'translator'));
});


// tests rebuilt
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('isRebuilt')->andReturn(false);
	$structure->shouldReceive('rebuild');
	$structure->shouldReceive('getBelongsToReference')->andReturn([])->once();
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn([
		'author_id' => 'authors',
		'translator_id' => 'authors',
		'another_id' => 'another_table',
	])->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(['authors', 'author_id'], $conventions->getBelongsToReference('books', 'author'));
	Assert::same(['authors', 'translator_id'], $conventions->getBelongsToReference('books', 'translator'));
});


// tests already rebuilt structure
test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('isRebuilt')->andReturn(true);
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn([])->once();

	$conventions = new DiscoveredConventions($structure);
	Assert::null($conventions->getBelongsToReference('books', 'author'));
});


Mockery::close();
