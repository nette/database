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

	$explorer = $container->getService('database.first');
	Assert::type(Nette\Database\Explorer::class, $explorer);
	Assert::same($explorer, $container->getByType(Nette\Database\Explorer::class));
	Assert::type(Nette\Caching\Cache::class, $explorer->getCache());

	// aliases
	Assert::same($explorer, $container->getService('database.first.explorer'));
	Assert::same($explorer, $container->getService('nette.database.first'));
	Assert::same($explorer, $container->getService('nette.database.first.context'));
});
