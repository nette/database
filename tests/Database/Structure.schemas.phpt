<?php

/**
 * Test: Nette\Database\Structure
 */

declare(strict_types=1);

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
class StructureSchemasTestCase extends TestCase
{
	private Nette\Database\Connection $connection;
	private Nette\Database\Driver $driver;
	private Nette\Caching\Storage $storage;
	private Structure $structure;


	protected function setUp()
	{
		parent::setUp();
		$this->driver = Mockery::mock(Nette\Database\Driver::class);
		$this->connection = Mockery::mock(Nette\Database\Connection::class);
		$this->storage = Mockery::mock(Nette\Caching\Storage::class);

		$this->connection->shouldReceive('getDsn')->once()->andReturn('');
		$this->connection->shouldReceive('getDriver')->once()->andReturn($this->driver);
		$this->driver->shouldReceive('getTables')->once()->andReturn([
			['name' => 'authors', 'view' => false, 'fullName' => 'authors.authors'],
			['name' => 'books', 'view' => false, 'fullName' => 'books.books'],
		]);
		$this->driver->shouldReceive('getColumns')->with('authors.authors')->once()->andReturn([
			['name' => 'id', 'primary' => true, 'vendor' => ['sequence' => '"authors"."authors_id_seq"']],
			['name' => 'name', 'primary' => false, 'vendor' => []],
		]);
		$this->driver->shouldReceive('getColumns')->with('books.books')->once()->andReturn([
			['name' => 'id', 'primary' => true, 'vendor' => ['sequence' => '"books"."books_id_seq"']],
			['name' => 'title', 'primary' => false, 'vendor' => []],
		]);

		$this->connection->shouldReceive('getDriver')->times(2)->andReturn($this->driver);
		$this->driver->shouldReceive('getForeignKeys')->with('authors.authors')->once()->andReturn([]);
		$this->driver->shouldReceive('getForeignKeys')->with('books.books')->once()->andReturn([
			['local' => 'author_id', 'table' => 'authors.authors', 'foreign' => 'id', 'name' => 'authors_authors_fk1'],
			['local' => 'translator_id', 'table' => 'authors.authors', 'foreign' => 'id', 'name' => 'authors_authors_fk2'],
		]);

		$this->structure = new StructureMock($this->connection, $this->storage);
	}


	public function testGetHasManyReference()
	{
		Assert::same([
			'books.books' => ['author_id', 'translator_id'],
		], $this->structure->getHasManyReference('authors'));

		Assert::same([
			'books.books' => ['author_id', 'translator_id'],
		], $this->structure->getHasManyReference('authors.authors'));
	}


	public function testGetBelongsToReference()
	{
		Assert::same([], $this->structure->getBelongsToReference('authors'));
		Assert::same([], $this->structure->getBelongsToReference('authors.authors'));

		Assert::same([
			'author_id' => 'authors.authors',
			'translator_id' => 'authors.authors',
		], $this->structure->getBelongsToReference('books'));

		Assert::same([
			'author_id' => 'authors.authors',
			'translator_id' => 'authors.authors',
		], $this->structure->getBelongsToReference('books.books'));
	}


	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}
}


$test = new StructureSchemasTestCase;
$test->run();
