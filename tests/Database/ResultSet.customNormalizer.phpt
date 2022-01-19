<?php

declare(strict_types=1);

/**
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");
$connection->query('UPDATE author SET born=?', new DateTime('2022-01-23'));


test('disabled normalization', function () use ($connection) {
	$driverName = $GLOBALS['driverName'];

	$connection->setRowNormalizer(null);
	$res = $connection->query('SELECT * FROM author');
	$asInt = $driverName === 'pgsql' || ($driverName !== 'sqlsrv' && PHP_VERSION_ID >= 80100);
	Assert::same([
		'id' => $asInt ? 11 : '11',
		'name' => 'Jakub Vrana',
		'web' => 'http://www.vrana.cz/',
		'born' => $driverName === 'sqlite' ? ($asInt ? 1_642_892_400 : '1642892400') : '2022-01-23',
	], (array) $res->fetch());
});


test('custom normalization', function () use ($connection) {
	$driverName = $GLOBALS['driverName'];

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
		'_born_' => $driverName === 'sqlite' ? '1642892400' : '2022-01-23',
	], (array) $res->fetch());
});
