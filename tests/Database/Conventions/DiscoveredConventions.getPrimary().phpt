<?php

/**
 * Test: Nette\Database\Conventions\DiscoveredConventions::getPrimary().
 */

use Nette\Database\Conventions\DiscoveredConventions;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test(function () {
	$structure = Mockery::mock('Nette\Database\IStructure');
	$structure->shouldReceive('getPrimaryKey')->with('books_x_tags')->andReturn(array('book_id', 'tag_id'));

	$conventions = new DiscoveredConventions($structure);
	Assert::same(array('book_id', 'tag_id'), $conventions->getPrimary('books_x_tags'));
});
