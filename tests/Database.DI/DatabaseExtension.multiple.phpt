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
			options:
				lazy: yes

		second:
			dsn: "sqlite::memory:"
			user: name
			password: secret
			debugger: no
			options:
				lazy: yes

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
	Assert::same('sqlite::memory:', $connection->getDsn());

	$context = $container->getService('database.first.context');
	Assert::type(Nette\Database\Explorer::class, $context);
	Assert::same($context, $container->getByType(Nette\Database\Explorer::class));
	Assert::same($connection, $context->getConnection());

	Assert::type(Nette\Database\Structure::class, $context->getStructure());
	Assert::same($context->getStructure(), $container->getByType(Nette\Database\IStructure::class));
	Assert::type(Nette\Database\Conventions\DiscoveredConventions::class, $context->getConventions());
	Assert::same($context->getConventions(), $container->getByType(Nette\Database\IConventions::class));

	// aliases
	Assert::same($connection, $container->getService('nette.database.first'));
	Assert::same($context, $container->getService('nette.database.first.context'));
});
