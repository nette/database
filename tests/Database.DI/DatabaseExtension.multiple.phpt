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
	$compiler->addExtension('database', new DatabaseExtension(FALSE));
	eval(@$compiler->compile($config, 'Container1')); // @ compatibility with DI 2.4

	$container = new Container1;
	$container->initialize();

	$connection = $container->getService('database.first');
	Assert::type('Nette\Database\Connection', $connection);
	Assert::same($connection, $container->getByType('Nette\Database\Connection'));
	Assert::same('sqlite::memory:', $connection->getDsn());

	$context = $container->getService('database.first.context');
	Assert::type('Nette\Database\Context', $context);
	Assert::same($context, $container->getByType('Nette\Database\Context'));
	Assert::same($connection, $context->getConnection());

	Assert::type('Nette\Database\Structure', $context->getStructure());
	Assert::same($context->getStructure(), $container->getByType('Nette\Database\IStructure'));
	Assert::type('Nette\Database\Conventions\DiscoveredConventions', $context->getConventions());
	Assert::same($context->getConventions(), $container->getByType('Nette\Database\IConventions'));

	// aliases
	Assert::same($connection, $container->getService('nette.database.first'));
	Assert::same($context, $container->getService('nette.database.first.context'));
});
