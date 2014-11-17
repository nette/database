<?php

/**
 * Test: Nette\Database\Structure
 */

use Mockery\MockInterface;
use Nette\Database\Structure;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';


class StructureMock extends Structure
{
	protected function needStructure()
	{
		if (!$this->structure) {
			$this->structure = $this->loadStructure();
		}
	}
}


/**
 * @testCase
 */
class StructureSchemasTestCase extends TestCase
{
	/** @var MockInterface */
	private $connection;

	/** @var MockInterface */
	private $driver;

	/** @var MockInterface */
	private $storage;

	/** @var Structure */
	private $structure;


	protected function setUp()
	{
		parent::setUp();
		$this->driver = Mockery::mock('Nette\Database\ISupplementalDriver');
		$this->connection = Mockery::mock('Nette\Database\Connection');
		$this->storage = Mockery::mock('Nette\Caching\IStorage');

		$this->connection->shouldReceive('getDsn')->once()->andReturn('');
		$this->connection->shouldReceive('getSupplementalDriver')->once()->andReturn($this->driver);
		$this->driver->shouldReceive('getTables')->once()->andReturn(array(
			array('name' => 'authors', 'view' => FALSE, 'fullName' => 'authors.authors'),
			array('name' => 'books', 'view' => FALSE, 'fullName' => 'books.books'),
		));
		$this->driver->shouldReceive('getColumns')->with('authors.authors')->once()->andReturn(array(
			array('name' => 'id', 'primary' => TRUE, 'vendor' => array('sequence' => '"authors"."authors_id_seq"')),
			array('name' => 'name', 'primary' => FALSE, 'vendor' => array()),
		));
		$this->driver->shouldReceive('getColumns')->with('books.books')->once()->andReturn(array(
			array('name' => 'id', 'primary' => TRUE, 'vendor' => array('sequence' => '"books"."books_id_seq"')),
			array('name' => 'title', 'primary' => FALSE, 'vendor' => array()),
		));

		$this->connection->shouldReceive('getSupplementalDriver')->times(2)->andReturn($this->driver);
		$this->driver->shouldReceive('getForeignKeys')->with('authors.authors')->once()->andReturn(array());
		$this->driver->shouldReceive('getForeignKeys')->with('books.books')->once()->andReturn(array(
			array('local' => 'author_id', 'table' => 'authors.authors', 'foreign' => 'id'),
			array('local' => 'translator_id', 'table' => 'authors.authors', 'foreign' => 'id'),
		));

		$this->structure = new StructureMock($this->connection, $this->storage);
	}


	public function testGetHasManyReference()
	{
		Assert::same(array(
			'books.books' => array('author_id', 'translator_id'),
		), $this->structure->getHasManyReference('authors'));

		Assert::same(array(
			'books.books' => array('author_id', 'translator_id'),
		), $this->structure->getHasManyReference('authors.authors'));
	}


	public function testGetBelongsToReference()
	{
		Assert::same(array(), $this->structure->getBelongsToReference('authors'));
		Assert::same(array(), $this->structure->getBelongsToReference('authors.authors'));

		Assert::same(array(
			'author_id' => 'authors.authors',
			'translator_id' => 'authors.authors',
		), $this->structure->getBelongsToReference('books'));

		Assert::same(array(
			'author_id' => 'authors.authors',
			'translator_id' => 'authors.authors',
		), $this->structure->getBelongsToReference('books.books'));;
	}


	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}

}


$test = new StructureSchemasTestCase();
$test->run();
