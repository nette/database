<?php declare(strict_types=1);

/**
 * Test: Nette\Database\SqlPreprocessor
 * @dataProvider? databases.ini
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::true(true);

$explorer = connectToDB();
$preprocessor = new Nette\Database\SqlPreprocessor($explorer->getConnection());

[$sql, $params] = $preprocessor->process(['SELECT id FROM author WHERE', [
	'b',
]]);
//dump($sql);
//dump($params);


$_POST = ['0) UNION SELECT name, salary FROM users WHERE (0'];

try {
	$explorer->table('Operator1')
		->where($_POST)
		->fetch();
} catch (Throwable $e) {
	echo $e->getMessage(), "\n\n";
}

echo $explorer->getConnection()->getLastQueryString();
