<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Connection ConnectionLostException on MySQL.
 * @dataProvider? databases.ini  mysql
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$victim = connectToDB()->getConnection();
$killer = connectToDB()->getConnection();


test('Exception thrown when connection is killed server-side', function () use ($victim, $killer) {
	$victimId = (int) $victim->fetchField('SELECT CONNECTION_ID()');
	$killer->query('KILL ?', $victimId);

	$e = Assert::exception(
		fn() => $victim->query('SELECT 1'),
		Nette\Database\ConnectionLostException::class,
	);

	Assert::contains($e->getDriverCode(), [2006, 2013]);
	Assert::true($e instanceof Nette\Database\ConnectionException);
});
