<?php declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$connection = new Nette\Database\Connection('mysql:', 'root', 'xxx');
$connection->query('CREATE DATABASE IF NOT EXISTS nette_test');
