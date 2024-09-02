<?php

/**
 * Test: Nette\Database\Conventions\DiscoveredConventions::getBelongsToReference().
 */

declare(strict_types=1);

use Nette\Database\Conventions\DiscoveredConventions;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test('basic test', function () {
	$structure = Mockery::mock(Nette\Database\Structure::class);
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn([
		'author_id' => 'authors',
		'translator_id' => 'authors',
		'another_id' => 'another_table',
	])->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(['authors', 'author_id'], $conventions->getBelongsToReference('books', 'author'));
	Assert::same(['authors', 'translator_id'], $conventions->getBelongsToReference('books', 'translator'));
});

test('basic test', function () {
	$structure = Mockery::mock(Nette\Database\Structure::class);
	$structure->shouldReceive('getBelongsToReference')->with('public.books')->andReturn([
		'author_id' => 'public.authors',
		'translator_id' => 'public.authors',
		'another_id' => 'public.another_table',
	])->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(['public.authors', 'author_id'], $conventions->getBelongsToReference('public.books', 'author'));
	Assert::same(['public.authors', 'translator_id'], $conventions->getBelongsToReference('public.books', 'translator'));
});

test('tests order of table columns with foreign keys', function () {
	$structure = Mockery::mock(Nette\Database\Structure::class);
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn([
		'translator_id' => 'authors',
		'author_id' => 'authors',
	])->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(['authors', 'author_id'], $conventions->getBelongsToReference('books', 'author'));
	Assert::same(['authors', 'translator_id'], $conventions->getBelongsToReference('books', 'translator'));
});


test('tests case insensivity', function () {
	$structure = Mockery::mock(Nette\Database\Structure::class);
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn([
		'author_id' => 'authors',
		'translator_id' => 'authors',
	])->twice();

	$conventions = new DiscoveredConventions($structure);
	Assert::same(['authors', 'author_id'], $conventions->getBelongsToReference('books', 'Author'));
	Assert::same(['authors', 'translator_id'], $conventions->getBelongsToReference('books', 'Translator'));
});


test('tests case insensivity and prefixes', function () {
	$structure = Mockery::mock(Nette\Database\Structure::class);
	$structure->shouldReceive('getBelongsToReference')->with('nBooks')->andReturn([
		'authorId' => 'nAuthors',
		'translatorId' => 'nAuthors',
	])->twice();

	$conventions = new DiscoveredConventions($structure);

	Assert::same(['nAuthors', 'authorId'], $conventions->getBelongsToReference('nBooks', 'author'));
	Assert::same(['nAuthors', 'translatorId'], $conventions->getBelongsToReference('nBooks', 'translator'));
});


test('tests rebuilt', function () {
	$structure = Mockery::mock(Nette\Database\Structure::class);
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


test('tests already rebuilt structure', function () {
	$structure = Mockery::mock(Nette\Database\Structure::class);
	$structure->shouldReceive('isRebuilt')->andReturn(true);
	$structure->shouldReceive('getBelongsToReference')->with('books')->andReturn([])->once();

	$conventions = new DiscoveredConventions($structure);
	Assert::null($conventions->getBelongsToReference('books', 'author'));
});


Mockery::close();
