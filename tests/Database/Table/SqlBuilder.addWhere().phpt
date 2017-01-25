<?php

/**
 * Test: Nette\Database\Table\SqlBuilder: addWhere() and placeholders.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;
use Nette\Database\SqlLiteral;
use Nette\Database\Conventions\DiscoveredConventions;
use Nette\Database\Table\SqlBuilder;
use Nette\Database\ISupplementalDriver;

require __DIR__ . '/../connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");


test(function () use ($context) { // test paramateres with NULL
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id ? OR id ?', [1, NULL]);
	$sqlBuilder->addWhere('id ? OR id ?', [1, NULL]); // duplicit condition
	Assert::same(reformat('SELECT * FROM [book] WHERE ([id] = ? OR [id] IS NULL)'), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // ?name
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('?name ?', 'id', 3);
	$sqlBuilder->addWhere('?name = ?', 'number', 4);
	$sqlBuilder->addWhere('?name ?', 'number', NULL);
	Assert::same(reformat('SELECT * FROM [book] WHERE (?name = ?) AND (?name = ?) AND (?name IS NULL)'), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // test Selection as a parameter
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id', $context->table('book'));
	Assert::equal(reformat([
		'mysql' => 'SELECT * FROM `book` WHERE (`id` IN (?))',
		'SELECT * FROM [book] WHERE ([id] IN (SELECT [id] FROM [book]))',
	]), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // test more Selection as a parameter
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id', $context->table('book'));
	$sqlBuilder->addWhere('id', $context->table('book_tag')->select('book_id'));
	Assert::equal(reformat([
		'mysql' => 'SELECT * FROM `book` WHERE (`id` IN (?)) AND (`id` IN (?))',
		'SELECT * FROM [book] WHERE ([id] IN (SELECT [id] FROM [book])) AND ([id] IN (SELECT [book_id] FROM [book_tag]))',
	]), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // test more ActiveRow as a parameter
	$sqlBuilder = new SqlBuilder('book', $context);
	$books = $context->table('book')->where('id', [1,2])->fetchPairs('id');
	$sqlBuilder->addWhere('id ?', $books[1]);
	$sqlBuilder->addWhere('id ?', $books[2]);
	Assert::equal(reformat([
		'SELECT * FROM [book] WHERE ([id] = ?) AND ([id] = ?)',
	]), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // test Selection with parameters as a parameter
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id', $context->table('book')->having('COUNT(:book_tag.tag_id) >', 1));
	$schemaSupported = $context->getConnection()->getSupplementalDriver()->isSupported(ISupplementalDriver::SUPPORT_SCHEMA);
	Assert::equal(reformat([
		'mysql' => 'SELECT * FROM `book` WHERE (`id` IN (?))',
		'SELECT * FROM [book] WHERE ([id] IN (SELECT [id] FROM [book] LEFT JOIN ' . ($schemaSupported ? '[public].[book_tag] ' : '') . '[book_tag] ON [book].[id] = [book_tag].[book_id] HAVING COUNT([book_tag].[tag_id]) >))',
	]), $sqlBuilder->buildSelectQuery());
	Assert::count(1, $sqlBuilder->getParameters());
});


test(function () use ($context) { // test Selection with column as a parameter
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id', $context->table('book')->select('id'));
	Assert::equal(reformat([
		'mysql' => 'SELECT * FROM `book` WHERE (`id` IN (?))',
		'SELECT * FROM [book] WHERE ([id] IN (SELECT [id] FROM [book]))',
	]), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // test multiple placeholder parameter
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id ? OR id ?', NULL, $context->table('book'));
	Assert::equal(reformat([
		'mysql' => 'SELECT * FROM `book` WHERE (`id` IS NULL OR `id` IN (?))',
		'SELECT * FROM [book] WHERE ([id] IS NULL OR [id] IN (SELECT [id] FROM [book]))',
	]), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // test SqlLiteral
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id IN (?)', new SqlLiteral('1, 2, 3'));
	Assert::same(reformat('SELECT * FROM [book] WHERE ([id] IN (?))'), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // test auto type detection
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id ? OR id ? OR id ?', 1, 'test', [1, 2]);
	Assert::same(reformat('SELECT * FROM [book] WHERE ([id] = ? OR [id] = ? OR [id] IN (?))'), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // test empty array
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id', []);
	$sqlBuilder->addWhere('id NOT', []);
	$sqlBuilder->addWhere('NOT (id ?)', []);

	Assert::exception(function () use ($sqlBuilder) {
		$sqlBuilder->addWhere('TRUE AND id', []);
	}, Nette\InvalidArgumentException::class, 'Possible SQL query corruption. Add parentheses around operators.');

	Assert::exception(function () use ($sqlBuilder) {
		$sqlBuilder->addWhere('NOT id', []);
	}, Nette\InvalidArgumentException::class, 'Possible SQL query corruption. Add parentheses around operators.');

	Assert::same(reformat('SELECT * FROM [book] WHERE ([id] IS NULL AND FALSE) AND ([id] IS NULL OR TRUE) AND (NOT ([id] IS NULL AND FALSE))'), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // backward compatibility
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id = ? OR id ? OR id IN ? OR id LIKE ? OR id > ?', 1, 2, [1, 2], '%test', 3);
	$sqlBuilder->addWhere('name', 'var');
	$sqlBuilder->addWhere('MAIN', 0); // "IN" is not considered as the operator
	$sqlBuilder->addWhere('id IN (?)', [1, 2]);
	Assert::same(reformat('SELECT * FROM [book] WHERE ([id] = ? OR [id] = ? OR [id] IN (?) OR [id] LIKE ? OR [id] > ?) AND ([name] = ?) AND (MAIN = ?) AND ([id] IN (?))'), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // auto operator tests
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('FOO(?)', 1);
	$sqlBuilder->addWhere('FOO(id, ?)', 1);
	$sqlBuilder->addWhere('id & ? = ?', 1, 1);
	$sqlBuilder->addWhere('?', 1);
	$sqlBuilder->addWhere('NOT ? OR ?', 1, 1);
	$sqlBuilder->addWhere('? + ? - ? / ? * ? % ?', 1, 1, 1, 1, 1, 1);
	Assert::same(reformat('SELECT * FROM [book] WHERE (FOO(?)) AND (FOO([id], ?)) AND ([id] & ? = ?) AND (?) AND (NOT ? OR ?) AND (? + ? - ? / ? * ? % ?)'), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // tests multiline condition
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere("\ncol1 ?\nOR col2 ?\n", 1, 1);
	Assert::same(reformat("SELECT * FROM [book] WHERE ([col1] = ?\nOR [col2] = ?)"), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // tests NOT
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id NOT', [1, 2]);
	$sqlBuilder->addWhere('id NOT', NULL);
	$sqlBuilder->addWhere('id NOT', $context->table('book')->select('id'));
	Assert::equal(reformat([
		'mysql' => 'SELECT * FROM `book` WHERE (`id` NOT IN (?)) AND (`id` IS NOT NULL) AND (`id` NOT IN (?))',
		'SELECT * FROM [book] WHERE ([id] NOT IN (?)) AND ([id] IS NOT NULL) AND ([id] NOT IN (SELECT [id] FROM [book]))',
	]), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // tests multi column IN clause
	$sqlBuilder = new SqlBuilder('book_tag', $context);
	$sqlBuilder->addWhere(['book_id', 'tag_id'], [[1, 11], [2, 12]]);
	Assert::equal(reformat([
		'sqlite' => 'SELECT * FROM [book_tag] WHERE (([book_id] = ? AND [tag_id] = ?) OR ([book_id] = ? AND [tag_id] = ?))',
		'mysql' => 'SELECT * FROM `book_tag` WHERE ((`book_id` = ? AND `tag_id` = ?) OR (`book_id` = ? AND `tag_id` = ?))',
		'SELECT * FROM [book_tag] WHERE (([book_id], [tag_id]) IN (?))',
	]), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) { // tests operator suffix
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id <> ? OR id >= ?', 1, 2);
	Assert::same(reformat('SELECT * FROM [book] WHERE ([id] <> ? OR [id] >= ?)'), $sqlBuilder->buildSelectQuery());
});


test(function () use ($context) {
	$books = $context->table('book')->where('id',
		$context->table('book_tag')->select('book_id')->where('tag_id', 21)
	);
	Assert::same(3, $books->count());
});


Assert::exception(function () use ($context) {
	$context->table('book')->where('id',
		$context->table('book_tag')->where('tag_id', 21)
	);
}, Nette\InvalidArgumentException::class, 'Selection argument must have defined a select column.');


Assert::exception(function () use ($context) {
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id ?');
}, Nette\InvalidArgumentException::class, 'Argument count does not match placeholder count.');


Assert::exception(function () use ($context) {
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id = ?', NULL);
}, Nette\InvalidArgumentException::class, 'Column operator does not accept NULL argument.');


Assert::exception(function () use ($context) {
	$sqlBuilder = new SqlBuilder('book', $context);
	$sqlBuilder->addWhere('id = ?', [1, 2]);
}, Nette\InvalidArgumentException::class, 'Column operator does not accept array argument.');


test(function () use ($driverName, $context, $connection, $structure) {
	switch ($driverName) {
		case 'mysql':
			$context->query('CREATE INDEX book_tag_unique ON book_tag (book_id, tag_id)');
			$context->query('ALTER TABLE book_tag DROP PRIMARY KEY');
			break;
		case 'pgsql':
			$context->query('ALTER TABLE book_tag DROP CONSTRAINT "book_tag_pkey"');
			break;
		case 'sqlite':
			// dropping constraint or column is not supported
			$context->query('
				CREATE TABLE book_tag_temp (
					book_id INTEGER NOT NULL,
					tag_id INTEGER NOT NULL,
					CONSTRAINT book_tag_tag FOREIGN KEY (tag_id) REFERENCES tag (id),
					CONSTRAINT book_tag_book FOREIGN KEY (book_id) REFERENCES book (id) ON DELETE CASCADE
				)
			');
			$context->query('INSERT INTO book_tag_temp SELECT book_id, tag_id FROM book_tag');
			$context->query('DROP TABLE book_tag');
			$context->query('ALTER TABLE book_tag_temp RENAME TO book_tag');
			break;
		case 'sqlsrv':
			$context->query('ALTER TABLE book_tag DROP CONSTRAINT PK_book_tag');
			break;
		default:
			Assert::fail("Unsupported driver $driverName");
	}

	$structure->rebuild();
	$conventions = new DiscoveredConventions($structure);
	$dao = new Nette\Database\Context($connection, $structure, $conventions);

	$e = Assert::exception(function () use ($dao) {
		$books = $dao->table('book')->where('id',
			$dao->table('book_tag')->where('tag_id', 21)
		);
		$books->fetch();
	}, Nette\InvalidArgumentException::class, 'Selection argument must have defined a select column.');

	Assert::exception(function () use ($e) {
		throw $e->getPrevious();
	}, LogicException::class, "Table 'book_tag' does not have a primary key.");
});
