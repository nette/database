<?php

/**
 * Test: Nette\Database\Helpers::dumpSql().
 * @dataProvider? databases.ini  mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test(function () use ($connection) { // int check
	Assert::same(
"<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong> id \n<strong style=\"color:blue\">FROM</strong> author \n<strong style=\"color:blue\">WHERE</strong> id = 10 <strong style=\"color:green\">OR</strong> id = 11</pre>\n", Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE id = ? OR id = ?', [10, 11], $connection));
});

test(function () use ($connection) { // string check
	Assert::same(
"<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong> id \n<strong style=\"color:blue\">FROM</strong> author \n<strong style=\"color:blue\">WHERE</strong> name = <span title=\"Length 15 characters\">'Alexej Chruščev'</span></pre>\n", Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ['Alexej Chruščev'], $connection));
});

test(function () use ($connection) { // string check with \'
	Assert::same(
"<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong> id \n<strong style=\"color:blue\">FROM</strong> author \n<strong style=\"color:blue\">WHERE</strong> name = <span title=\"Length 16 characters\">'Alexej Ch\'ruščev'</span></pre>\n", Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ["Alexej Ch'ruščev"], $connection));
});

test(function () { // string check without connection
	Assert::same(
"<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong> id \n<strong style=\"color:blue\">FROM</strong> author \n<strong style=\"color:blue\">WHERE</strong> name = <span title=\"Length 16 characters\">'Alexej Ch'ruščev'</span></pre>\n", Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ["Alexej Ch'ruščev"]));
});


test(function () use ($connection) { // string compare with $connection vs without
	Assert::notSame(Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ["Alexej Ch'ruščev"], $connection), Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ["Alexej Ch'ruščev"]));
});

test(function () use ($connection) { // string check with \'
	Nette\Database\Helpers::$maxLength = 10;
	Assert::same(
"<pre class=\"dump\"><strong style=\"color:blue\">SELECT</strong> id \n<strong style=\"color:blue\">FROM</strong> author \n<strong style=\"color:blue\">WHERE</strong> name = <span title=\"Length 16 characters\">'Alexej Ch…'</span></pre>\n", Nette\Database\Helpers::dumpSql('SELECT id FROM author WHERE name = ?', ["Alexej Ch'ruščev"], $connection));
});
