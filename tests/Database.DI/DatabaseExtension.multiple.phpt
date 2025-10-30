<?php

/**
 * Test: DatabaseExtension.
 */

declare(strict_types=1);

use Nette\Bridges\DatabaseDI\DatabaseExtension;
use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('', function () {
	$loader = new DI\Config\Loader;
	$config = $loader->load(Tester\FileMock::create('
	database:
		first:
			dsn: "sqlite::memory:"
			user: name
			password: secret
			debugger: no

		second:
			dsn: "sqlite::memory:"
			user: name
			password: secret
			debugger: no

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'neon'));

	$compiler = new DI\Compiler;
	$compiler->addExtension('database', new DatabaseExtension(false));
	eval($compiler->addConfig($config)->setClassName('Container1')->compile());

	$container = new Container1;
	$container->initialize();

	$connection = $container->getService('database.first');
	Assert::type(Nette\Database\Connection::class, $connection);
	Assert::same($connection, $container->getByType(Nette\Database\Connection::class));

	$explorer = $container->getService('database.first.explorer');
	Assert::type(Nette\Database\Explorer::class, $explorer);
	Assert::same($explorer, $container->getByType(Nette\Database\Explorer::class));
	Assert::same($connection, $explorer->getConnection());
	Assert::same($container->getService('database.first.context'), $explorer);

	Assert::type(Nette\Database\Structure::class, $explorer->getStructure());
	Assert::same($explorer->getStructure(), $container->getByType(Nette\Database\IStructure::class));
	Assert::type(Nette\Database\Conventions\DiscoveredConventions::class, $explorer->getConventions());
	Assert::same($explorer->getConventions(), $container->getByType(Nette\Database\Conventions::class));

	// aliases
	Assert::same($connection, $container->getService('nette.database.first'));
	Assert::same($explorer, $container->getService('nette.database.first.context'));
});
