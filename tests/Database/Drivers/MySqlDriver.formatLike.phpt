<?php

/**
 * @dataProvider? ../databases.ini  mysql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$connection = connectToDB();
$engine = $connection->getDatabaseEngine();

Assert::same(0, $connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike('A_B', 0)))->fetchField());
Assert::same(1, $connection->query("SELECT 'AA_BB' LIKE", $connection::literal($engine->formatLike('A_B', 0)))->fetchField());

Assert::same(0, $connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike('A%B', 0)))->fetchField());
Assert::same(1, $connection->query("SELECT 'AA%BB' LIKE", $connection::literal($engine->formatLike('A%B', 0)))->fetchField());

Assert::same(0, $connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike("A'B", 0)))->fetchField());
Assert::same(1, $connection->query("SELECT 'AA''BB' LIKE", $connection::literal($engine->formatLike("A'B", 0)))->fetchField());

Assert::same(0, $connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike('A"B', 0)))->fetchField());
Assert::same(1, $connection->query("SELECT 'AA\"BB' LIKE", $connection::literal($engine->formatLike('A"B', 0)))->fetchField());

Assert::same(0, $connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike('A\B', 0)))->fetchField());
Assert::same(1, $connection->query("SELECT 'AA\\\\BB' LIKE", $connection::literal($engine->formatLike('A\B', 0)))->fetchField());

Assert::same(0, $connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike('A\%B', 0)))->fetchField());
Assert::same(1, $connection->query("SELECT 'AA\\\\%BB' LIKE", $connection::literal($engine->formatLike('A\%B', 0)))->fetchField());
