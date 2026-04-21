<?php declare(strict_types=1);

/**
 * Test: Nette\Database\Connection::transaction() with retry on deadlock.
 */

use Nette\Database\Connection;
use Nette\Database\ConnectionLostException;
use Nette\Database\DeadlockException;
use Nette\Database\RetryableException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function makeDeadlock(): DeadlockException
{
	$pdo = new PDOException('Deadlock found when trying to get lock');
	$pdo->errorInfo = ['40001', 1213, 'Deadlock found'];
	return DeadlockException::from($pdo);
}


test('retries on DeadlockException and eventually succeeds', function () {
	$connection = new Connection('sqlite::memory:');
	$attempts = 0;

	$result = $connection->transaction(function () use (&$attempts) {
		$attempts++;
		if ($attempts < 3) {
			throw makeDeadlock();
		}
		return 'success';
	}, attempts: 5);

	Assert::same('success', $result);
	Assert::same(3, $attempts);
});


test('gives up after exhausting attempts and rethrows last deadlock', function () {
	$connection = new Connection('sqlite::memory:');
	$attempts = 0;

	Assert::exception(
		function () use ($connection, &$attempts) {
			$connection->transaction(function () use (&$attempts) {
				$attempts++;
				throw makeDeadlock();
			}, attempts: 3);
		},
		DeadlockException::class,
	);

	Assert::same(3, $attempts);
});


test('does not retry ConnectionLostException, the commit outcome is unknown', function () {
	$connection = new Connection('sqlite::memory:');
	$attempts = 0;

	Assert::exception(
		function () use ($connection, &$attempts) {
			$connection->transaction(function () use (&$attempts) {
				$attempts++;
				$pdo = new PDOException('MySQL server has gone away');
				$pdo->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
				throw ConnectionLostException::from($pdo);
			}, attempts: 5);
		},
		ConnectionLostException::class,
	);

	Assert::same(1, $attempts);
});


test('does not retry on non-deadlock exceptions', function () {
	$connection = new Connection('sqlite::memory:');
	$attempts = 0;

	Assert::exception(
		function () use ($connection, &$attempts) {
			$connection->transaction(function () use (&$attempts) {
				$attempts++;
				throw new Exception('something else');
			}, attempts: 5);
		},
		Throwable::class,
		'something else',
	);

	Assert::same(1, $attempts);
});


test('inner nested transaction does not retry on its own', function () {
	$connection = new Connection('sqlite::memory:');
	$outerAttempts = 0;
	$innerAttempts = 0;

	// outer attempts=1 → inner deadlock bubbles up, outer rethrows without retry
	// verifies that inner nested transaction does NOT retry by itself
	Assert::exception(
		function () use ($connection, &$outerAttempts, &$innerAttempts) {
			$connection->transaction(function (Connection $connection) use (&$outerAttempts, &$innerAttempts) {
				$outerAttempts++;
				$connection->transaction(function () use (&$innerAttempts) {
					$innerAttempts++;
					throw makeDeadlock();
				}, attempts: 5);
			});
		},
		DeadlockException::class,
	);

	Assert::same(1, $outerAttempts);
	Assert::same(1, $innerAttempts);
});


test('outer transaction retries even when inner transaction throws deadlock', function () {
	$connection = new Connection('sqlite::memory:');
	$outerAttempts = 0;

	$result = $connection->transaction(function (Connection $connection) use (&$outerAttempts) {
		$outerAttempts++;
		if ($outerAttempts < 3) {
			$connection->transaction(function () {
				throw makeDeadlock();
			});
		}
		return 'ok';
	}, attempts: 5);

	Assert::same('ok', $result);
	Assert::same(3, $outerAttempts);
});


test('default attempts = 1 does not retry', function () {
	$connection = new Connection('sqlite::memory:');
	$attempts = 0;

	Assert::exception(
		function () use ($connection, &$attempts) {
			$connection->transaction(function () use (&$attempts) {
				$attempts++;
				throw makeDeadlock();
			});
		},
		DeadlockException::class,
	);

	Assert::same(1, $attempts);
});


test('attempts < 1 throws InvalidArgumentException', function () {
	$connection = new Connection('sqlite::memory:');

	Assert::exception(
		fn() => $connection->transaction(fn() => null, attempts: 0),
		Nette\InvalidArgumentException::class,
		'Number of attempts must be at least 1.',
	);
});


test('retries any user-defined RetryableException', function () {
	$userException = new class ('optimistic lock conflict') extends \RuntimeException implements RetryableException {
	};

	$connection = new Connection('sqlite::memory:');
	$attempts = 0;

	$result = $connection->transaction(function () use (&$attempts, $userException) {
		$attempts++;
		if ($attempts < 2) {
			throw $userException;
		}
		return 'ok';
	}, attempts: 3);

	Assert::same('ok', $result);
	Assert::same(2, $attempts);
});


test('onRetry hook fires before each retry with attempt number and exception', function () {
	$connection = new Connection('sqlite::memory:');
	$hookCalls = [];
	$connection->onRetry[] = function (Connection $conn, int $attempt, RetryableException $e) use (&$hookCalls) {
		$hookCalls[] = [$attempt, $e::class];
	};

	$attempts = 0;
	$connection->transaction(function () use (&$attempts) {
		$attempts++;
		if ($attempts < 3) {
			throw makeDeadlock();
		}
		return 'ok';
	}, attempts: 5);

	Assert::same([[1, DeadlockException::class], [2, DeadlockException::class]], $hookCalls);
});


test('onRetry hook does not fire when retry is exhausted', function () {
	$connection = new Connection('sqlite::memory:');
	$hookCalls = 0;
	$connection->onRetry[] = function () use (&$hookCalls) {
		$hookCalls++;
	};

	Assert::exception(
		fn() => $connection->transaction(fn() => throw makeDeadlock(), attempts: 3),
		DeadlockException::class,
	);

	// fires before attempts 2 and 3; not before the final failed throw
	Assert::same(2, $hookCalls);
});


test('retries when commit() throws RetryableException', function () {
	$connection = new class ('sqlite::memory:') extends Connection {
		public int $commitFailuresLeft = 2;


		public function commit(): void
		{
			if ($this->commitFailuresLeft-- > 0) {
				try {
					parent::rollBack();
				} catch (\Throwable) {
				}
				throw makeDeadlock();
			}
			parent::commit();
		}
	};

	$attempts = 0;
	$result = $connection->transaction(function () use (&$attempts) {
		$attempts++;
		return 'ok';
	}, attempts: 5);

	Assert::same('ok', $result);
	Assert::same(3, $attempts);
});


test('retries when beginTransaction() throws RetryableException', function () {
	$connection = new class ('sqlite::memory:') extends Connection {
		public int $beginFailuresLeft = 2;


		public function beginTransaction(): void
		{
			if ($this->beginFailuresLeft-- > 0) {
				throw makeDeadlock();
			}
			parent::beginTransaction();
		}
	};

	$attempts = 0;
	$result = $connection->transaction(function () use (&$attempts) {
		$attempts++;
		return 'ok';
	}, attempts: 5);

	Assert::same('ok', $result);
	Assert::same(1, $attempts);
});


test('failure inside rollBack() does not mask the original exception', function () {
	$connection = new class ('sqlite::memory:') extends Connection {
		public function rollBack(): void
		{
			throw new RuntimeException('rollback failed');
		}
	};

	$original = makeDeadlock();
	Assert::exception(
		fn() => $connection->transaction(fn() => throw $original),
		DeadlockException::class,
	);
});
