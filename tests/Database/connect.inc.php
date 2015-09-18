<?php

/**
 * Test: Nette\Database test bootstrap.
 */

require __DIR__ . '/../bootstrap.php';


try {
	$options = Tester\Environment::loadData() + ['user' => NULL, 'password' => NULL];
} catch (Exception $e) {
	Tester\Environment::skip($e->getMessage());
}

try {
	$connection = new Nette\Database\Connection($options['dsn'], $options['user'], $options['password']);
} catch (PDOException $e) {
	Tester\Environment::skip("Connection to '$options[dsn]' failed. Reason: " . $e->getMessage());
}

if (strpos($options['dsn'], 'sqlite::memory:') === FALSE) {
	Tester\Environment::lock($options['dsn'], TEMP_DIR);
}

$driverName = $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
$cacheMemoryStorage = new Nette\Caching\Storages\MemoryStorage;

$structure = new Nette\Database\Structure($connection, $cacheMemoryStorage);
$conventions = new Nette\Database\Conventions\DiscoveredConventions($structure);
$context = new Nette\Database\Context($connection, $structure, $conventions, $cacheMemoryStorage);


/** Replaces [] with driver-specific quotes */
function reformat($s)
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
	} elseif ($driverName === 'sqlsrv' || $driverName === 'sqlite' || $driverName === 'sqlite2') {
		return $s;
	} else {
		trigger_error("Unsupported driver $driverName", E_USER_WARNING);
	}
}
