<?php

/**
 * Test: ConnectionPanel
 */

declare(strict_types=1);

use Nette\Bridges\DatabaseTracy\ConnectionPanel;
use Nette\Database\Connection;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('Tracy Bar', function () {
	$connection = new Connection('sqlite::memory:');
	$panel = ConnectionPanel::initialize($connection, addBarPanel: true, name: 'foo');

	$connection->beginTransaction();
	$connection->query('SELECT 1');
	$connection->commit();
	try {
		$connection->query('SELECT');
	} catch (Throwable) {
	}

	Assert::matchFile(__DIR__ . '/tab.html', $panel->getTab());
	Assert::matchFile(__DIR__ . '/panel.html', $panel->getPanel());
});

test('Bluescreen Panel', function () {
	$connection = new Connection('sqlite::memory:');
	try {
		$connection->query('SELECT');
	} catch (Throwable $e) {
	}

	Assert::same(
		[
			'tab' => 'SQL',
			'panel' => "<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong></pre>\n",
		],
		ConnectionPanel::renderException($e),
	);
});

test('deprecated initialization', function () {
	$connection = new Connection('sqlite::memory:');
	$panel = Nette\Database\Helpers::initializeTracy($connection, addBarPanel: true, name: 'foo');

	$connection->beginTransaction();
	$connection->query('SELECT 1');
	$connection->commit();
	try {
		$connection->query('SELECT');
	} catch (Throwable) {
	}

	Assert::matchFile(__DIR__ . '/tab.html', $panel->getTab());
	Assert::matchFile(__DIR__ . '/panel.html', $panel->getPanel());
});
