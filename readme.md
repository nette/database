Nette Database
==============

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/database.svg)](https://packagist.org/packages/nette/database)
[![Build Status](https://travis-ci.org/nette/database.svg?branch=v2.3)](https://travis-ci.org/nette/database)
[![Build Status Windows](https://ci.appveyor.com/api/projects/status/github/nette/database?branch=v2.3&svg=true)](https://ci.appveyor.com/project/dg/database/branch/v2.3)

Nette provides a powerful layer for accessing your database easily.

- composes SQL queries with ease
- easily fetches data
- uses efficient queries and does not transmit unnecessary data

The `Nette\Database\Connection` class is a wrapper around the PDO and represents a connection to the database.
The core functionality is provided by `Nette\Database\Context`. `Nette\Database\Table` layer orivudes an enhanced layer for table querying.

To create a new database connection just create a new instance of [api:Nette\Database\Connection] class:

```php
$connection = new Nette\Database\Connection($dsn, $user, $password);
```

All connections are created as "lazy" by default. This means the connection is established when it's needed, not when you create a `Connection` instance. You can disable this behavior by passing `'lazy' => FALSE` configuration.

Queries
--------

The core functionality is provided by `Nette\Database\Context`. Database\Context allows you to easily query your database by calling `query` method:

```php
$database = new Nette\Database\Context($connection);

$database->query('INSERT INTO users', array( // an array can be a parameter
	'name' => 'Jim',
	'created' => new DateTime, // or a DateTime object
	'avatar' => fopen('image.gif', 'r'), // or a file
), ...); // it is even possible to use multiple inserts

$database->query('UPDATE users SET ? WHERE id=?', $data, $id);
$database->query('SELECT * FROM categories WHERE id=?', 123)->dump();
```

Table Selection
---------------

`Nette\Database\Table` layer helps you to fetch database data more easily and in a more optimized way. **The primary attitude is to fetch data only from one table and fetch them at once.** The data are fetched into [ActiveRow | database-activerow] instances. Data from other tables connected by relationships are delivered by another queries - this is maintained by Database\Table layer itself.

Let's take a look at common use-case. You need to fetch books and their authors. It is common 1:N relationship. The often used implementation fetches data by one SQL query with table joins. The second possibility is to fetch data separately, run one query for getting books and then get an author for each book by another query (e.g. in your foreach cycle). This could be easily optimized to run only two queries, one for books, and another for the needed authors - and this is just the way how Nette\Database\Table does it.

Creating Selection is quite easy, just call `table()` method on your database context.

```php
$selection = $context->table('book'); // db table name is "book"
```

Selection implements traversable interface: you can just iterate over the instance to get all books. The rows are fetched as ActiveRow instances; you can read row data from their properties.

```php
$books = $context->table('book');
foreach ($books as $book) {
	echo $book->title;
	echo $book->author_id;
}
```

Getting just one specific row is done by `get()` method. It is "filtering" method, which directly returns an ActiveRow instance.

```php
$book = $context->table('book')->get(2); // returns book with id 2
echo $book->title;
echo $book->author_id;
```

Working with relationships
--------------------------

As we mentioned in the chapter intro, Database\Table layer maintains the table relations for you. There are two possibilities how and where you can work with relationships.

1. **Filtering rows fetched by Selection.** In the introduction we stated the basic principle to select data only from one database table at once. However, Selection instance can do a table join to filter selected row. For example you need select only that authors who has written more than 2 books.
2. **Getting related data for fetched ActiveRows.** We denied getting data from more than one table at once. Sadly, printing `author_id` is not good enough. We need to get full author database row, ideally fetched as ActiveRow. Getting this type of relationships is maintained by ActiveRow.


In provided examples we will work with this database schema below. There are common OneHasMany and ManyHasMany relationships. OneHasMany relationship is doubled, a book must have an author and could have a translator (`translator_id` could be a `NULL`).

![](https://files.nette.org/git/doc-2.1/db-schema-1-.png)

In example below we are getting related data for fetched books. In author property (of book ActiveRow instances) is available another ActiveRow instance, which represents author of the book. Getting book_tag instances is done by `related()` method, which returns collection of this instances. In the cycle we get the tag name from another ActiveRow instance available in book_tag instance.

```php
$books = $context->table('book');

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

If you use cache (defaults on), no columns will be queried unnecessarily. After the first query, cache will store the used column names and Nette\Database will run queries only with the needed columns:

```sql
SELECT `id`, `title`, `author_id` FROM `book`
SELECT `id`, `name` FROM `author` WHERE (`author`.`id` IN (11, 12))
SELECT `book_id`, `tag_id` FROM `book_tag` WHERE (`book_tag`.`book_id` IN (1, 4, 2, 3))
SELECT `id`, `name` FROM `tag` WHERE (`tag`.`id` IN (21, 22, 23))
```
