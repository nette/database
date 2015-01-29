<?php

/**
 * @dataProvider? databases.ini  postgresql
 */

use Tester\Assert,
	Nette\Utils\DateTime;

require __DIR__ . '/connect.inc.php'; // create $connection


$tests = function($connection) {
	$driver = $connection->getSupplementalDriver();
	Assert::false($connection->query("SELECT 'AAxBB' LIKE", $connection::literal($driver->formatLike('A_B', 0)))->fetchField());
	Assert::true( $connection->query("SELECT 'AA_BB' LIKE", $connection::literal($driver->formatLike('A_B', 0)))->fetchField());

	Assert::false($connection->query("SELECT 'AAxBB' LIKE", $connection::literal($driver->formatLike('A%B', 0)))->fetchField());
	Assert::true( $connection->query("SELECT 'AA%BB' LIKE", $connection::literal($driver->formatLike('A%B', 0)))->fetchField());

	Assert::same('AA\\BB', $connection->query("SELECT 'AA\\BB'")->fetchField());
	Assert::false($connection->query("SELECT 'AAxBB' LIKE", $connection::literal($driver->formatLike('A\\B', 0)))->fetchField());
	Assert::true( $connection->query("SELECT 'AA\\BB' LIKE", $connection::literal($driver->formatLike('A\\B', 0)))->fetchField());
};

$connection->query('SET escape_string_warning = off'); // do not log warnings

$connection->query('SET standard_conforming_strings = on');
$tests($connection);
$connection->query('SET standard_conforming_strings = off');
$tests($connection);
