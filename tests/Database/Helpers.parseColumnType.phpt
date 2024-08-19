<?php

/**
 * Test: Nette\Database\Row.
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Nette\Database\Helpers;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Test basic type
$result = Helpers::parseColumnType('UNSIGNED INT');
Assert::same(['type' => 'UNSIGNED INT', 'length' => null, 'scale' => null, 'parameters' => null], $result);

// Test type with length
$result = Helpers::parseColumnType('VARCHAR(255)');
Assert::same(['type' => 'VARCHAR', 'length' => 255, 'scale' => null, 'parameters' => null], $result);

// Test type with precision and scale
$result = Helpers::parseColumnType('DECIMAL(10,2)');
Assert::same(['type' => 'DECIMAL', 'length' => 10, 'scale' => 2, 'parameters' => null], $result);

// Test type with additional parameters
$result = Helpers::parseColumnType("ENUM('value1','value2')");
Assert::same(['type' => 'ENUM', 'length' => null, 'scale' => null, 'parameters' => "'value1','value2'"], $result);
