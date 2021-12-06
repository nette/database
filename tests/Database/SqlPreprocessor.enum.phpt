<?php

/**
 * @phpVersion 8.1
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/connect.inc.php'; // create $connection


enum EnumInt: int
{
	case One = 1;
}

enum EnumString: string
{
	case One = 'one';
}

enum PureEnum
{
	case One;
}


$preprocessor = new Nette\Database\SqlPreprocessor($connection);

[$sql, $params] = $preprocessor->process(['SELECT ?', EnumInt::One]);
Assert::same('SELECT ?', $sql);
Assert::same([1], $params);

[$sql, $params] = $preprocessor->process(['SELECT ?', EnumString::One]);
Assert::same('SELECT ?', $sql);
Assert::same(['one'], $params);

Assert::exception(function () use ($preprocessor) {
	$preprocessor->process(['SELECT ?', PureEnum::One]);
}, Nette\InvalidArgumentException::class, 'Unexpected type of parameter: PureEnum');
