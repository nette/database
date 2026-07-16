<?php declare(strict_types=1);

/**
 * Test: Row & RowBehavior: attached row class composing the trait over a plain value base class.
 * @dataProvider? ../databases.ini
 */

use Nette\Database\Table;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


// value class: the shape of a book regardless of origin (database row, test fixture, form)
class Book extends Table\ActiveRow
{
	public function __construct(
		public string $title = '',
		public ?int $author_id = null,
		public ?int $translator_id = null,
		public ?int $next_volume = null,
	) {
	}


	public function isTranslated(): bool
	{
		return $this->translator_id !== null;
	}
}


// attached row in its target form: composes RowBehavior, plus a userland unset
// bridging declared value properties to magic column access
final class BookRow extends Book implements Table\Row
{
	use Table\RowBehavior {
		__construct as private constructRow;
	}

	public function __construct(array $data, Table\Selection $table, bool $deferredFetch = false)
	{
		$this->constructRow($data, $table, $deferredFetch);
		foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			if (!$property->isStatic()) {
				unset($this->{$property->getName()});
			}
		}
	}
}


class EntityExplorer extends Nette\Database\Explorer
{
	public function createActiveRow(array $data, Table\Selection $selection, bool $deferredFetch = false): Table\ActiveRow
	{
		return $selection->getName() === 'book'
			? new BookRow($data, $selection, $deferredFetch)
			: parent::createActiveRow($data, $selection, $deferredFetch);
	}
}


$explorer = connectToDB();
$connection = $explorer->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

$cacheMemoryStorage = new Nette\Caching\Storages\MemoryStorage;
$structure = new Nette\Database\Structure($connection, $cacheMemoryStorage);
$conventions = new Nette\Database\Conventions\DiscoveredConventions($structure);
$explorer = new EntityExplorer($connection, $structure, $conventions, $cacheMemoryStorage);


test('hydrated row is the entity class and reads columns through magic access', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	Assert::type(BookRow::class, $book);
	Assert::true($book instanceof Book);
	Assert::true($book instanceof Table\Row);
	Assert::true($book instanceof Table\ActiveRow);
	Assert::same('1001 tipu a triku pro PHP', $book->title);
	Assert::same(11, $book->author_id);
	Assert::true($book->isTranslated());
});


test('relations work from the entity row', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	Assert::same('Jakub Vrana', $book->author->name);
	Assert::same('Jakub Vrana', $book->ref('author', 'author_id')->name);

	$tags = [];
	foreach ($book->related('book_tag') as $bookTag) {
		$tags[] = $bookTag->tag->name;
	}

	sort($tags);
	Assert::same(['MySQL', 'PHP'], $tags);
});


test('attached row stays read-only', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	Assert::exception(
		fn() => $book->title = 'x',
		Nette\DeprecatedException::class,
		'ActiveRow is read-only; use update() method instead.',
	);
});


test('update() writes to database and refreshes declared properties', function () use ($explorer) {
	$book = $explorer->table('book')->get(2);
	$book->update(['title' => 'JUSH 2']);
	Assert::same('JUSH 2', $book->title);
	Assert::same('JUSH 2', $explorer->table('book')->get(2)->title);
});


test('insert() returns a lazy entity row completed on first access', function () use ($explorer) {
	$row = $explorer->table('book')->insert([
		'author_id' => 12,
		'title' => 'Value objects in practice',
	]);
	Assert::type(BookRow::class, $row);
	Assert::same('Value objects in practice', $row->title);
	Assert::false($row->isTranslated());
});


test('detached value is constructible and mutable without database', function () {
	$draft = new Book(title: 'Draft', author_id: 12);
	Assert::same('Draft', $draft->title);
	Assert::false($draft->isTranslated());

	$draft->translator_id = 11;
	Assert::true($draft->isTranslated());
});


test('detached value refuses database operations', function () {
	$draft = new Book(title: 'Draft');
	Assert::exception(
		fn() => $draft->update(['title' => 'x']),
		Error::class,
		'%a%$table must not be accessed before initialization',
	);
});
