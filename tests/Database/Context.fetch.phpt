<?php

/**
 * Test: Nette\Database\Context fetch methods.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test(function () use ($context) { // fetch
	$row = $context->fetch('SELECT name, id FROM author WHERE id = ?', 11);
	Assert::type(Nette\Database\Row::class, $row);
	Assert::equal(Nette\Database\Row::from([
		'name' => 'Jakub Vrana',
		'id' => 11,
	]), $row);
});


test(function () use ($context) { // fetchField
	Assert::same('Jakub Vrana', $context->fetchField('SELECT name FROM author ORDER BY id'));
});


test(function () use ($context) { // fetchPairs
	$pairs = $context->fetchPairs('SELECT name, id FROM author WHERE id > ? ORDER BY id', 11);
	Assert::same([
		'David Grudl' => 12,
		'Geek' => 13,
	], $pairs);
});


test(function () use ($context) { // fetchAll
	$arr = $context->fetchAll('SELECT name, id FROM author WHERE id < ? ORDER BY id', 13);
	Assert::equal([
		Nette\Database\Row::from(['name' => 'Jakub Vrana', 'id' => 11]),
		Nette\Database\Row::from(['name' => 'David Grudl', 'id' => 12]),
	], $arr);
});
