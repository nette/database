<?php declare(strict_types=1);

/**
 * Test: DatabaseExtension entity mapping configuration.
 */

use Nette\Bridges\DatabaseDI\DatabaseExtension;
use Nette\Database\DefaultEntityMapping;
use Nette\Database\Explorer;
use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createContainer(string $neon, string $className): DI\Container
{
	$loader = new DI\Config\Loader;
	$config = $loader->load(Tester\FileMock::create($neon, 'neon'));
	$compiler = new DI\Compiler;
	$compiler->addExtension('database', new DatabaseExtension(false));
	eval($compiler->addConfig($config)->setClassName($className)->compile());
	$container = new $className;
	$container->initialize();
	return $container;
}


function getEntityMapping(Explorer $explorer): ?Nette\Database\EntityMapping
{
	return (new ReflectionProperty($explorer, 'entityMapping'))->getValue($explorer);
}


test('full mapping with tables map', function () {
	$container = createContainer('
	database:
		dsn: "sqlite::memory:"
		mapping:
			tables:
				special: App\Entity\SpecialRow
				"*": App\Entity\*Row
		debugger: no

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'Container2');

	$explorer = $container->getService('database.default.explorer');
	Assert::type(DefaultEntityMapping::class, getEntityMapping($explorer));
});


test('tables string shortcut', function () {
	$container = createContainer('
	database:
		dsn: "sqlite::memory:"
		mapping:
			tables: App\Entity\*Row
		debugger: no

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'Container2b');

	$explorer = $container->getService('database.default.explorer');
	Assert::type(DefaultEntityMapping::class, getEntityMapping($explorer));
});


test('no mapping by default', function () {
	$container = createContainer('
	database:
		dsn: "sqlite::memory:"
		debugger: no

	services:
		cache: Nette\Caching\Storages\DevNullStorage
	', 'Container3');

	$explorer = $container->getService('database.default.explorer');
	Assert::null(getEntityMapping($explorer));
});


test('DefaultEntityMapping: explicit tables', function () {
	$mapping = new DefaultEntityMapping([
		'my_table' => 'Nette\Database\Table\ActiveRow',
	]);

	Assert::same(Nette\Database\Table\ActiveRow::class, $mapping->getClassName('my_table'));
	Assert::null($mapping->getClassName('other'));
});


test('DefaultEntityMapping: exact key overrides wildcard fallback', function () {
	$mapping = new DefaultEntityMapping([
		'special' => 'Nette\Database\Table\ActiveRow',
		'*' => 'Nette\Database\Table\*',
	]);

	Assert::same(Nette\Database\Table\ActiveRow::class, $mapping->getClassName('special'));
	Assert::same('Nette\Database\Table\ActiveRow', $mapping->getClassName('active_row'));
});


test('DefaultEntityMapping: bare wildcard with snake_case to PascalCase', function () {
	$mapping = new DefaultEntityMapping(['*' => 'Nette\Database\Table\*']);

	Assert::same('Nette\Database\Table\ActiveRow', $mapping->getClassName('active_row'));
	Assert::same('Nette\Database\Table\NonexistentTable', $mapping->getClassName('nonexistent_table'));
});


test('DefaultEntityMapping: empty map returns null', function () {
	$mapping = new DefaultEntityMapping;

	Assert::null($mapping->getClassName('any_table'));
});


test('DefaultEntityMapping: camelCase off = identity', function () {
	$mapping = new DefaultEntityMapping;

	Assert::same('first_name', $mapping->getPropertyName('first_name'));
	Assert::same('firstName', $mapping->getColumnName('firstName'));
});


test('DefaultEntityMapping: camelCase on', function () {
	$mapping = new DefaultEntityMapping(camelCase: true);

	Assert::same('firstName', $mapping->getPropertyName('first_name'));
	Assert::same('authorId', $mapping->getPropertyName('author_id'));
	Assert::same('id', $mapping->getPropertyName('id'));
	Assert::same('name', $mapping->getPropertyName('name'));
	Assert::same('createdAt', $mapping->getPropertyName('created_at'));
	Assert::same('address2', $mapping->getPropertyName('address2'));

	Assert::same('first_name', $mapping->getColumnName('firstName'));
	Assert::same('author_id', $mapping->getColumnName('authorId'));
	Assert::same('id', $mapping->getColumnName('id'));
	Assert::same('name', $mapping->getColumnName('name'));
	Assert::same('created_at', $mapping->getColumnName('createdAt'));
	Assert::same('address2', $mapping->getColumnName('address2'));
});


test('DefaultEntityMapping: camelCase roundtrip', function () {
	$mapping = new DefaultEntityMapping(camelCase: true);

	foreach (['id', 'name', 'first_name', 'author_id', 'created_at', 'address2'] as $column) {
		Assert::same($column, $mapping->getColumnName($mapping->getPropertyName($column)));
	}
});


test('DefaultEntityMapping: schema prefix is stripped in class name', function () {
	$mapping = new DefaultEntityMapping(['*' => 'App\Model\*Row']);

	Assert::same('App\Model\UserAccountRow', $mapping->getClassName('public.user_account'));
	Assert::same('App\Model\UserAccountRow', $mapping->getClassName('user_account'));
});


test('DefaultEntityMapping: wildcard pattern in tables key', function () {
	$mapping = new DefaultEntityMapping([
		'forum_*' => 'App\Forum\*Row',
		'shop_*' => 'App\Shop\*Row',
	]);

	Assert::same('App\Forum\PostRow', $mapping->getClassName('forum_post'));
	Assert::same('App\Forum\UserAccountRow', $mapping->getClassName('forum_user_account'));
	Assert::same('App\Shop\OrderRow', $mapping->getClassName('shop_order'));
	Assert::null($mapping->getClassName('unmatched_table'));
});


test('DefaultEntityMapping: exact key wins over wildcard', function () {
	$mapping = new DefaultEntityMapping([
		'forum_post' => 'App\Forum\Post',
		'forum_*' => 'App\Forum\*Row',
	]);

	Assert::same('App\Forum\Post', $mapping->getClassName('forum_post'));
	Assert::same('App\Forum\TagRow', $mapping->getClassName('forum_tag'));
});


test('DefaultEntityMapping: wildcard patterns tried in declaration order', function () {
	$mapping = new DefaultEntityMapping([
		'forum_admin_*' => 'App\Admin\*Row',
		'forum_*' => 'App\Forum\*Row',
	]);

	Assert::same('App\Admin\UserRow', $mapping->getClassName('forum_admin_user'));
	Assert::same('App\Forum\PostRow', $mapping->getClassName('forum_post'));
});


test('DefaultEntityMapping: bare * acts as catch-all fallback', function () {
	$mapping = new DefaultEntityMapping([
		'forum_*' => 'App\Forum\*Row',
		'*' => 'App\Entity\*Row',
	]);

	Assert::same('App\Forum\PostRow', $mapping->getClassName('forum_post'));
	Assert::same('App\Entity\UserRow', $mapping->getClassName('user'));
});


test('DefaultEntityMapping: wildcard value without * is fixed class', function () {
	$mapping = new DefaultEntityMapping([
		'log_*' => 'App\Logging\LogRow',
	]);

	Assert::same('App\Logging\LogRow', $mapping->getClassName('log_access'));
	Assert::same('App\Logging\LogRow', $mapping->getClassName('log_error'));
});
