<?php declare(strict_types=1);

/**
 * Test: Row & RowBehavior: typed access to columns via virtual get-only property hooks
 * reading through column()/enum(): virtuality, read-only semantics, __PROPERTY__,
 * isset(), enum conversion and errors.
 * @dataProvider? ../databases.ini
 * @phpVersion 8.4
 */

use Nette\Database\Table;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


enum ArticleState: string
{
	case Draft = 'draft';
	case Published = 'published';
}


// typed façade over columns: virtual get-only hooked properties; the hook body must not
// touch the backing value, so the property stays virtual and writes are engine errors
class ArticleRow extends Table\ActiveRow
{
	public ?ArticleState $state { get => $this->enum(ArticleState::class, 'state'); }
	public ?string $note { get => $this->column(__PROPERTY__); } // __PROPERTY__ resolves to 'note'
	public mixed $missing { get => $this->column('missing'); }
}


class BookRow extends Table\ActiveRow
{
	public string $title { get => $this->column('title'); }
	public ?int $translatorId { get => $this->column('translator_id'); }


	public function isTranslated(): bool
	{
		return $this->translatorId !== null;
	}
}


class EntityExplorer extends Nette\Database\Explorer
{
	public function createActiveRow(array $data, Table\Selection $selection, bool $deferredFetch = false): Table\ActiveRow
	{
		return match ($selection->getName()) {
			'book' => new BookRow($data, $selection, $deferredFetch),
			'hook_article' => new ArticleRow($data, $selection, $deferredFetch),
			default => parent::createActiveRow($data, $selection, $deferredFetch),
		};
	}
}


$explorer = connectToDB();
$connection = $explorer->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

try {
	$connection->query('DROP TABLE hook_article');
} catch (Nette\Database\DriverException) {
}
$connection->query('CREATE TABLE hook_article (id int NOT NULL PRIMARY KEY, state varchar(20), note varchar(50))');

$cacheMemoryStorage = new Nette\Caching\Storages\MemoryStorage;
$structure = new Nette\Database\Structure($connection, $cacheMemoryStorage);
$conventions = new Nette\Database\Conventions\DiscoveredConventions($structure);
$explorer = new EntityExplorer($connection, $structure, $conventions, $cacheMemoryStorage);

$explorer->table('hook_article')->insertMany([
	['id' => 1, 'state' => 'published', 'note' => 'first'],
	['id' => 2, 'state' => null, 'note' => null],
	['id' => 3, 'state' => 'garbage', 'note' => 'broken'],
]);


test('hydrated row is the entity class and hooked properties read columns', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	Assert::type(BookRow::class, $book);
	Assert::true($book instanceof Table\Row);
	Assert::same('1001 tipu a triku pro PHP', $book->title);
	Assert::same(11, $book->translatorId);
	Assert::true($book->isTranslated());
});


test('magic access to non-hooked columns still works alongside hooks', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	Assert::same(11, $book->author_id);
	Assert::same('Jakub Vrana', $book->author->name);

	$tags = [];
	foreach ($book->related('book_tag') as $bookTag) {
		$tags[] = $bookTag->tag->name;
	}

	sort($tags);
	Assert::same(['MySQL', 'PHP'], $tags);
});


test('virtual get-only property is engine-level read-only', function () use ($explorer) {
	$book = $explorer->table('book')->get(1);
	Assert::exception(
		fn() => $book->title = 'x',
		Error::class,
		'%a%title%a%',
	);
});


test('update() refreshes data and hooked property reflects it', function () use ($explorer) {
	$book = $explorer->table('book')->get(2);
	$book->update(['title' => 'JUSH 2']);
	Assert::same('JUSH 2', $book->title);
	Assert::same('JUSH 2', $explorer->table('book')->get(2)->title);
});


test('insert() returns a lazy entity row completed on first hooked read', function () use ($explorer) {
	$row = $explorer->table('book')->insert([
		'author_id' => 12,
		'title' => 'Hooked value objects',
	]);
	Assert::type(BookRow::class, $row);
	Assert::same('Hooked value objects', $row->title);
	Assert::false($row->isTranslated());
});


test('enum(): conversion, NULL passthrough and isset() semantics on virtual properties', function () use ($explorer) {
	$article = $explorer->table('hook_article')->get(1);
	Assert::same(ArticleState::Published, $article->state);
	Assert::same('first', $article->note); // čteno přes __PROPERTY__

	$empty = $explorer->table('hook_article')->get(2);
	Assert::null($empty->state);
	Assert::null($empty->note);

	Assert::true(isset($article->state));
	Assert::false(isset($empty->state)); // isset na virtuální property volá get a testuje null
});


test('enum(): a value outside the enum reports table, column and class', function () use ($explorer) {
	$broken = $explorer->table('hook_article')->get(3);
	Assert::exception(
		fn() => $broken->state,
		Nette\InvalidStateException::class,
		"Cannot convert value 'garbage' of column 'hook_article.state' to enum ArticleState.",
	);
});


test('column(): unknown column reports a suggestion', function () use ($explorer) {
	$article = $explorer->table('hook_article')->get(1);
	Assert::exception(
		fn() => $article->missing,
		Nette\MemberAccessException::class,
		"Cannot read an undeclared column 'missing'.",
	);
});
