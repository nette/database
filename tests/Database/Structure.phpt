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
class StructureTestCase extends TestCase
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
			array('name' => 'authors', 'view' => FALSE),
			array('name' => 'Books', 'view' => FALSE),
			array('name' => 'tags', 'view' => FALSE),
			array('name' => 'books_x_tags', 'view' => FALSE),
			array('name' => 'books_view', 'view' => TRUE),
		));
		$this->driver->shouldReceive('getColumns')->with('authors')->once()->andReturn(array(
			array('name' => 'id', 'primary' => TRUE, 'vendor' => array('sequence' => '"public"."authors_id_seq"')),
			array('name' => 'name', 'primary' => FALSE, 'vendor' => array()),
		));
		$this->driver->shouldReceive('getColumns')->with('Books')->once()->andReturn(array(
			array('name' => 'id', 'primary' => TRUE, 'vendor' => array('sequence' => '"public"."Books_id_seq"')),
			array('name' => 'title', 'primary' => FALSE, 'vendor' => array()),
		));
		$this->driver->shouldReceive('getColumns')->with('tags')->once()->andReturn(array(
			array('name' => 'id', 'primary' => TRUE, 'vendor' => array()),
			array('name' => 'name', 'primary' => FALSE, 'vendor' => array()),
		));
		$this->driver->shouldReceive('getColumns')->with('books_x_tags')->once()->andReturn(array(
			array('name' => 'book_id', 'primary' => TRUE, 'vendor' => array()),
			array('name' => 'tag_id', 'primary' => TRUE, 'vendor' => array()),
		));
		$this->connection->shouldReceive('getSupplementalDriver')->times(4)->andReturn($this->driver);
		$this->driver->shouldReceive('getForeignKeys')->with('authors')->once()->andReturn(array());
		$this->driver->shouldReceive('getForeignKeys')->with('Books')->once()->andReturn(array(
			array('local' => 'author_id', 'table' => 'authors', 'foreign' => 'id'),
			array('local' => 'translator_id', 'table' => 'authors', 'foreign' => 'id'),
		));
		$this->driver->shouldReceive('getForeignKeys')->with('tags')->once()->andReturn(array());
		$this->driver->shouldReceive('getForeignKeys')->with('books_x_tags')->once()->andReturn(array(
			array('local' => 'book_id', 'table' => 'Books', 'foreign' => 'id'),
			array('local' => 'tag_id', 'table' => 'tags', 'foreign' => 'id'),
		));

		$this->structure = new StructureMock($this->connection, $this->storage);
	}


	public function testGetTables()
	{
		Assert::same(array(
			array('name' => 'authors', 'view' => FALSE),
			array('name' => 'Books', 'view' => FALSE),
			array('name' => 'tags', 'view' => FALSE),
			array('name' => 'books_x_tags', 'view' => FALSE),
			array('name' => 'books_view', 'view' => TRUE),
		), $this->structure->getTables());
	}


	public function testGetColumns()
	{
		$columns = array(
			array('name' => 'id', 'primary' => TRUE, 'vendor' => array()),
			array('name' => 'name', 'primary' => FALSE, 'vendor' => array()),
		);

		Assert::same($columns, $this->structure->getColumns('tags'));
		Assert::same($columns, $this->structure->getColumns('Tags'));

		$structure = $this->structure;
		Assert::exception(function () use ($structure) {
			$structure->getColumns('InvaliD');
		}, 'Nette\InvalidArgumentException', "Table 'invalid' does not exist.");
	}


	public function testGetPrimaryKey()
	{
		Assert::same('id', $this->structure->getPrimaryKey('books'));
		Assert::same(array('book_id', 'tag_id'), $this->structure->getPrimaryKey('Books_x_tags'));
		Assert::null($this->structure->getPrimaryKey('invalid'));
	}


	public function testGetPrimaryKeySequence()
	{
		$this->connection->shouldReceive('getSupplementalDriver')->times(4)->andReturn($this->driver);
		$this->driver->shouldReceive('isSupported')->with('sequence')->once()->andReturn(FALSE);
		$this->driver->shouldReceive('isSupported')->with('sequence')->times(3)->andReturn(TRUE);

		Assert::null($this->structure->getPrimaryKeySequence('Authors'));
		Assert::same('"public"."authors_id_seq"', $this->structure->getPrimaryKeySequence('Authors'));
		Assert::null($this->structure->getPrimaryKeySequence('tags'));
		Assert::null($this->structure->getPrimaryKeySequence('books_x_tags'));
	}


	public function testGetHasManyReference()
	{
		Assert::same(array(
			'Books' => array('author_id', 'translator_id'),
		), $this->structure->getHasManyReference('authors'));

		Assert::same(
			array('author_id', 'translator_id'),
			$this->structure->getHasManyReference('authors', 'books')
		);
	}


	public function testGetBelongsToReference()
	{
		Assert::same(array(), $this->structure->getBelongsToReference('authors'));

		Assert::same(array(
			'author_id' => 'authors',
			'translator_id' => 'authors',
		), $this->structure->getBelongsToReference('books'));

		Assert::same(array(
			'tag_id' => 'tags',
			'book_id' => 'Books',
		), $this->structure->getBelongsToReference('books_x_tags'));
	}


	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}

}


$test = new StructureTestCase();
$test->run();
