<?php

declare(strict_types=1);

use Nette\Database\DateTime;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


date_default_timezone_set('Europe/Prague');

// timestamp
Assert::same('1978-01-23 11:40:00', (string) new DateTime(254_400_000));
Assert::same(254_400_000, (new DateTime(254_400_000))->getTimestamp());

Assert::same(is_int(2_544_000_000) ? 2_544_000_000 : '2544000000', (new DateTime(2_544_000_000))->getTimestamp()); // 64 bit

// to string
Assert::same('1978-01-23 11:40:00', (string) new DateTime('1978-01-23 11:40'));

// JSON
Assert::same('"1978-01-23T11:40:00+01:00"', json_encode(new DateTime('1978-01-23 11:40')));
