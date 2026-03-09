<?php declare(strict_types=1);

/**
 * Test: DatabaseExtension row mapping configuration.
 */

use Nette\Bridges\DatabaseDI\DatabaseExtension;
use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('string shortcut mapping', function () {
	$loader = new DI\Config\Loader;
	$config = $loader->load(Tester\FileMock::create('
	database:
		dsn: "sqlite::memory:"
		mapping: App\Entity\*Row
		debugger: no

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'neon'));

	$compiler = new DI\Compiler;
	$compiler->addExtension('database', new DatabaseExtension(false));
	$code = $compiler->addConfig($config)->setClassName('Container1')->compile();
	eval($code);

	$container = new Container1;
	$container->initialize();

	$explorer = $container->getService('database.default.explorer');
	Assert::type(Nette\Database\Explorer::class, $explorer);

	// verify the mapping closure was set by checking generated code
	Assert::contains('createRowMapping', $code);
});


test('full mapping with convention and tables', function () {
	$loader = new DI\Config\Loader;
	$config = $loader->load(Tester\FileMock::create('
	database:
		dsn: "sqlite::memory:"
		mapping:
			convention: App\Entity\*Row
			tables:
				special: App\Entity\SpecialRow
		debugger: no

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'neon'));

	$compiler = new DI\Compiler;
	$compiler->addExtension('database', new DatabaseExtension(false));
	$code = $compiler->addConfig($config)->setClassName('Container2')->compile();
	eval($code);

	$container = new Container2;
	$container->initialize();

	$explorer = $container->getService('database.default.explorer');
	Assert::type(Nette\Database\Explorer::class, $explorer);
	Assert::contains('createRowMapping', $code);
});


test('no mapping by default', function () {
	$loader = new DI\Config\Loader;
	$config = $loader->load(Tester\FileMock::create('
	database:
		dsn: "sqlite::memory:"
		debugger: no

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'neon'));

	$compiler = new DI\Compiler;
	$compiler->addExtension('database', new DatabaseExtension(false));
	$code = $compiler->addConfig($config)->setClassName('Container3')->compile();
	eval($code);

	$container = new Container3;
	$container->initialize();

	$explorer = $container->getService('database.default.explorer');
	Assert::type(Nette\Database\Explorer::class, $explorer);

	// no mapping should be set
	Assert::notContains('createRowMapping', $code);
});


test('createRowMapping() with convention', function () {
	$mapping = DatabaseExtension::createRowMapping('App\Entity\*Row', []);

	// unknown class -> fallback to ActiveRow
	Assert::same(Nette\Database\Table\ActiveRow::class, $mapping('nonexistent'));
});


test('createRowMapping() with explicit tables', function () {
	$mapping = DatabaseExtension::createRowMapping('', [
		'my_table' => 'Nette\Database\Table\ActiveRow',
	]);

	Assert::same(Nette\Database\Table\ActiveRow::class, $mapping('my_table'));

	// not in tables and no convention -> fallback
	Assert::same(Nette\Database\Table\ActiveRow::class, $mapping('other'));
});


test('createRowMapping() tables override convention', function () {
	$mapping = DatabaseExtension::createRowMapping('Some\*Row', [
		'special' => 'Nette\Database\Table\ActiveRow',
	]);

	// explicit override wins
	Assert::same(Nette\Database\Table\ActiveRow::class, $mapping('special'));
});


test('createRowMapping() snake_case to PascalCase', function () {
	$mapping = DatabaseExtension::createRowMapping('*', []);

	// We can't test actual entity classes (they don't exist in test env),
	// but we can verify the fallback for non-existent classes
	Assert::same(Nette\Database\Table\ActiveRow::class, $mapping('some_table'));

	// Verify convention produces correct class names by using a class that exists
	$mapping = DatabaseExtension::createRowMapping('Nette\Database\Table\*', []);
	Assert::same('Nette\Database\Table\ActiveRow', $mapping('active_row'));
});
