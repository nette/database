<?php

/**
 * @phpVersion 8.1
 * @dataProvider? databases.ini
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$connection = connectToDB()->getConnection();

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

Assert::exception(
	fn() => $preprocessor->process(['SELECT ?', PureEnum::One]),
	Nette\InvalidArgumentException::class,
	'Unexpected type of parameter: PureEnum',
);
