<?php

/**
 * @dataProvider? ../databases.ini  postgresql
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

$connection = connectToDB()->getConnection();

$tests = function ($connection) {
	$engine = $connection->getDatabaseEngine();

	Assert::false($connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike('A_B', 0)))->fetchField());
	Assert::true($connection->query("SELECT 'AA_BB' LIKE", $connection::literal($engine->formatLike('A_B', 0)))->fetchField());

	Assert::false($connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike('A%B', 0)))->fetchField());
	Assert::true($connection->query("SELECT 'AA%BB' LIKE", $connection::literal($engine->formatLike('A%B', 0)))->fetchField());

	Assert::false($connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike("A'B", 0)))->fetchField());
	Assert::true($connection->query("SELECT 'AA''BB' LIKE", $connection::literal($engine->formatLike("A'B", 0)))->fetchField());

	Assert::false($connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike('A"B', 0)))->fetchField());
	Assert::true($connection->query("SELECT 'AA\"BB' LIKE", $connection::literal($engine->formatLike('A"B', 0)))->fetchField());
};

$engine = $connection->getDatabaseEngine();
$connection->query('SET escape_string_warning TO off'); // do not log warnings

$connection->query('SET standard_conforming_strings TO on');
$tests($connection);
Assert::false($connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike('A\\B', 0)))->fetchField());
Assert::true($connection->query("SELECT 'AA\\BB' LIKE", $connection::literal($engine->formatLike('A\\B', 0)))->fetchField());

$connection->query('SET standard_conforming_strings TO off');
$tests($connection);
Assert::false($connection->query("SELECT 'AAxBB' LIKE", $connection::literal($engine->formatLike('A\\B', 0)))->fetchField());
Assert::true($connection->query("SELECT 'AA\\\\BB' LIKE", $connection::literal($engine->formatLike('A\\B', 0)))->fetchField());
