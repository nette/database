<?php

/**
 * Test: NNette\Database\Table\InstanceFactory custom default instances.
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

class CustomActiveRow extends \Nette\Database\Table\ActiveRow {}
class CustomSelection extends \Nette\Database\Table\Selection {}
class CustomGroupedSelection extends \Nette\Database\Table\GroupedSelection {}

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");

$context->setInstanceFactory(new \Nette\Database\Table\InstanceFactory([
		'default' => [
			'activeRow' => 'CustomActiveRow',
			'selection' => 'CustomSelection',
			'groupedSelection' => 'CustomGroupedSelection'
		]
	]
));

// Custom default instances
test(function () use ($context) { // ActiveRow
	$row = $context->table('book_tag')->where('book_id', 2)->fetch();
	Assert::type(CustomActiveRow::class, $row);
});

test(function () use ($context) { // Selection
	$row = $context->table('book_tag');
	Assert::type(CustomSelection::class, $row);
});

test(function () use ($context) { // GroupedSelection
	$row = $context->table('book')->wherePrimary(4)->fetch();
	$tags = $row->related('book_tag');
	Assert::type(CustomGroupedSelection::class, $tags);

	foreach ($tags as $tag) {
		Assert::type(CustomActiveRow::class, $tag);
	}
});
