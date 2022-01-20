<?php

/**
 * Test: Nette\Database\Structure
 */

declare(strict_types=1);

use Mockery\MockInterface;
use Nette\Database\Reflection\Column;
use Nette\Database\Reflection\ForeignKey;
use Nette\Database\Reflection\Table;
use Nette\Database\Structure;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';


class StructureMock extends Structure
{
	protected function needStructure(): void
	{
		if (!isset($this->structure)) {
			$this->structure = $this->loadStructure();
		}
	}
}


/**
 * @testCase
 */
class StructureTestCase extends TestCase
{
	private MockInterface $connection;

	private MockInterface $driver;

	private MockInterface $storage;

	private Structure $structure;


	protected function setUp()
	{
		parent::setUp();
		$this->driver = Mockery::mock(Nette\Database\Driver::class);
		$this->connection = Mockery::mock(Nette\Database\Connection::class);
		$this->storage = Mockery::mock(Nette\Caching\IStorage::class);

		$this->connection->shouldReceive('getDsn')->once()->andReturn('');
		$this->connection->shouldReceive('getDriver')->once()->andReturn($this->driver);
		$this->driver->shouldReceive('getTables')->once()->andReturn([
			new Table(name: 'authors', view: false),
			new Table(name: 'Books', view: false),
			new Table(name: 'tags', view: false),
			new Table(name: 'books_x_tags', view: false),
			new Table(name: 'books_view', view: true),
		]);
		$this->driver->shouldReceive('getColumns')->with('authors')->once()->andReturn([
			new Column(name: 'id', primary: true, autoIncrement: true, vendor: ['sequence' => '"public"."authors_id_seq"']),
			new Column(name: 'name', primary: false, autoIncrement: false, vendor: []),
		]);
		$this->driver->shouldReceive('getColumns')->with('Books')->once()->andReturn([
			new Column(name: 'id', primary: true, autoIncrement: true, vendor: ['sequence' => '"public"."Books_id_seq"']),
			new Column(name: 'title', primary: false, autoIncrement: false, vendor: []),
		]);
		$this->driver->shouldReceive('getColumns')->with('tags')->once()->andReturn([
			new Column(name: 'id', primary: true, autoIncrement: false, vendor: []),
			new Column(name: 'name', primary: false, autoIncrement: false, vendor: []),
		]);
		$this->driver->shouldReceive('getColumns')->with('books_x_tags')->once()->andReturn([
			new Column(name: 'book_id', primary: true, autoIncrement: false, vendor: []),
			new Column(name: 'tag_id', primary: true, autoIncrement: false, vendor: []),
		]);
		$this->driver->shouldReceive('getColumns')->with('books_view')->once()->andReturn([
			new Column(name: 'id', primary: false, autoIncrement: false, vendor: []),
			new Column(name: 'title', primary: false, autoIncrement: false, vendor: []),
		]);
		$this->connection->shouldReceive('getDriver')->times(4)->andReturn($this->driver);
		$this->driver->shouldReceive('getForeignKeys')->with('authors')->once()->andReturn([]);
		$this->driver->shouldReceive('getForeignKeys')->with('Books')->once()->andReturn([
			new ForeignKey(columns: ['author_id'], targetTable: 'authors', targetColumns: ['id'], name: 'authors_fk1'),
			new ForeignKey(columns: ['translator_id'], targetTable: 'authors', targetColumns: ['id'], name: 'authors_fk2'),
		]);
		$this->driver->shouldReceive('getForeignKeys')->with('tags')->once()->andReturn([]);
		$this->driver->shouldReceive('getForeignKeys')->with('books_x_tags')->once()->andReturn([
			new ForeignKey(columns: ['book_id'], targetTable: 'Books', targetColumns: ['id'], name: 'books_x_tags_fk1'),
			new ForeignKey(columns: ['tag_id'], targetTable: 'tags', targetColumns: ['id'], name: 'books_x_tags_fk2'),
		]);

		$this->structure = new StructureMock($this->connection, $this->storage);
	}


	public function testGetTables()
	{
		Assert::equal([
			new Table(name: 'authors', view: false),
			new Table(name: 'Books', view: false),
			new Table(name: 'tags', view: false),
			new Table(name: 'books_x_tags', view: false),
			new Table(name: 'books_view', view: true),
		], $this->structure->getTables());
	}


	public function testGetColumns()
	{
		$columns = [
			new Column(name: 'id', primary: true, autoIncrement: false, vendor: []),
			new Column(name: 'name', primary: false, autoIncrement: false, vendor: []),
		];

		Assert::equal($columns, $this->structure->getColumns('tags'));
		Assert::equal($columns, $this->structure->getColumns('Tags'));

		$structure = $this->structure;
		Assert::exception(function () use ($structure) {
			$structure->getColumns('InvaliD');
		}, Nette\InvalidArgumentException::class, "Table 'invalid' does not exist.");
	}


	public function testGetPrimaryKey()
	{
		Assert::same('id', $this->structure->getPrimaryKey('books'));
		Assert::same(['book_id', 'tag_id'], $this->structure->getPrimaryKey('Books_x_tags'));
		Assert::exception(function () {
			$this->structure->getPrimaryKey('invalid');
		}, Nette\InvalidArgumentException::class, "Table 'invalid' does not exist.");
	}


	public function testGetPrimaryKeySequence()
	{
		$this->connection->shouldReceive('getDriver')->times(4)->andReturn($this->driver);
		$this->driver->shouldReceive('isSupported')->with('sequence')->once()->andReturn(false);
		$this->driver->shouldReceive('isSupported')->with('sequence')->times(3)->andReturn(true);

		Assert::null($this->structure->getPrimaryKeySequence('Authors'));
		Assert::same('"public"."authors_id_seq"', $this->structure->getPrimaryKeySequence('Authors'));
		Assert::null($this->structure->getPrimaryKeySequence('tags'));
		Assert::null($this->structure->getPrimaryKeySequence('books_x_tags'));
	}


	public function testGetHasManyReference()
	{
		Assert::same([
			'Books' => ['author_id', 'translator_id'],
		], $this->structure->getHasManyReference('authors'));

		Assert::same(
			['author_id', 'translator_id'],
			$this->structure->getHasManyReference('authors', 'books'),
		);
	}


	public function testGetBelongsToReference()
	{
		Assert::same([], $this->structure->getBelongsToReference('authors'));

		Assert::same([
			'author_id' => 'authors',
			'translator_id' => 'authors',
		], $this->structure->getBelongsToReference('books'));

		Assert::same([
			'tag_id' => 'tags',
			'book_id' => 'Books',
		], $this->structure->getBelongsToReference('books_x_tags'));

		Assert::same(
			['Books', 'book_id'],
			$this->structure->getBelongsToReference('books_x_tags', 'book_id'),
		);

		Assert::null($this->structure->getBelongsToReference('books_x_tags', 'non_exist'));
	}


	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}
}


$test = new StructureTestCase;
$test->run();
