<?php

/**
 * Test: Nette\Database\Connection correct usage of Nette\Database\CredentialProvider.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Caching\Storages\DevNullStorage;
use Nette\Database\Structure;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('password obtained from provider', function () {
	Assert::exception(
		fn() => new Nette\Database\Connection('mysql://127.0.0.1', 'user', new class implements Nette\Database\CredentialProvider {
			public function getPassword(): string {
				throw new RuntimeException('Tried to obtain password');
			}
		}),
		RuntimeException::class,
		'Tried to obtain password',
	);
});

test('password obtained from provider at connect time for lazy connection', function () {
	$connection = new Nette\Database\Connection('mysql://127.0.0.1', 'user', new class implements Nette\Database\CredentialProvider {
							public function getPassword(): string {
								throw new RuntimeException('Tried to obtain password');
							}
				}, ['lazy' => true]);
	$explorer = new Nette\Database\Explorer($connection, new Structure($connection, new DevNullStorage));
	Assert::exception(
		fn() => $explorer->query('SELECT ?', 10),
		RuntimeException::class,
		'Tried to obtain password',
	);
});

test('password obtained from provider on each reconnect', function () {
	$connection = new Nette\Database\Connection('mysql://127.0.0.1', 'user', new class implements Nette\Database\CredentialProvider {
							private int $counter = 0;


							public function getPassword(): string {
								if ($this->counter++ === 0) {
									return 'password';
								} else {
									throw new RuntimeException('Provider called second time');
								}
							}
				}, ['lazy' => true]);

	$explorer = new Nette\Database\Explorer($connection, new Structure($connection, new DevNullStorage));
	Assert::exception(
		fn() => $explorer->query('SELECT ?', 10),
		Nette\Database\DriverRuntimeException::class,
		'%a%',
	);

	$connection->disconnect();

	Assert::exception(
		fn() => $explorer->query('SELECT ?', 10),
		RuntimeException::class,
		'Provider called second time',
	);
});
