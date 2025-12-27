# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Nette Database** is a database abstraction layer for PHP providing two components:
1. **Database Core** - PDO wrapper with advanced query building and parameter substitution
2. **Database Explorer** - ActiveRow pattern implementation (inspired by NotORM) for efficient relationship handling

Supports PHP 8.1-8.5 and multiple database engines (MySQL, PostgreSQL, SQLite, MS SQL Server, Oracle).

## Essential Commands

### Testing
```bash
# Run all tests
composer run tester

# Run specific test directory
vendor/bin/tester tests/Database/Explorer -s -C

# Run single test file (use php directly, not through tester)
php tests/Database/Explorer/Explorer.basic.phpt

# Run tests with database fixtures
vendor/bin/tester tests/Database -c tests/php.ini -s -C
```

**Test framework:** Nette Tester with `.phpt` extension
- Tests use `@dataProvider` to run against multiple databases (databases.ini)
- Each test uses `test()` function with descriptive names
- No comments before `test()` calls - the description parameter serves this purpose

### Static Analysis
```bash
# Run PHPStan
composer run phpstan
```

Configuration: PHPStan level 5 with phpstan-nette extension

## Architecture Overview

### Two-Layer Design

**Core Layer** (`src/Database/`)
- `Connection` - PDO connection management, query execution, transactions
- `SqlPreprocessor` - Advanced parameter substitution with multiple modes (ModeAnd, ModeSet, ModeValues, ModeOrder, ModeList)
- `ResultSet` - Iterator-based result sets
- `Driver` interface + database-specific implementations
- `Structure` - Cached database metadata (tables, columns, foreign keys, indexes)
- `Reflection` - On-demand schema reflection with lazy loading

**Explorer Layer** (`src/Database/Table/`)
- `Explorer` - Entry point for high-level database operations
- `Selection` - Fluent query builder with lazy loading and generic type support (`Selection<T>`)
- `ActiveRow` - Represents database rows with relationship navigation
- `GroupedSelection` - Handles grouped/aggregated queries
- `SqlBuilder` - Constructs SQL from Selection API calls

### Key Design Patterns

**ActiveRecord with Automatic N+1 Prevention**
```php
// Automatically batches related queries - constant query count regardless of result size
foreach ($books as $book) {
    echo $book->author->name;  // First iteration: SELECT * FROM author WHERE id IN (...)
}
```

**Convention-based Relationships**
- `DiscoveredConventions` - Auto-discovers from database structure
- `StaticConventions` - Manual configuration
- Forward references: `$book->author` (via foreign key)
- Back-references: `$author->related('book')` (reverse relationship)

**Relationship Navigation with Colon Notation**
```php
->where('category.slug', $slug)           // Forward reference via foreign key
->where(':order_item.quantity >', 1)      // Back-reference (reverse relationship)
->where('category.parent.name', 'Root')   // Deep relationship traversal
```

**Smart Query Optimization**
- Accessed column caching - only SELECTs columns actually used after first query
- Automatic JOIN batching for related data
- Two-level caching: Structure cache (metadata) + accessed columns cache

### Driver Architecture

Each driver implements `Driver` interface with database-specific features:
- SQL formatting (delimiters, datetime, LIKE encoding)
- LIMIT/OFFSET application (varies by database)
- Schema reflection
- Exception mapping (PDOException → specific constraint violations)
- Feature detection (sequences, schemas, ungrouped columns support)

**Exception Hierarchy:**
```
DriverException
├── ConnectionException
└── ConstraintViolationException
    ├── ForeignKeyConstraintViolationException
    ├── NotNullConstraintViolationException
    └── UniqueConstraintViolationException
```

## Coding Standards

### PHP Standards
- Every file must include `declare(strict_types=1)`
- All parameters, properties, and return values must have types
- Use generic type annotations for IDE support: `@template T of ActiveRow`
- Use single quotes for strings unless containing apostrophes
- Follow Nette Coding Standard (PSR-12 based)

### Documentation (phpDoc)
- Document class purpose concisely without "Class that..." phrases
- Only document parameters/returns when adding info beyond PHP types
- For arrays, specify contents: `@return string[]` or `@param ActiveRow[] $rows`
- Use single-line format for simple properties: `/** @var string[] */`
- Start method docs with 3rd person singular present tense verb (Returns, Formats, Checks, Creates)

### Testing Patterns
```php
test('descriptive name of what is being tested', function () use ($explorer) {
    $result = $explorer->table('book')->where('id', 1)->fetch();
    Assert::same('expected', $result->title);
});

testException('throws exception for invalid input', function () use ($explorer) {
    $explorer->table('book')->get(999);
}, Nette\InvalidArgumentException::class, 'Expected message pattern');
```

## Important Implementation Details

### Generic Type Support
Always use generic annotations for Selection returns:
```php
/** @return Selection<ProductRow> */
public function getProducts(): Selection
{
    return $this->db->table('product');
}
```

### Query Preprocessing Modes
Context-aware array expansion in SqlPreprocessor:
- `ModeAnd` - WHERE/HAVING conditions: `['name' => 'John', 'age >' => 18]` → `name = 'John' AND age > 18`
- `ModeSet` - UPDATE SET clauses: `['name' => 'John', 'age' => 30]` → `name = 'John', age = 30`
- `ModeValues` - INSERT/REPLACE statements
- `ModeOrder` - ORDER BY/GROUP BY clauses
- `ModeList` - IN clauses: `[1, 2, 3]` → `IN (1, 2, 3)`

### Exception Handling
Catch specific constraint violations, not generic PDOException:
```php
try {
    $row->update(['email' => $newEmail]);
} catch (Nette\Database\UniqueConstraintViolationException $e) {
    // Handle unique constraint violation
}
```

### Relationship Naming Convention
Follow foreign key naming without `_id` suffix:
- Database column: `author_id`
- Relationship property: `$book->author` (not `$book->author_id`)

### When to Use Selection API vs Raw SQL
**Use Selection API for:**
- Standard CRUD operations
- Simple filtering and sorting
- Queries benefiting from lazy loading
- Dynamic condition chaining

**Use raw SQL for:**
- Complex analytics and reporting
- Recursive queries (WITH RECURSIVE)
- Performance-critical queries
- Complex joins awkward in fluent API

### Caching Behavior
- First query fetches all columns: `SELECT * FROM book`
- Subsequent queries use accessed column cache: `SELECT id, title, author_id FROM book`
- Cache persists across requests via Nette\Caching\Storage
- Test environment uses MemoryStorage for isolation

## Test Organization

```
tests/Database/
├── Connection.*.phpt          # Core connection functionality
├── ResultSet.*.phpt           # Result fetching methods
├── SqlPreprocessor.phpt       # Query preprocessing
├── Structure.phpt             # Metadata caching
├── Explorer/                  # Explorer layer tests
│   ├── Explorer.*.phpt        # Main Explorer functionality
│   ├── Selection.*.phpt       # Selection API methods
│   ├── bugs/                  # Regression tests
│   └── SqlBuilder.*.phpt      # Query building
├── Drivers/                   # Database-specific driver tests
├── Conventions/               # Relationship convention tests
└── files/                     # SQL fixtures for test databases
```

**Database fixtures:** Tests use `@dataProvider databases.ini` to run against multiple database engines. Fixtures in `files/` directory named by pattern: `{driver}-nette_test1.sql`

## Nette DI Integration

DatabaseExtension (`src/Bridges/DatabaseDI/DatabaseExtension.php`) provides:
- Nette DI container integration
- Multi-database configuration support
- Auto-wiring for Connection, Structure, and Explorer services
- Tracy debugger integration via ConnectionPanel

## SQL Placeholders and Hints

The SQL preprocessor uses special placeholders for different contexts:

| Placeholder | Purpose | Auto-detected for |
|-------------|---------|-------------------|
| `?` | Standard parameter placeholder | - |
| `?name` | Table/column identifier (properly quoted) | - |
| `?values` | INSERT format: `(col1, col2) VALUES (?, ?)` | `INSERT ... ?`, `REPLACE ... ?` |
| `?set` | UPDATE format: `col1 = ?, col2 = ?` | `SET ?`, `ON DUPLICATE KEY UPDATE ?` |
| `?and` | WHERE format with AND: `col1 = ? AND col2 = ?` | `WHERE ?`, `HAVING ?` |
| `?or` | WHERE format with OR: `col1 = ? OR col2 = ?` | - |
| `?order` | ORDER BY format | `ORDER BY ?`, `GROUP BY ?` |

**Examples:**
```php
// Dynamic identifiers (use only with trusted values!)
$database->query('SELECT ?name FROM ?name', $column, $table);
// SELECT `name` FROM `users` (MySQL)

// OR conditions (auto-detection uses AND by default)
$database->query('SELECT * FROM users WHERE ?or', [
    'name' => 'John',
    'email' => 'john@example.com',
]);
// WHERE `name` = 'John' OR `email` = 'john@example.com'

// Context-aware array processing
$database->query('INSERT INTO users ?', ['name' => 'John', 'year' => 1994]);
// INSERT INTO users (`name`, `year`) VALUES ('John', 1994)

$database->query('UPDATE users SET ? WHERE id = ?', ['name' => 'John'], 1);
// UPDATE users SET `name` = 'John' WHERE id = 1

$database->query('SELECT * FROM users WHERE', ['active' => true, 'role' => 'admin']);
// WHERE `active` = 1 AND `role` = 'admin'
```

**Special value types:**
```php
$database->query('INSERT INTO articles', [
    'title' => 'My Article',
    'published_at' => new DateTime,              // Converted to database format
    'content' => fopen('image.png', 'r'),        // Binary file content
    'state' => Status::Draft,                    // Enum converted to value
    'updated_at' => $database::literal('NOW()'), // SQL literal - not escaped
]);
```

## Security Considerations

### Mass Assignment Vulnerability

**Never use unvalidated user input directly in arrays for INSERT/UPDATE/WHERE:**
```php
// ❌ DANGEROUS - attacker can set any column
$database->query('INSERT INTO users', $_POST);
$table->insert($_POST);
$table->where($_POST);

// ❌ DANGEROUS - attacker can use operators and SQL injection
$_POST['salary >'] = 100000;
$table->where($_POST); // WHERE `salary` > 100000

$_POST['0) UNION SELECT password FROM users WHERE (1'] = true;
$table->where($_POST); // SQL injection via array keys
```

**Always use column whitelist:**
```php
// ✅ Safe - only allowed columns
$allowedColumns = ['name', 'email', 'active'];
$filteredData = array_intersect_key($userData, array_flip($allowedColumns));

$database->query('INSERT INTO users', $filteredData);
$table->update($filteredData);
$table->where($filteredData);
```

### SQL Injection Prevention

**Use parameterized queries, never string concatenation:**
```php
// ❌ DANGEROUS
$table->where('name = ' . $_GET['name']);
$table->where("name = '$_GET[name]'");
$database->query("SELECT * FROM users WHERE name = '$name'");

// ✅ Safe
$table->where('name = ?', $name);
$database->query('SELECT * FROM users WHERE name = ?', $name);
```

**For dynamic identifiers, use whitelist + ?name:**
```php
// ✅ Safe - validate against whitelist first
$allowedColumns = ['name', 'email', 'created_at'];
if (!in_array($column, $allowedColumns)) {
    throw new InvalidArgumentException('Invalid column');
}
$database->query('SELECT ?name FROM users', $column);
```

## Configuration (NEON)

```neon
database:
    # Single connection
    dsn: 'mysql:host=127.0.0.1;dbname=test'
    user: root
    password: password

    # Options
    debugger: true              # Tracy panel
    explain: true               # Show EXPLAIN in Tracy
    autowired: true             # Enable autowiring
    conventions: discovered     # discovered|static|ClassName

    options:
        lazy: false             # Connect only when needed
        charset: utf8mb4        # MySQL: SET NAMES
        sqlmode: ''             # MySQL: SET sql_mode
        convertBoolean: false   # MySQL: TINYINT(1) → bool
        newDateTime: false      # Return DateTimeImmutable instead of DateTime
        formatDateTime: 'U'     # SQLite/Oracle: DateTime format (default: Unix timestamp)

# Multiple connections
database:
    main:
        dsn: 'mysql:host=127.0.0.1;dbname=test'
        user: root
        password: password
        autowired: true         # Only first connection is autowired by default

    another:
        dsn: 'sqlite::memory:'
        autowired: false

# DI service names
services:
    # Explicitly reference non-autowired connections
    - UserFacade(@database.another.connection)
```

**Service names:** `database.{name}.connection` and `database.{name}.explorer`

## Explorer API Methods

### Filtering and Conditions

**where() - Automatic operator detection:**
```php
$table->where('id', 1);              // WHERE `id` = 1
$table->where('id', null);           // WHERE `id` IS NULL
$table->where('id', [1, 2, 3]);      // WHERE `id` IN (1, 2, 3)
$table->where('id NOT', [1, 2, 3]);  // WHERE `id` NOT IN (1, 2, 3)
$table->where('id', []);             // WHERE `id` IS NULL AND FALSE (finds nothing)

// Array syntax with explicit operators
$table->where([
    'age >' => 18,
    'name LIKE' => 'John%',
    'status' => ['active', 'pending'],
]);

// Subqueries
$table->where('id', $explorer->table('other')->select('foreign_id'));
```

**whereOr() - OR conditions:**
```php
$table->whereOr([
    'status' => 'active',
    'deleted' => false,
]);
// WHERE (`status` = 'active') OR (`deleted` = 0)
```

**wherePrimary() - Primary key filtering:**
```php
$table->wherePrimary(123);           // WHERE `id` = 123
$table->wherePrimary([1, 2, 3]);     // WHERE `id` IN (1, 2, 3)

// Composite primary key
$table->wherePrimary(['foo_id' => 1, 'bar_id' => 5]);
// WHERE `foo_id` = 1 AND `bar_id` = 5
```

### Relationships

**Accessing parent (1:N from child perspective):**
```php
$book = $explorer->table('book')->get(1);
echo $book->author->name;           // Via author_id column
echo $book->translator?->name;      // Via translator_id column (nullable)

// Alternative: ref() method
$book->ref('author', 'author_id')->name;
$book->ref('author', 'translator_id')->name;
```

**Accessing children (1:N from parent perspective):**
```php
$author = $explorer->table('author')->get(1);

// Explicit column specification
foreach ($author->related('book.author_id') as $book) {
    echo $book->title;
}

// Auto-detection (uses book.author_id based on parent table name)
foreach ($author->related('book') as $book) {
    echo $book->title;
}

// Translated books
foreach ($author->related('book.translator_id') as $book) {
    echo $book->title;
}
```

**Many-to-many relationships:**
```php
$book = $explorer->table('book')->get(1);
// Go through junction table first
foreach ($book->related('book_tag') as $bookTag) {
    echo $bookTag->tag->name;  // Then access final table
}
```

**Querying through relationships (automatic JOINs):**
```php
// Dot notation (forward: 1:N from child)
$books->where('author.name LIKE ?', 'Jon%');
$books->order('author.name DESC');
$books->select('book.title, author.name');

// Colon notation (backward: 1:N from parent)
$authors->where(':book.title LIKE ?', '%PHP%');
$authors->select('*, COUNT(:book.id) AS book_count')->group('author.id');

// Specify joining column explicitly
$authors->where(':book(translator_id).title LIKE ?', '%PHP%');

// Chain relationships
$authors->where(':book:book_tag.tag.name', 'PHP');
```

**joinWhere() - Extend JOIN conditions:**
```php
// Adds condition to JOIN ON clause (not WHERE clause)
$books = $explorer->table('book')
    ->joinWhere('translator', 'translator.name', 'David');
// LEFT JOIN author translator ON book.translator_id = translator.id
//   AND (translator.name = 'David')

// With alias for complex queries
$tags = $explorer->table('tag')
    ->joinWhere(':book_tag.book.author', 'book_author.born < ?', 1950)
    ->alias(':book_tag.book.author', 'book_author');
```

### CRUD Operations

**insert() - Returns ActiveRow for single insert, int for multi-insert:**
```php
// Single insert
$row = $explorer->table('users')->insert([
    'name' => 'John',
    'email' => 'john@example.com',
]);
echo $row->id; // Auto-generated ID

// Multi-insert
$count = $explorer->table('users')->insert([
    ['name' => 'John', 'year' => 1994],
    ['name' => 'Jack', 'year' => 1995],
]);
// Returns number of inserted rows

// Insert from selection
$newUsers = $explorer->table('potential_users')
    ->where('approved', 1)
    ->select('name, email');
$explorer->table('users')->insert($newUsers);
```

**update() - Returns number of affected rows:**
```php
$affected = $explorer->table('users')
    ->where('id', 10)
    ->update([
        'name' => 'John Smith',
        'points+=' => 1,  // Increment
        'coins-=' => 1,   // Decrement
    ]);

// ActiveRow::update() - updates single row, returns true if changed
$article = $explorer->table('article')->get(1);
$changed = $article->update(['views+=' => 1]);
```

**delete() - Returns number of deleted rows:**
```php
$count = $explorer->table('users')
    ->where('id', 10)
    ->delete();

// ActiveRow::delete()
$book = $explorer->table('book')->get(1);
$book->delete();
```

### Aggregation

```php
$table->count('*');                      // SELECT COUNT(*) FROM table
$table->count('DISTINCT column');        // SELECT COUNT(DISTINCT column)
$table->min('price');                    // SELECT MIN(price)
$table->max('price');                    // SELECT MAX(price)
$table->sum('price * quantity');         // SELECT SUM(price * quantity)

// Generic aggregation
$avgPrice = $products->where('category_id', 1)
    ->aggregation('AVG(price)');

// Aggregation over grouped results
$totalPrice = $products
    ->select('category_id, SUM(price * stock) AS category_total')
    ->group('category_id')
    ->aggregation('SUM(category_total)', 'SUM');
```

## Transactions

**Automatic transaction management:**
```php
// Preferred: automatic commit/rollback
$result = $database->transaction(function ($database) use ($id) {
    $database->query('DELETE FROM articles WHERE id = ?', $id);
    $database->query('INSERT INTO audit_log', [
        'article_id' => $id,
        'action' => 'delete',
    ]);
    return $database->getInsertId(); // Can return values
});

// Manual control
$database->beginTransaction();
try {
    $database->query('...');
    $database->commit();
} catch (\Exception $e) {
    $database->rollBack();
    throw $e;
}
```

## Reflection API

**Introspect database structure:**
```php
$reflection = $database->getReflection();

// Tables
foreach ($reflection->tables as $name => $table) {
    echo $table->name;
    echo $table->view ? 'VIEW' : 'TABLE';
    echo $table->fullName; // Including schema if exists
}

if ($reflection->hasTable('users')) {
    $table = $reflection->getTable('users');
}

// Columns
foreach ($table->columns as $name => $column) {
    echo "$column->name: $column->nativeType";
    echo $column->nullable ? 'NULL' : 'NOT NULL';
    echo $column->default;
    echo $column->autoIncrement ? 'AUTO_INCREMENT' : '';
    echo $column->primary ? 'PRIMARY KEY' : '';
}

// Indexes
foreach ($table->indexes as $index) {
    $columns = implode(', ', array_map(fn($col) => $col->name, $index->columns));
    echo ($index->primary ? 'PRIMARY KEY' : 'INDEX') . ": $columns";
    echo $index->unique ? 'UNIQUE' : '';
}

// Primary key
if ($primaryKey = $table->primaryKey) {
    $columns = implode(', ', array_map(fn($col) => $col->name, $primaryKey->columns));
}

// Foreign keys
foreach ($table->foreignKeys as $fk) {
    $localCols = implode(', ', array_map(fn($col) => $col->name, $fk->localColumns));
    $foreignCols = implode(', ', array_map(fn($col) => $col->name, $fk->foreignColumns));
    echo "$localCols -> {$fk->foreignTable->name}($foreignCols)";
}
```
