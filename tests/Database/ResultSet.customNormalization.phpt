<?php

/**
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/files/{$driverName}-nette_test1.sql");

class CustomRowNormalizer implements \Nette\Database\IRowNormalizer
{
	function normalizeRow($row, \Nette\Database\ResultSet $resultSet)
	{
		foreach ($row as $key => $value) {
			unset($row[$key]);
			$row['_'.$key.'_'] = (string) $value;
		}
		return $row;
	}
}

test(function() use ($context) {
	$res = $context->query('SELECT * FROM author');
	$res->setRowNormalizer(new CustomRowNormalizer());
	Assert::equal([
		'_id_' => '11',
		'_name_' => 'Jakub Vrana',
		'_web_' => 'http://www.vrana.cz/',
		'_born_' => ''
	], (array)$res->fetch());
});
