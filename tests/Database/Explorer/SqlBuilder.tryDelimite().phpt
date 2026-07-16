<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Table: tryDelimite.
 * @dataProvider? ../databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

$sqlBuilder = new Nette\Database\Table\SqlBuilder('book', $explorer);
$tryDelimite = (new ReflectionClass($sqlBuilder))->getMethod('tryDelimite');

Assert::same(reformat('[hello]'), $tryDelimite->invoke($sqlBuilder, 'hello'));
Assert::same(reformat(' [hello] '), $tryDelimite->invoke($sqlBuilder, ' hello '));
Assert::same(reformat('HELLO'), $tryDelimite->invoke($sqlBuilder, 'HELLO'));
Assert::same(reformat('[HellO]'), $tryDelimite->invoke($sqlBuilder, 'HellO'));
Assert::same(reformat('[hello].[world]'), $tryDelimite->invoke($sqlBuilder, 'hello.world'));
Assert::same(reformat('[hello] [world]'), $tryDelimite->invoke($sqlBuilder, 'hello world'));
Assert::same(reformat('HELLO([world])'), $tryDelimite->invoke($sqlBuilder, 'HELLO(world)'));
Assert::same(reformat('hello([world])'), $tryDelimite->invoke($sqlBuilder, 'hello(world)'));
Assert::same('[hello]', $tryDelimite->invoke($sqlBuilder, '[hello]'));
Assert::same(reformat('::int'), $tryDelimite->invoke($sqlBuilder, '::int'));

// string literals are not supported, the content would be delimited as an identifier
Assert::error(
	fn() => $tryDelimite->invoke($sqlBuilder, "name = 'abc'"),
	E_USER_WARNING,
	"SQL string literals are not supported here, pass the value as a parameter instead: name = 'abc'",
);
