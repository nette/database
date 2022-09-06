Nette Database
==============

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/database.svg)](https://packagist.org/packages/nette/database)
[![Tests](https://github.com/nette/database/workflows/Tests/badge.svg?branch=master)](https://github.com/nette/database/actions)
[![Build Status Windows](https://ci.appveyor.com/api/projects/status/github/nette/database?branch=master&svg=true)](https://ci.appveyor.com/project/dg/database/branch/master)
[![Latest Stable Version](https://poser.pugx.org/nette/database/v/stable)](https://github.com/nette/database/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/nette/database/blob/master/license.md)


Introduction
------------

Nette provides a powerful layer for accessing your database easily.

- composes SQL queries with ease
- easily fetches data
- uses efficient queries and does not transmit unnecessary data

The [Nette Database Core](https://doc.nette.org/database-core) is a wrapper around the PDO and provides core functionality.

The [Nette Database Explorer](https://doc.nette.org/database-explorer) layer helps you to fetch database data more easily and in a more optimized way.


[Support Me](https://github.com/sponsors/dg)
--------------------------------------------

Do you like Nette Database? Are you looking forward to the new features?

[![Buy me a coffee](https://files.nette.org/icons/donation-3.svg)](https://github.com/sponsors/dg)

Thank you!


Installation
------------

The recommended way to install is via Composer:

```
composer require nette/database
```

It requires PHP version 7.2 and supports PHP up to 8.2.


Usage
-----

This is just a piece of documentation. [Please see our website](https://doc.nette.org/database).


Database Core
-------------

To create a new database connection just create a new instance of `Nette\Database\Connection` class:

```php
$database = new Nette\Database\Connection($dsn, $user, $password); // the same arguments as uses PDO
```

Connection allows you to easily query your database by calling `query` method:

```php
$database->query('INSERT INTO users', [ // an array can be a parameter
	'name' => 'Jim',
	'created' => new DateTime, // or a DateTime object
	'avatar' => fopen('image.gif', 'r'), // or a file
], ...); // it is even possible to use multiple inserts

$database->query('UPDATE users SET ? WHERE id=?', $data, $id);
$database->query('SELECT * FROM categories WHERE id=?', 123)->dump();
```

Database Explorer
-----------------

Nette Database Explorer layer helps you to fetch database data more easily and in a more optimized way. The primary attitude is to fetch data only from one table and fetch them at once. The data are fetched into `ActiveRow` instances. Data from other tables connected by relationships are delivered by another queries - this is maintained by Database Explorer layer itself.

Let's take a look at common use-case. You need to fetch books and their authors. It is common 1:N relationship. The often used implementation fetches data by one SQL query with table joins. The second possibility is to fetch data separately, run one query for getting books and then get an author for each book by another query (e.g. in your foreach cycle). This could be easily optimized to run only two queries, one for books, and another for the needed authors - and this is just the way how Nette Database Explorer does it.

Selecting data starts with the table, just call `$explorer->table()` on the `Nette\Database\Explorer` object. The easiest way to get it is [described here](https://doc.nette.org/database-core#toc-configuration), but if we use Nette Database Explorer alone, it can be [manually created](https://doc.nette.org/database-explorer#toc-manual-creating-nette-database-context).


```php
$selection = $explorer->table('book'); // db table name is "book"
```

We can simply iterate over the selection and pass through all the books. The rows are fetched as ActiveRow instances; you can read row data from their properties.

```php
$books = $explorer->table('book');
foreach ($books as $book) {
	echo $book->title;
	echo $book->author_id;
}
```

Getting just one specific row is done by `get()` method, which directly returns an ActiveRow instance.

```php
$book = $explorer->table('book')->get(2); // returns book with id 2
echo $book->title;
echo $book->author_id;
```

Working with relationships
--------------------------

```php
$books = $explorer->table('book');

foreach ($books as $book) {
	echo 'title:      ' . $book->title;
	echo 'written by: ' . $book->author->name;

	echo 'tags: ';
	foreach ($book->related('book_tag') as $bookTag) {
		echo $bookTag->tag->name . ', ';
	}
}
```

You will be pleased how efficiently the database layer works. The example above performs constant number of queries, see following 4 queries:

```sql
SELECT * FROM `book`
SELECT * FROM `author` WHERE (`author`.`id` IN (11, 12))
SELECT * FROM `book_tag` WHERE (`book_tag`.`book_id` IN (1, 4, 2, 3))
SELECT * FROM `tag` WHERE (`tag`.`id` IN (21, 22, 23))
```

If you use caching (defaults on), no columns will be queried unnecessarily. After the first query, cache will store the used column names and Nette Database Explorer will run queries only with the needed columns:

```sql
SELECT `id`, `title`, `author_id` FROM `book`
SELECT `id`, `name` FROM `author` WHERE (`author`.`id` IN (11, 12))
SELECT `book_id`, `tag_id` FROM `book_tag` WHERE (`book_tag`.`book_id` IN (1, 4, 2, 3))
SELECT `id`, `name` FROM `tag` WHERE (`tag`.`id` IN (21, 22, 23))
```

[Continue…](https://doc.nette.org/database-explorer).
