<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Connection ConnectionLostException on PostgreSQL.
 * @dataProvider? databases.ini  postgresql
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$victim = connectToDB()->getConnection();
$killer = connectToDB()->getConnection();


test('Exception thrown when connection is terminated server-side', function () use ($victim, $killer) {
	$victimPid = (int) $victim->fetchField('SELECT pg_backend_pid()');
	$killer->query('SELECT pg_terminate_backend(?)', $victimPid);

	$e = Assert::exception(
		fn() => $victim->query('SELECT 1'),
		Nette\Database\ConnectionLostException::class,
	);

	// PDO_pgsql sometimes reports HY000 instead of 08006 for abnormal termination.
	Assert::contains($e->getSqlState(), ['08003', '08006', 'HY000']);
	Assert::true($e instanceof Nette\Database\ConnectionException);
});
