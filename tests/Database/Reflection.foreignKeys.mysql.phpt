<?php declare(strict_types=1);

/**
 * Test: MySqlDriver::getForeignKeys() column order
 * @dataProvider? databases.ini  mysql
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
$driver = $connection->getDriver();

$connection->query('SET foreign_key_checks = 0');
$connection->query('DROP TABLE IF EXISTS fk_child, fk_parent');
$connection->query('SET foreign_key_checks = 1');
$connection->query('CREATE TABLE fk_parent (b INT NOT NULL, a INT NOT NULL, PRIMARY KEY (b, a))');
$connection->query('CREATE TABLE fk_child (x INT, y INT, CONSTRAINT fk_comp FOREIGN KEY (y, x) REFERENCES fk_parent (b, a))');


test('composite foreign key reports columns in constraint order', function () use ($driver) {
	Assert::same([
		['name' => 'fk_comp', 'local' => 'y', 'table' => 'fk_parent', 'foreign' => 'b'],
		['name' => 'fk_comp', 'local' => 'x', 'table' => 'fk_parent', 'foreign' => 'a'],
	], $driver->getForeignKeys('fk_child'));
});
