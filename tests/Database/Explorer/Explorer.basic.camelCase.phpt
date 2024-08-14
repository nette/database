<?php

/**
 * Test: Nette\Database\Table: Basic operations with camelCase name conventions.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();
$connection = $explorer->getConnection();

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test2.sql");


test('', function () use ($explorer) {
	$titles = [];
	foreach ($explorer->table('nUsers')->order('nUserId') as $user) {
		foreach ($user->related('nUsers_nTopics')->order('nTopicId') as $userTopic) {
			$titles[$userTopic->nTopic->title] = $user->name;
		}
	}

	Assert::same([
		'Topic #1' => 'John',
		'Topic #3' => 'John',
		'Topic #2' => 'Doe',
	], $titles);
});
