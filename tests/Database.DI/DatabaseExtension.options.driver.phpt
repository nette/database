<?php declare(strict_types=1);

/**
 * Test: DatabaseExtension driver-specific constants like Pdo\Sqlite::ATTR_* in options.
 * @phpVersion >= 8.4
 */

use Nette\Bridges\DatabaseDI\DatabaseExtension;
use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function buildContainer(string $config, string $class): DI\Container
{
	$loader = new DI\Config\Loader;
	$config = $loader->load(Tester\FileMock::create($config, 'neon'));

	$compiler = new DI\Compiler;
	$compiler->addExtension('database', new DatabaseExtension);
	eval($compiler->addConfig($config)->setClassName($class)->compile());

	$container = new $class;
	$container->initialize();
	return $container;
}


test('driver-specific constants are translated to their values', function () {
	$container = buildContainer('
	database:
		dsn: "sqlite::memory:"
		options:
			Pdo\Sqlite::ATTR_EXTENDED_RESULT_CODES: true

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'ContainerOptDriver');

	Assert::noError(fn() => $container->getService('database.default')->connect());
});


test('an unknown driver-specific constant names the offending config key', function () {
	Assert::exception(
		fn() => buildContainer('
	database:
		dsn: "sqlite::memory:"
		options:
			Pdo\Sqlite::ATTR_FOO: true
	', 'ContainerOptDriverBad'),
		Nette\DI\InvalidConfigurationException::class,
		"Unknown constant 'Pdo\\Sqlite::ATTR_FOO' in the 'database.default.options' option.",
	);
});
