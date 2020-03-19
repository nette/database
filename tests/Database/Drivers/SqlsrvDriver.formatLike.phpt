<?php

/**
 * @dataProvider? ../databases.ini  sqlsrv
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection


$driver = $connection->getDriver();

Assert::same(0, $connection->query("SELECT CASE WHEN 'AAxBB' LIKE", $connection::literal($driver->formatLike('A_B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());
Assert::same(1, $connection->query("SELECT CASE WHEN 'AA_BB' LIKE", $connection::literal($driver->formatLike('A_B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());

Assert::same(0, $connection->query("SELECT CASE WHEN 'AAxBB' LIKE", $connection::literal($driver->formatLike('A%B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());
Assert::same(1, $connection->query("SELECT CASE WHEN 'AA%BB' LIKE", $connection::literal($driver->formatLike('A%B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());

Assert::same(0, $connection->query("SELECT CASE WHEN 'AAxBB' LIKE", $connection::literal($driver->formatLike("A'B", 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());
Assert::same(1, $connection->query("SELECT CASE WHEN 'AA''BB' LIKE", $connection::literal($driver->formatLike("A'B", 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());

Assert::same(0, $connection->query("SELECT CASE WHEN 'AAxBB' LIKE", $connection::literal($driver->formatLike('A"B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());
Assert::same(1, $connection->query("SELECT CASE WHEN 'AA\"BB' LIKE", $connection::literal($driver->formatLike('A"B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());

Assert::same(0, $connection->query("SELECT CASE WHEN 'AAxBB' LIKE", $connection::literal($driver->formatLike('A\B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());
Assert::same(1, $connection->query("SELECT CASE WHEN 'AA\\BB' LIKE", $connection::literal($driver->formatLike('A\B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());

Assert::same(0, $connection->query("SELECT CASE WHEN 'AAxBB' LIKE", $connection::literal($driver->formatLike('A\%B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());
Assert::same(1, $connection->query("SELECT CASE WHEN 'AA\\%BB' LIKE", $connection::literal($driver->formatLike('A\%B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());

Assert::same(0, $connection->query("SELECT CASE WHEN 'AAxBB' LIKE", $connection::literal($driver->formatLike('A[a-z]B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());
Assert::same(1, $connection->query("SELECT CASE WHEN 'AA[a-z]BB' LIKE", $connection::literal($driver->formatLike('A[a-z]B', 0)), 'THEN 1 ELSE 0 END AS col')->fetchField());
