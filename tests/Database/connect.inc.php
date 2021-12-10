<?php

/**
 * Test: Nette\Database test bootstrap.
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';


$options = Tester\Environment::loadData() + ['user' => null, 'password' => null];

try {
	$connection = new Nette\Database\Connection($options['dsn'], $options['user'], $options['password']);
} catch (PDOException $e) {
	Tester\Environment::skip("Connection to '$options[dsn]' failed. Reason: " . $e->getMessage());
}

if (strpos($options['dsn'], 'sqlite::memory:') === false) {
	Tester\Environment::lock($options['dsn'], TEMP_DIR);
}

$driverName = $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
$cacheMemoryStorage = new Nette\Caching\Storages\MemoryStorage;

$structure = new Nette\Database\Structure($connection, $cacheMemoryStorage);
$conventions = new Nette\Database\Conventions\DiscoveredConventions($structure);
$explorer = new Nette\Database\Explorer($connection, $structure, $conventions, $cacheMemoryStorage);

echo "Driver: $driverName\n";


/** Replaces [] with driver-specific quotes */
function reformat($s): string
{
	global $driverName;
	if (is_array($s)) {
		if (isset($s[$driverName])) {
			return $s[$driverName];
		}

		$s = $s[0];
	}

	if ($driverName === 'mysql') {
		return strtr($s, '[]', '``');
	} elseif ($driverName === 'pgsql') {
		return strtr($s, '[]', '""');
	} elseif ($driverName === 'sqlsrv' || $driverName === 'sqlite') {
		return $s;
	} else {
		trigger_error("Unsupported driver $driverName", E_USER_WARNING);
	}
}
