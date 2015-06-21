<?php

/**
 * Test: DatabaseExtension.
 */

use Nette\DI;
use Nette\Bridges\DatabaseDI\DatabaseExtension;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () {
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
	$compiler->addExtension('database', new DatabaseExtension(FALSE));
	eval($compiler->compile($config, 'Container1'));

	$container = new Container1;
	$container->initialize();

	$connection = $container->getService('database.default');
	Assert::type('Nette\Database\Connection', $connection);
	Assert::same('sqlite::memory:', $connection->getDsn());

	$context = $container->getService('database.default.context');
	Assert::type('Nette\Database\Context', $context);
	Assert::same($connection, $context->getConnection());

	Assert::type('Nette\Database\Structure', $context->getStructure());
	Assert::type('Nette\Database\Conventions\DiscoveredConventions', $context->getConventions());

	// aliases
	Assert::same($connection, $container->getService('nette.database.default'));
	Assert::same($context, $container->getService('nette.database.default.context'));
});
