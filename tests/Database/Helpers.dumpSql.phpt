<?php

/**
 * Test: Nette\Database\Helpers::dumpSql().
 * @dataProvider? databases.ini  mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();
Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('int check', function () use ($connection) {
	Assert::same(
		"<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong> id \n<strong style=\"color:blue\">FROM</strong> author \n<strong style=\"color:blue\">WHERE</strong> id = 10 <strong style=\"color:green\">OR</strong> id = 11</pre>\n",
		Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE id = ? OR id = ?', [10, 11], $connection),
	);
});

test('bool check', function () use ($connection) {
	Assert::same(
		"<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong> id \n<strong style=\"color:blue\">FROM</strong> author \n<strong style=\"color:blue\">WHERE</strong> deleted = 0</pre>\n",
		Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE deleted = ?', [false], $connection),
	);
});

test('string check', function () use ($connection) {
	Assert::same(
		"<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong> id \n<strong style=\"color:blue\">FROM</strong> author \n<strong style=\"color:blue\">WHERE</strong> name = <span title=\"Length 15 characters\">'Alexej Chruščev'</span></pre>\n",
		Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ['Alexej Chruščev'], $connection),
	);
});

test('string check with \'', function () use ($connection) {
	Assert::same(
		"<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong> id \n<strong style=\"color:blue\">FROM</strong> author \n<strong style=\"color:blue\">WHERE</strong> name = <span title=\"Length 16 characters\">'Alexej Ch\\'ruščev'</span></pre>\n",
		Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ["Alexej Ch'ruščev"], $connection),
	);
});

test('string check without connection', function () {
	Assert::same(
		"<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong> id \n<strong style=\"color:blue\">FROM</strong> author \n<strong style=\"color:blue\">WHERE</strong> name = <span title=\"Length 16 characters\">'Alexej Ch'ruščev'</span></pre>\n",
		Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ["Alexej Ch'ruščev"]),
	);
});


test('string compare with $connection vs without', function () use ($connection) {
	Assert::notSame(Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ["Alexej Ch'ruščev"], $connection), Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ["Alexej Ch'ruščev"]));
});

test('string check with \'', function () use ($connection) {
	Nette\Database\Helpers::$maxLength = 10;
	Assert::same(
		"<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong> id \n<strong style=\"color:blue\">FROM</strong> author \n<strong style=\"color:blue\">WHERE</strong> name = <span title=\"Length 16 characters\">'Alexej Ch…'</span></pre>\n",
		Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ["Alexej Ch'ruščev"], $connection),
	);
});
