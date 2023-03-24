<?php

/**
 * Test: Nette\Database\Table: Related() with StaticConvention.
 * @dataProvider? ../databases.ini
 */

declare(strict_types=1);

use Nette\Database\Conventions\StaticConventions;
use Tester\Assert;

require __DIR__ . '/../connect.inc.php'; // create $connection

$conventions = new StaticConventions();
$explorer = new Nette\Database\Explorer($connection, $structure, $conventions, $cacheMemoryStorage);

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/../files/{$driverName}-nette_test1.sql");

test('', function () use ($explorer) {
    $tags = [];
    foreach ($explorer->table('book')->order('id') as $book) {
        foreach ($book->related('book_tag')->order('tag_id')->select("tag.*") as $tag) {
            $tags[$book->id][] = $tag->name;
        }
    }

    Assert::same([
        1 => ['PHP', 'MySQL'],
        2 => ['JavaScript'],
        3 => ['PHP'],
        4 => ['PHP', 'MySQL'],
    ], $tags);
});
