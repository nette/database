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
Assert::same(['type' => 'UNSIGNED INT', 'size' => null, 'scale' => null, 'parameters' => null], $result);

// Test type with length
$result = Helpers::parseColumnType('VARCHAR(255)');
Assert::same(['type' => 'VARCHAR', 'size' => 255, 'scale' => null, 'parameters' => null], $result);

// Test type with precision and scale
$result = Helpers::parseColumnType('DECIMAL(10,2)');
Assert::same(['type' => 'DECIMAL', 'size' => 10, 'scale' => 2, 'parameters' => null], $result);

// Test type with additional parameters
$result = Helpers::parseColumnType("ENUM('value1','value2')");
Assert::same(['type' => 'ENUM', 'size' => null, 'scale' => null, 'parameters' => "'value1','value2'"], $result);

// Test omitted type
$result = Helpers::parseColumnType('');
Assert::same(['type' => null, 'size' => null, 'scale' => null, 'parameters' => null], $result);
