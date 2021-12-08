<?php

declare(strict_types=1);

/**
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");


test('disabled normalization', function () use ($connection) {
	global $driverName;

	$connection->setRowNormalizer(null);
	$res = $connection->query('SELECT * FROM author');
	$asInt = $driverName === 'pgsql' || ($driverName !== 'sqlsrv' && PHP_VERSION_ID >= 80100);
	Assert::same([
		'id' => $asInt ? 11 : '11',
		'name' => 'Jakub Vrana',
		'web' => 'http://www.vrana.cz/',
		'born' => null,
	], (array) $res->fetch());
});


test('custom normalization', function () use ($connection) {
	$connection->setRowNormalizer(function (array $row, Nette\Database\ResultSet $resultSet) {
		foreach ($row as $key => $value) {
			unset($row[$key]);
			$row['_' . $key . '_'] = (string) $value;
		}
		return $row;
	});

	$res = $connection->query('SELECT * FROM author');
	Assert::same([
		'_id_' => '11',
		'_name_' => 'Jakub Vrana',
		'_web_' => 'http://www.vrana.cz/',
		'_born_' => '',
	], (array) $res->fetch());
});
