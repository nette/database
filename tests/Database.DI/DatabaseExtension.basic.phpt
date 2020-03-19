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

	$connection = $container->getService('database.default');
	Assert::type(Nette\Database\Connection::class, $connection);
	Assert::same('sqlite::memory:', $connection->getDsn());

	$context = $container->getService('database.default.context');
	Assert::type(Nette\Database\Explorer::class, $context);
	Assert::same($connection, $context->getConnection());

	Assert::type(Nette\Database\Structure::class, $context->getStructure());
	Assert::type(Nette\Database\Conventions\DiscoveredConventions::class, $context->getConventions());

	// aliases
	Assert::same($connection, $container->getService('nette.database.default'));
	Assert::same($context, $container->getService('nette.database.default.context'));
});
