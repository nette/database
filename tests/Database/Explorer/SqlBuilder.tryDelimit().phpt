<?php

/**
 * Test: Nette\Database\Table: tryDelimit.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$explorer = connectToDB();

$sqlBuilder = new Nette\Database\Table\SqlBuilder('book', $explorer);
$tryDelimit = (new ReflectionClass($sqlBuilder))->getMethod('tryDelimit');
$tryDelimit->setAccessible(true);

Assert::same(reformat('[hello]'), $tryDelimit->invoke($sqlBuilder, 'hello'));
Assert::same(reformat(' [hello] '), $tryDelimit->invoke($sqlBuilder, ' hello '));
Assert::same(reformat('HELLO'), $tryDelimit->invoke($sqlBuilder, 'HELLO'));
Assert::same(reformat('[HellO]'), $tryDelimit->invoke($sqlBuilder, 'HellO'));
Assert::same(reformat('[hello].[world]'), $tryDelimit->invoke($sqlBuilder, 'hello.world'));
Assert::same(reformat('[hello] [world]'), $tryDelimit->invoke($sqlBuilder, 'hello world'));
Assert::same(reformat('HELLO([world])'), $tryDelimit->invoke($sqlBuilder, 'HELLO(world)'));
Assert::same(reformat('hello([world])'), $tryDelimit->invoke($sqlBuilder, 'hello(world)'));
Assert::same('[hello]', $tryDelimit->invoke($sqlBuilder, '[hello]'));
Assert::same(reformat('::int'), $tryDelimit->invoke($sqlBuilder, '::int'));
