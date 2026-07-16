<?php declare(strict_types=1);

/**
 * Test: DatabaseExtension configuration options.
 */

use Nette\Bridges\DatabaseDI\DatabaseExtension;
use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


function buildContainer(string $config, string $class, bool $debugMode = false): DI\Container
{
	$loader = new DI\Config\Loader;
	$config = $loader->load(Tester\FileMock::create($config, 'neon'));

	$compiler = new DI\Compiler;
	$compiler->addExtension('database', new DatabaseExtension($debugMode));
	eval($compiler->addConfig($config)->setClassName($class)->compile());

	$container = new $class;
	$container->initialize();
	return $container;
}


test('PDO:: constants in options are translated to their values', function () {
	$container = buildContainer('
	database:
		dsn: "sqlite::memory:"
		options:
			PDO::ATTR_CASE: PDO::CASE_LOWER

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'ContainerOpt1');

	$connection = $container->getService('database.default');
	Assert::same(PDO::CASE_LOWER, $connection->getPdo()->getAttribute(PDO::ATTR_CASE));
});


test('BC option reflection: creates the conventions service under the historical name', function () {
	$container = buildContainer('
	database:
		dsn: "sqlite::memory:"
		reflection: discovered

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'ContainerOpt2');

	Assert::true($container->hasService('database.default.reflection'));
	Assert::false($container->hasService('database.default.conventions'));
	Assert::type(
		Nette\Database\Conventions\DiscoveredConventions::class,
		$container->getService('database.default.reflection'),
	);
});


test('empty conventions registers no conventions service and Explorer falls back to StaticConventions', function () {
	$container = buildContainer('
	database:
		dsn: "sqlite::memory:"
		conventions: ""

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'ContainerOpt3');

	Assert::false($container->hasService('database.default.conventions'));
	$explorer = $container->getService('database.default.explorer');
	Assert::type(Nette\Database\Conventions\StaticConventions::class, $explorer->getConventions());
});


test('conventions accepts a custom class name', function () {
	$container = buildContainer('
	database:
		dsn: "sqlite::memory:"
		conventions: Nette\Database\Conventions\StaticConventions

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'ContainerOpt4');

	Assert::type(
		Nette\Database\Conventions\StaticConventions::class,
		$container->getService('database.default.conventions'),
	);
});


test('debugger: yes wires the Tracy bar panel to the connection', function () {
	$container = buildContainer('
	database:
		dsn: "sqlite::memory:"
		debugger: yes

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'ContainerOpt5', debugMode: true);

	$container->getService('database.default');
	Assert::type(
		Nette\Bridges\DatabaseTracy\ConnectionPanel::class,
		Tracy\Debugger::getBar()->getPanel(Nette\Bridges\DatabaseTracy\ConnectionPanel::class),
	);
});
