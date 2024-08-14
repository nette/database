<?php

/**
 * Test: Nette\Database\Table: tryDelimite.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

$sqlBuilder = new Nette\Database\Table\SqlBuilder('book', $explorer);
$tryDelimite = (new ReflectionClass($sqlBuilder))->getMethod('tryDelimite');
$tryDelimite->setAccessible(true);

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
