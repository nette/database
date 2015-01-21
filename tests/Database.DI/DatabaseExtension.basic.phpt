<?php

/**
 * Test: DatabaseExtension.
 */

use Nette\DI,
	Nette\Bridges\DatabaseDI\DatabaseExtension,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function() {
	$loader = new DI\Config\Loader;
	$config = $loader->load(Tester\FileMock::create('
	database:
		dsn: "sqlite::memory:"
		user: name
		password: secret
		debugger: no
		options:
			lazy: yes
	', 'neon'));

	$compiler = new DI\Compiler;
	$compiler->addExtension('database', new DatabaseExtension(FALSE));
	$compiler->addExtension('cache', new Nette\Bridges\CacheDI\CacheExtension(TEMP_DIR));
	eval($compiler->compile($config, 'Container1'));

	$container = new Container1;
	$container->initialize();

	$connection = $container->getService('database.default');
	Assert::type('Nette\Database\Connection', $connection);
	Assert::same('sqlite::memory:', $connection->getDsn());

	$context = $container->getService('database.default.context');
	Assert::type('Nette\Database\Context', $context);
	Assert::same($connection, $context->getConnection());

	// aliases
	Assert::same($connection, $container->getService('nette.database.default'));
	Assert::same($context, $container->getService('nette.database.default.context'));
});
