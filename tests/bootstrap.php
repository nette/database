<?php

declare(strict_types=1);

// The Nette Tester command-line runner can be
// invoked through the command: ../vendor/bin/tester .

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer install`';
	exit(1);
}


// configure environment
Tester\Environment::setup();
Tester\Environment::setupFunctions();
date_default_timezone_set('Europe/Prague');


function getTempDir(): string
{
	$dir = __DIR__ . '/tmp';
	@mkdir($dir);
	return $dir;
}


function connectToDB(): Nette\Database\Explorer
{
	$args = Tester\Environment::loadData() + ['username' => null, 'password' => null, 'options' => []];

	if ($args['dsn'] !== 'sqlite::memory:') {
		Tester\Environment::lock($args['dsn'], getTempDir());
	}

	try {
		$connection = new Nette\Database\Connection($args['dsn'], $args['username'], $args['password'], $args['options']);
	} catch (PDOException $e) {
		Tester\Environment::skip("Connection to '$args[dsn]' failed. Reason: " . $e->getMessage());
	}

	$driverName = $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
	$cacheMemoryStorage = new Nette\Caching\Storages\MemoryStorage;

	$structure = new Nette\Database\Structure($connection, $cacheMemoryStorage);
	$conventions = new Nette\Database\Conventions\DiscoveredConventions($structure);
	$explorer = new Nette\Database\Explorer($connection, $structure, $conventions, $cacheMemoryStorage);

	echo "Driver: $driverName\n";
	$GLOBALS['driverName'] = $driverName;
	return $explorer;
}


/** Replaces [] with driver-specific quotes */
function reformat($s): string
{
	$driverName = $GLOBALS['driverName'];
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
