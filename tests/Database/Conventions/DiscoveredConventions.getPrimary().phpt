<?php

/**
 * Test: Nette\Database\Conventions\DiscoveredConventions::getPrimary().
 */

declare(strict_types=1);

use Nette\Database\Conventions\DiscoveredConventions;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test(function () {
	$structure = Mockery::mock(Nette\Database\IStructure::class);
	$structure->shouldReceive('getPrimaryKey')->with('books_x_tags')->andReturn(['book_id', 'tag_id']);

	$conventions = new DiscoveredConventions($structure);
	Assert::same(['book_id', 'tag_id'], $conventions->getPrimary('books_x_tags'));
});
