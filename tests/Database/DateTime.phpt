<?php

declare(strict_types=1);

use Nette\Database\DateTime;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


date_default_timezone_set('Europe/Prague');

// to string
Assert::same('1978-01-23 11:40:00.000000', (string) new DateTime('1978-01-23 11:40'));

// JSON
Assert::same('"1978-01-23T11:40:00+01:00"', json_encode(new DateTime('1978-01-23 11:40')));
