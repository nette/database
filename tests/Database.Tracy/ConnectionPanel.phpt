<?php declare(strict_types=1);

/**
 * Test: ConnectionPanel
 */

use Nette\Bridges\DatabaseTracy\ConnectionPanel;
use Nette\Database\Connection;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('Tracy Bar', function () {
	$connection = new Connection('sqlite::memory:');
	$panel = ConnectionPanel::initialize($connection, name: 'foo');

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
	$panel = @Nette\Database\Helpers::initializeTracy($connection, addBarPanel: true, name: 'foo'); // @ initializeTracy() is deprecated

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


test('maxQueries caps stored query details but not the count', function () {
	$connection = new Connection('sqlite::memory:');
	$panel = ConnectionPanel::initialize($connection, name: 'cap');
	$panel->maxQueries = 3;

	for ($i = 1; $i <= 5; $i++) {
		$connection->query('SELECT ' . $i);
	}

	$events = new ReflectionProperty($panel, 'events')->getValue($panel);
	Assert::count(3, $events); // exactly maxQueries stored
	Assert::same('SELECT 1', $events[0][1]->getSql());
	Assert::same('SELECT 3', $events[2][1]->getSql());
	Assert::same(5, new ReflectionProperty($panel, 'count')->getValue($panel));
});


test('BlueScreen panel is registered only once for multiple connections', function () {
	$blueScreen = new Tracy\BlueScreen;
	$before = count(new ReflectionProperty($blueScreen, 'panels')->getValue($blueScreen));

	ConnectionPanel::initialize(new Connection('sqlite::memory:'), blueScreen: $blueScreen);
	ConnectionPanel::initialize(new Connection('sqlite::memory:'), blueScreen: $blueScreen);

	$panels = new ReflectionProperty($blueScreen, 'panels')->getValue($blueScreen);
	Assert::count($before + 1, $panels);
});


test('disabled panel logs nothing', function () {
	$connection = new Connection('sqlite::memory:');
	$panel = ConnectionPanel::initialize($connection, name: 'off');
	$panel->disabled = true;

	$connection->query('SELECT 1');

	Assert::same(0, new ReflectionProperty($panel, 'count')->getValue($panel));
	Assert::count(0, new ReflectionProperty($panel, 'events')->getValue($panel));
	Assert::null($panel->getPanel());
});
