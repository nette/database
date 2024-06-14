<?php

/**
 * Test: DatabaseExtension with CredentialProvider
 */

declare(strict_types=1);

use Nette\Bridges\DatabaseDI\DatabaseExtension;
use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

class CredentialProviderImpl implements Nette\Database\CredentialProvider
{
	public function getPassword(): string
	{
		throw new RuntimeException('password requested from provider');
	}
}

test('', function () {
	$loader = new DI\Config\Loader;
	$config = $loader->load(Tester\FileMock::create('
	database:
		dsn: "sqlite::memory:"
		user: name
		password: @passwords
		debugger: no
		options:
			lazy: yes

	services:
		cache: Nette\Caching\Storages\DevNullStorage
		passwords: \CredentialProviderImpl
	', 'neon'));

	$compiler = new DI\Compiler;
	$compiler->addExtension('database', new DatabaseExtension(false));
	eval($compiler->addConfig($config)->setClassName('Container1')->compile());

	$container = new Container1;
	$container->initialize();

	$connection = $container->getService('database.default');
	Assert::type(Nette\Database\Connection::class, $connection);

	Assert::exception(
		fn() => $connection->getPdo(),
		RuntimeException::class,
		'password requested from provider',
	);
});
