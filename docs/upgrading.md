# Upgrading nette/database

## 3.2.10

- **Renamed** the `length` key in the result of `Nette\Database\Helpers::parseColumnType()` → `size`; the old name was removed (5e04db8f)
- **Renamed** `$nsTable` of `Nette\Database\Conventions\DiscoveredConventions::getHasManyReference()` → `$table`; affects callers using named arguments; the old name was removed (0da2ddd4)
- **Changed** `Nette\Database\Table\Selection::insert()` given a list of rows (integer keys, also with gaps, or a `Traversable` yielding them) performs a bulk insert and returns the number of affected rows; previously it returned the `Nette\Database\Table\ActiveRow` of the first row (d1d6e805)
- **Changed** `Nette\Database\Drivers\MsSqlDriver::getColumns()` no longer reports the `unsigned` key (cfb2b213)
- **Changed** `Nette\Database\Reflection::getTable()` throws `InvalidArgumentException` for an unknown table also on drivers that report no columns for it (SQLite); previously it returned a table without columns (370205ce)
- **Changed** `Nette\Database\Connection::transaction()` rethrows the original exception of the callback when the final rollback fails; previously the exception of the rollback was thrown; affects code catching it (8f04a2ff)
- **Changed** `Nette\Database\Connection::connect()` throws `Nette\InvalidStateException` for an unknown PDO driver name; previously a class-not-found `Error` was raised (9dcf7e51)
- **Changed** `Nette\Database\Table\ActiveRow::related()` throws `Nette\NotSupportedException` on a table with a composite primary key; previously a `TypeError` was raised (f85a581a)
- **Changed** `Nette\Database\Table\Selection::page()` throws `Nette\InvalidArgumentException` when `$itemsPerPage` is less than 1; previously a `DivisionByZeroError` was raised (69f5cec8)
- **Changed** a unique, NOT NULL, foreign key or CHECK constraint violation on SQL Server (2627, 2601, 515, 547) now throws `Nette\Database\UniqueConstraintViolationException`, `Nette\Database\NotNullConstraintViolationException`, `Nette\Database\ForeignKeyConstraintViolationException` or `Nette\Database\CheckConstraintViolationException` instead of `Nette\Database\DriverException`; the new classes extend the old one (d8db52c6)
- **Changed** a CHECK constraint violation on SQLite now throws `Nette\Database\CheckConstraintViolationException` instead of `Nette\Database\ConstraintViolationException`; the new class extends the old one (d8db52c6)

## 3.2.9

- **Signature** `Nette\Database\Reflection\ForeignKey::$name` is now `string` and the `$name` parameter of `Nette\Database\Reflection\ForeignKey::__construct()` is required; previously both were nullable and the parameter optional (042159e1)
- **Signature** `Nette\Database\ResultSet::__construct()`: the `$normalizer` parameter is now `?Closure` instead of `?callable`; wrap a callable with first-class callable syntax (294f8b6f)
- **Signature** `Nette\Database\Table\Selection::insert()` returns `ActiveRow|array|int`; previously `bool` was also possible; affects code testing the result against `false` (b76f9d97)
- **Signature** `Nette\Database\Table\GroupedSelection::insert()` returns `ActiveRow|array|int`; previously `bool` was also possible; affects code testing the result against `false` (b76f9d97)
- **Changed** `Nette\Database\Table\Selection::execute()` no longer throws `Nette\InvalidStateException` when the table has no primary key and no explicit `select()`; the query runs and rows are keyed by result position (e8ea5983)
- **Changed** `Nette\Database\Table\Selection::wherePrimary()` now throws `Nette\NotSupportedException` when a scalar key is given for a composite primary key; previously it silently built a broken condition; pass the key as a list or associative array (e8ea5983)
- **Changed** `Nette\Database\Table\Selection::where()` now throws `Nette\InvalidArgumentException` when an array condition is given without a matching array of parameters; previously it silently built an incorrect condition (e8ea5983)
- **Changed** a connection dropped mid-operation now throws `Nette\Database\ConnectionLostException` instead of `Nette\Database\ConnectionException` on MySQL (2006, 2013) and PostgreSQL (08003, 08006, HY000); the new class extends the old one, so existing catch blocks keep working (524afceb)
- **Changed** a deadlock or serialization failure now throws `Nette\Database\DeadlockException` instead of `Nette\Database\DriverException` on MySQL (1213), PostgreSQL (40001, 40P01), SQL Server (1205) and Oracle (ORA-00060); the new class extends the old one (1a29dd16)
- **Changed** a lock wait timeout now throws `Nette\Database\LockTimeoutException` instead of `Nette\Database\DriverException` on MySQL (1205), PostgreSQL (55P03) and SQL Server (1222); the new class extends the old one (cc81ea27)
- **Changed** a CHECK constraint violation now throws `Nette\Database\CheckConstraintViolationException` instead of `Nette\Database\DriverException` on MySQL 8.0.16+ (3819) and PostgreSQL (23514); SQL Server still throws the generic exception (5532941d)

## 3.2.7

- **Signature** `Nette\Database\IStructure::getBelongsToReference()`: the `$column` parameter was removed; the method always returns all belongsTo references of the table; affects custom implementations (42c5f525)
- **Signature** `Nette\Database\IStructure::getHasManyReference()`: the `$targetTable` parameter was removed; the method always returns all hasMany references of the table; affects custom implementations (42c5f525)
- **Signature** `Nette\Database\Structure::getBelongsToReference()`: the `$column` parameter was removed and the return type is `array` instead of `?array` (42c5f525)
- **Signature** `Nette\Database\Structure::getHasManyReference()`: the `$targetTable` parameter was removed and the return type is `array` instead of `?array` (42c5f525)
- **Changed** `Nette\Database\Connection::query()` now inlines integers of an `?list` or `IN (?)` placeholder directly into the SQL instead of binding them as parameters; the query string and `Nette\Database\ResultSet::getParameters()` change accordingly (d0ea1a8f)

## 3.2.6

- **Renamed** the `?auto` placeholder → `?`; the old name was removed (7972f601)
- **Replaced** `Nette\Database\Table\Selection::createRow()` with `Nette\Database\Explorer::createActiveRow()`: override the factory on a custom `Nette\Database\Explorer` subclass instead of on the selection; the old method is silently deprecated (4a6c0767)
- **Replaced** `Nette\Database\Table\Selection::createSelectionInstance()` with `Nette\Database\Explorer::table()`; the old method is silently deprecated (4a6c0767)
- **Replaced** `Nette\Database\Table\Selection::createGroupedSelectionInstance()` with `Nette\Database\Explorer::createGroupedSelection()` (marked @internal); the old method is silently deprecated (4a6c0767)
- **Deprecated** `Nette\Database\Driver::SupportSubselect`: all drivers support subselects, drop the `isSupported()` check; the old name is silently deprecated (600997ba)
- **Deprecated** `Nette\Database\Table\Selection::fetchAssoc()`: the old name is silently deprecated (0c7e0af7)
- **Changed** `Nette\Database\Table\Selection::where()` and `whereOr()` now generate `column != value` for a `NOT` operator with a non-null scalar; previously the operator was silently ignored and `column = value` was generated (ccab26e2)
- **Changed** `Nette\Database\Table\Selection::where()` and `whereOr()` now wrap a group of several conditions in double parentheses `((a) AND (b))` instead of `(a) AND (b)`, so it nests safely in another condition (8bc4ab30)
- **Changed** `Nette\Database\Connection::query()` now formats `Nette\Database\Table\ActiveRow`, `BackedEnum` and `Stringable` parameters through the general value formatter, so they are inlined as SQL literals instead of always being bound as PDO parameters (7972f601)
- **Changed** `Nette\Database\Helpers::parseColumnType()` returns `null` in the `type` key for an unrecognized column type; previously the key was undefined and reading it raised a PHP warning (c853e493)

## 3.2.5

- **Renamed** `Nette\Database\Driver::SupportMultiColumnAsOrCond` → `Nette\Database\Driver::SupportMultiColumnAsOrCondition`; the old name was removed (3f9e12a5)
- **Renamed** `$item` of `Nette\Database\Driver::isSupported()` → `$feature`; affects callers using named arguments and custom drivers; the old name was removed (3f9e12a5)
- **Renamed** `Nette\Database\ResultSet::fetchFields()` → `Nette\Database\ResultSet::fetchList()`; the old name still works as an alias (7a4dedff)
- **Renamed** `Nette\Database\Connection::fetchFields()` → `Nette\Database\Connection::fetchList()`; the old name still works as an alias (7a4dedff)
- **Renamed** `Nette\Database\Explorer::fetchFields()` → `Nette\Database\Explorer::fetchList()`; the old name still works as an alias (7a4dedff)
- **Signature** `Nette\Database\ResultSet::fetchAssoc()`: `$path` is now optional and nullable and the return type is `?array`; without a path the method returns the whole result as an associative array (f6a42951)

## 3.2.4

- **Renamed** `Nette\Database\Driver::SUPPORT_SEQUENCE` → `Nette\Database\Driver::SupportSequence`; the old name is silently deprecated (64b5b00e)
- **Renamed** `Nette\Database\Driver::SUPPORT_SELECT_UNGROUPED_COLUMNS` → `Nette\Database\Driver::SupportSelectUngroupedColumns`; the old name is silently deprecated (64b5b00e)
- **Renamed** `Nette\Database\Driver::SUPPORT_MULTI_INSERT_AS_SELECT` → `Nette\Database\Driver::SupportMultiInsertAsSelect`; the old name is silently deprecated (64b5b00e)
- **Renamed** `Nette\Database\Driver::SUPPORT_MULTI_COLUMN_AS_OR_COND` → `Nette\Database\Driver::SupportMultiColumnAsOrCond`; the old name is silently deprecated (64b5b00e)
- **Renamed** `Nette\Database\Driver::SUPPORT_SUBSELECT` → `Nette\Database\Driver::SupportSubselect`; the old name is silently deprecated (64b5b00e)
- **Renamed** `Nette\Database\Driver::SUPPORT_SCHEMA` → `Nette\Database\Driver::SupportSchema`; the old name is silently deprecated (64b5b00e)
- **Renamed** the MySQL driver option `database.<name>.options.supportBooleans` → `database.<name>.options.convertBoolean`; the old name still works as an alias (451b33ea)
- **Renamed** `$key` of `Nette\Database\ResultSet::fetchPairs()` → `$keyOrCallback`; it now also accepts a callback; affects callers using named arguments; the old name was removed (efab8342)
- **Renamed** `$key` of `Nette\Database\Table\Selection::fetchPairs()` → `$keyOrCallback`; it now also accepts a callback; affects callers using named arguments; the old name was removed (efab8342)
- **Signature** `Nette\Database\Helpers::toPairs()`: `$key` and `$value` lost their default values and `$key` also accepts a `Closure`; pass `null` explicitly to omit them (efab8342)
- **Signature** the protected properties `Nette\Database\Table\Selection::$explorer`, `Nette\Database\Table\Selection::$conventions`, `Nette\Database\Table\Selection::$name`, `Nette\Database\Table\Selection::$primary`, `Nette\Database\Table\Selection::$cache`, `Nette\Database\Table\GroupedSelection::$column`, `Nette\Database\Table\GroupedSelection::$refTable`, `Nette\Database\Table\SqlBuilder::$conventions`, `Nette\Database\Table\SqlBuilder::$tableName`, `Nette\Database\Table\SqlBuilder::$delimitedTable`, `Nette\Database\Structure::$cache`, `Nette\Database\Structure::$connection`, `Nette\Database\Conventions\DiscoveredConventions::$structure`, `Nette\Database\Conventions\StaticConventions::$table`, `Nette\Database\Conventions\StaticConventions::$primary` and `Nette\Database\Conventions\StaticConventions::$foreign` are now `readonly`; affects subclasses assigning to them outside the constructor (8b4db308)
- **Changed** `Nette\Database\Connection::connect()` no longer forces the PDO attribute `ATTR_ERRMODE` to exception mode after connecting, so a value passed in the connection options now takes effect (a5370587)
- **Changed** `Nette\Database\Drivers\MySqlDriver::initialize()` always uses the `utf8mb4` charset; previously it fell back to `utf8` on servers older than 5.5.3, which now fail on connect unless `charset` is set explicitly in the options (1f80a34a)
- **Changed** `Nette\Database\Drivers\SqlsrvDriver::applyLimit()` always generates `OFFSET ... FETCH`; previously it fell back to `TOP` on SQL Server older than 2012, which is no longer supported (3f6917f1)
- **Changed** `Nette\Database\Reflection::getTable()` no longer throws `Nette\InvalidArgumentException` immediately for a table missing from the cached table list; it first tries to access the table directly (a22eddbf)
- **Changed** `Nette\Database\Reflection::getTable()` and `Nette\Database\Reflection::hasTable()` no longer prefix an unqualified table name with the `public` schema on drivers supporting schemas; pass a fully qualified name (a1fb5171)
- **Changed** `Nette\Database\Drivers\PgSqlDriver::getColumns()` reports the numeric precision in `size` for NUMERIC and DECIMAL columns instead of the raw `atttypmod` value, and reports the length of fixed-length types that were `null` before (b3c6e120)
- **Changed** `Nette\Database\Drivers\SqlsrvDriver::getColumns()` reports the column precision or maximum length in `size`; previously it was always `null` (b3c6e120)
- **Changed** `Nette\Database\Helpers::detectType()` detects an `INT UNSIGNED` column as `Nette\Database\IStructure::FIELD_INTEGER`; previously it matched no pattern and fell back to string, so such columns are now converted to `int` (marked @internal) (52ca4979)

## 3.2.1

- **Renamed** the `column` key in the result of `Nette\Database\Drivers\SqlsrvDriver::getForeignKeys()` → `foreign`; the old name was removed (1d9e2866)
- **Changed** `Nette\Database\Drivers\SqliteDriver::getColumnTypes()` no longer forces DATE and DATETIME columns to `Nette\Database\IStructure::FIELD_UNIX_TIMESTAMP` when a `formatDateTime` option other than `U` is set; the type is detected from the native type instead (27558e30)

## 3.2.0

- **Renamed** `$saveCache` of `Nette\Database\Table\GroupedSelection::emptyResultSet()` → `$clearCache`; affects subclasses overriding it; the old name was removed (7a2aeafc)
- **Renamed** `$deleteRererencedCache` of `Nette\Database\Table\GroupedSelection::emptyResultSet()` → `$deleteReferencedCache`; affects subclasses overriding it; the old name was removed (7a2aeafc)
- **Renamed** `$deleteRererencedCache` of `Nette\Database\Table\Selection::emptyResultSet()` → `$deleteReferencedCache`; affects subclasses overriding it; the old name was removed (7a2aeafc)
- **Signature** `Nette\Database\ResultSet::fetchField()`: the `$column` parameter was removed (deprecated since 3.0.0); the method always returns the first field of the row (0eeb6544)
- **Signature** `Nette\Database\Conventions::getPrimary()` declares the return type `string|array|null`; a custom implementation must repeat it (5dcb58d5)
- **Signature** `Nette\Database\IStructure::getPrimaryKey()` declares the return type `string|array|null`; a custom implementation must repeat it (5dcb58d5)
- **Signature** `Nette\Database\Connection::__construct()`: `$options` no longer accepts `null` and defaults to `[]` (b4509d6b)
- **Signature** the `$cacheStorage` parameter of `Nette\Database\Explorer::__construct()`, `Nette\Database\Table\Selection::__construct()`, `Nette\Database\Table\GroupedSelection::__construct()` and `Nette\Database\Structure::__construct()` is typed `Nette\Caching\Storage` instead of `Nette\Caching\IStorage`; subclasses must use the new name (67273d34)
- **Signature** `Nette\Database\Table\Selection::select()`: `$columns` is now typed `string`; passing anything else raises a `TypeError` (3b63aae8)
- **Signature** `Nette\Database\Table\GroupedSelection::select()`: `$columns` is now typed `string`; passing anything else raises a `TypeError` (3b63aae8)
- **Signature** native parameter, return and property types were added throughout, most visibly on `Nette\Database\Table\Selection::where()`, `Nette\Database\Table\Selection::fetchPairs()`, `Nette\Database\Table\SqlBuilder::addWhere()`, `Nette\Database\Table\SqlBuilder::addOrder()`, `Nette\Database\Table\SqlBuilder::setGroup()`, `Nette\Database\Table\SqlBuilder::addJoinCondition()`, `Nette\Database\ResultSet::fetchPairs()`, `Nette\Database\Helpers::toPairs()`, `Nette\Database\Table\GroupedSelection::setActive()`, `Nette\Database\Helpers::$maxLength`, `Nette\Database\DriverException::$queryString` and `Nette\Database\DriverException::$params`; subclasses overriding them and custom implementations must match the declarations (5dcb58d5, 3b63aae8, 9419ca1b)
- **Changed** `Nette\Database\Connection`, `Nette\Database\Explorer`, `Nette\Database\ResultSet`, `Nette\Database\Structure`, `Nette\Database\Table\Selection` and `Nette\Database\Table\SqlBuilder` no longer use the `Nette\SmartObject` trait; reading an undeclared property or calling an undeclared method raises a plain PHP error instead of `Nette\MemberAccessException` (93767291)
- **Changed** `Nette\Database\Helpers::normalizeRow()` converts a DATE, DATETIME or TIME column holding a MySQL zero date to `null`; previously it built a date object from the invalid value (marked @internal) (f5062e5c)
- **Changed** `Nette\Database\Helpers::normalizeRow()` gives a TIME column the date part 0001-01-01; previously it used today's date (marked @internal) (20dabde1)
- **Changed** `Nette\Database\Helpers::detectType()` classifies DECIMAL, NUMERIC and MONEY columns as `Nette\Database\IStructure::FIELD_DECIMAL`; previously as `Nette\Database\IStructure::FIELD_FLOAT`; the normalized value is still a float, but code comparing the detected type no longer matches (marked @internal) (39f98a8c)
- **Changed** `Nette\Database\Drivers\MySqlDriver::getColumnTypes()` returns a DECIMAL column with precision 0 as `int` (91eb6fc7)

## 3.1.5

- **Renamed** the service `database.<name>.context` → `database.<name>.explorer`; the old name still works as an alias (c6eea6cb)
- **Renamed** `Nette\Database\Helpers::initializeTracy()` → `Nette\Bridges\DatabaseTracy\ConnectionPanel::initialize()`; the old name is silently deprecated (182f2bcc)
- **Renamed** `Nette\Database\Helpers::createDebugPanel()` → `Nette\Bridges\DatabaseTracy\ConnectionPanel::initialize()`; its previous replacement `Nette\Database\Helpers::initializeTracy()` is now deprecated itself; the old name is silently deprecated (182f2bcc)
- **Changed** `Nette\Database\Helpers::normalizeRow()` always returns a column detected as float as `float`; previously a very small or very large value was returned as a string to preserve precision (marked @internal) (b138afb9)
- **Changed** `Nette\Database\Drivers\PgSqlDriver::getTables()`, `getColumns()` and `getIndexes()` include partitioned tables (976e352f)
- **Changed** `Nette\Database\Drivers\SqliteDriver::getForeignKeys()` returns the raw referenced column name from `PRAGMA foreign_key_list`; previously an empty name (the implicit rowid) was normalized to `null` (fd7ccf23)

## 3.1.4

- **Deprecated** `Nette\Database\Connection::queryArgs()`: use `Nette\Database\Connection::query()` and pass the parameters as variadic arguments; the old name is silently deprecated (0b3b2f5d)
- **Deprecated** `Nette\Database\Explorer::queryArgs()`: use `Nette\Database\Explorer::query()` and pass the parameters as variadic arguments; the old name is silently deprecated (0b3b2f5d)
- **Deprecated** `Nette\Database\ResultSet::getConnection()`: the old name is silently deprecated (d0f3e196)
- **Changed** `Nette\Database\Table\Selection::count()`, `min()`, `max()` and `sum()` run a wrapped query respecting `group()` and `having()`; the returned value differs for such selections (c4df89d2)

## 3.1.3

- **Signature** `Nette\Database\Helpers::initializeTracy()` returns `?Nette\Bridges\DatabaseTracy\ConnectionPanel`; with `$addBarPanel` left at `false` no panel is created and `null` is returned (08aff4d6)
- **Signature** `Nette\Database\Helpers::createDebugPanel()` returns `?Nette\Bridges\DatabaseTracy\ConnectionPanel`; in production mode no panel is created (08aff4d6)
- **Signature** `Nette\Database\ResultSet::__construct()` gained a `$normalizer` parameter; constructing the result set directly without it skips the column type normalization that used to run unconditionally; pass `Nette\Database\Helpers::normalizeRow()` or the normalizer of the connection (ee2ffbb1)

## 3.1.2

- **Replaced** `Nette\Database\Helpers::createDebugPanel()` with `Nette\Database\Helpers::initializeTracy()`: the parameter order and defaults differ (`$connection`, `$addBarPanel`, `$name`, `$explain`, `$bar`, `$blueScreen`) and the BlueScreen panel is registered automatically; the old name is silently deprecated (c98fec13)
- **Changed** `Nette\Database\Connection::beginTransaction()` throws `LogicException` when called inside a `Nette\Database\Connection::transaction()` callback (3cc79b1b)
- **Changed** `Nette\Database\Connection::commit()` throws `LogicException` when called inside a `Nette\Database\Connection::transaction()` callback (3cc79b1b)
- **Changed** `Nette\Database\Connection::rollBack()` throws `LogicException` when called inside a `Nette\Database\Connection::transaction()` callback (3cc79b1b)
- **Changed** `Nette\Database\Connection::transaction()` joins an already running transaction when nested; only the outermost call begins, commits or rolls back (87251696)
- **Changed** `Nette\Database\Connection::transaction()` passes the connection to the callback as its first argument (8deb8b5a)
- **Changed** `Nette\Database\Explorer::transaction()` passes the explorer to the callback as its first argument (8deb8b5a)
- **Changed** `Nette\Database\Drivers\MySqlDriver::isSupported()` reports no support for `Nette\Database\Driver::SUPPORT_SUBSELECT` again, because MySQL rejects LIMIT inside some subqueries; the SQL generated for MySQL 5.7 and newer returns to the pre-3.1.2 form (8caf4685)
- **Changed** `Nette\Database\Connection::query()` formats a scalar parameter of an `IN (?)` placeholder directly instead of forcing it through list mode (26941559)

## 3.1.1

- **Changed** `Nette\Database\Connection::query()` formats an array passed to a bare `?` placeholder with no established command mode as a set (`key = value, ...`); in 3.1.0 it was a plain comma-separated list (17bc9b2b)

## 3.1.0

- **Removed** `Nette\Bridges\DatabaseTracy\ConnectionPanel::logQuery()`: the panel subscribes to the connection itself (e67a7ef8)
- **Renamed** `Nette\Database\Context` → `Nette\Database\Explorer`; the old name still works as an alias (9023cbb2)
- **Renamed** `Nette\Database\IConventions` → `Nette\Database\Conventions`; the old name still works as an alias (18629c9c)
- **Renamed** `Nette\Database\ISupplementalDriver` → `Nette\Database\Driver`; the old name still works as an alias (b3592e2a)
- **Renamed** `Nette\Database\Table\Selection::$context` → `Nette\Database\Table\Selection::$explorer`; the old name still works as an alias (8e15cb99)
- **Renamed** `Nette\Database\Connection::getSupplementalDriver()` → `Nette\Database\Connection::getDriver()`; the old name is silently deprecated (5a7b548b)
- **Renamed** `$context` of `Nette\Database\Table\Selection::__construct()` → `$explorer`; affects callers using named arguments; the old name was removed (9023cbb2)
- **Renamed** `$context` of `Nette\Database\Table\GroupedSelection::__construct()` → `$explorer`; affects callers using named arguments; the old name was removed (9023cbb2)
- **Deprecated** `Nette\Database\IRow`: type-hint `Nette\Database\Row` instead; the old name is silently deprecated (efac96a0)
- **Deprecated** `Nette\Database\IRowContainer`: type-hint `Nette\Database\ResultSet` instead; the old name is silently deprecated (efac96a0)
- **Deprecated** `Nette\Database\Table\IRow`: type-hint `Nette\Database\Table\ActiveRow` instead; the old name is silently deprecated (efac96a0)
- **Deprecated** `Nette\Database\Table\IRowContainer`: type-hint `Nette\Database\Table\Selection` instead; the old name is silently deprecated (efac96a0)
- **Signature** `Nette\Database\IRowContainer` no longer declares `fetch()`, `fetchAll()`, `fetchAssoc()`, `fetchField()` and `fetchPairs()`; a custom implementation no longer has to define them and a type-hint against the interface no longer guarantees them (efac96a0)
- **Signature** `Nette\Database\Table\IRow` no longer declares `getPrimary()`, `getSignature()`, `ref()`, `related()`, `getTable()` and `setTable()`; a custom implementation no longer has to define them and a type-hint against the interface no longer guarantees them (efac96a0)
- **Signature** `Nette\Database\Explorer::__construct()`: `$structure` requires the concrete class `Nette\Database\Structure` instead of `Nette\Database\IStructure`, so a custom structure implementation can no longer be passed (18629c9c)
- **Signature** `Nette\Bridges\DatabaseTracy\ConnectionPanel::__construct()` gained a required `Tracy\BlueScreen $blueScreen` parameter (94caf0bf)
- **Signature** `Nette\Database\Helpers::createDebugPanel()`: `$explain` and `$name` lost their default values and the required parameters `Tracy\Bar $bar` and `Tracy\BlueScreen $blueScreen` were added (94caf0bf)
- **Signature** the return type `Nette\Database\IRow` was replaced by the concrete classes on `Nette\Database\Connection::fetch()`, `Nette\Database\Context::fetch()` and `Nette\Database\ResultSet::fetch()` (now `?Nette\Database\Row`), on `Nette\Database\Table\Selection::fetch()` (now `?Nette\Database\Table\ActiveRow`) and on `Nette\Database\Table\ActiveRow::ref()` (now `?self`); affects subclasses overriding them (d24acd94)
- **Changed** `Nette\Database\Connection::query()` formats an array passed to a placeholder with no established command mode as a plain comma-separated list; previously as a set (`key = value, ...`), so an associative array loses its keys (e5713242)
- **Changed** `Nette\Database\Connection::query()` no longer auto-formats an array nested in a value without an explicit placeholder mode; use `?list`, `?set` and the like (05ff6199)
- **Changed** `Nette\Database\Connection::query()` substitutes the primary key only for `Nette\Database\Table\ActiveRow` instances; a class merely implementing `Nette\Database\Table\IRow` is no longer special-cased (d24acd94)
- **Changed** `Nette\Database\Drivers\MySqlDriver::isSupported()` reports support for `Nette\Database\Driver::SUPPORT_SUBSELECT` on MySQL 5.7 and newer, so a selection may generate a real subquery where it previously ran a separate query (e89a34aa)

## 3.0.7

- **Config** `database.debugger`: the default is no longer `true` but auto-detection by the presence of a `Tracy\BlueScreen` service; set `debugger: true` explicitly to register the panel unconditionally (a7eb6d7d)
- **Changed** `Nette\Bridges\DatabaseTracy\ConnectionPanel::getPanel()` no longer sets `$disabled` to `true`, so query logging continues after the panel has been rendered (1f457f6c)
- **Changed** `Nette\Database\DriverException::getCode()` returns the SQLSTATE from the `errorInfo` of the original PDO exception when it has one; previously the code of the PDO exception itself (662d3844)

## 3.0.6

- **Changed** `Nette\Database\Connection::query()` writes an int, float or bool formatted as an SQL literal (outside a bound placeholder, e.g. in an array condition) as a bare number instead of a quoted string (b908881b)

## 3.0.5

- **Signature** `Nette\Database\Connection::fetch()` returns `?Nette\Database\IRow` instead of `?Nette\Database\Row`; subclasses overriding it must widen the return type (4f25cc17)
- **Signature** `Nette\Database\Context::fetch()` returns `?Nette\Database\IRow` instead of `?Nette\Database\Row`; subclasses overriding it must widen the return type (4f25cc17)
- **Changed** `Nette\Database\DriverException::from()` copies the file and line of the original PDO exception, so `getFile()` and `getLine()` no longer point at the place where it was created (e6eadca0)

## 3.0.4

- **Changed** `Nette\Database\Table\ActiveRow::__toString()` lets an exception raised while resolving the primary key propagate on PHP 7.4 and newer; previously it was reported through `E_USER_ERROR` (671c233e)
- **Changed** `Nette\Database\Connection::query()` matches the values of a multi-row INSERT or UPDATE to columns by the keys of the first row instead of by position, so rows whose keys are ordered differently now produce different SQL (da87ef48)

## 3.0.3

- **Changed** `Nette\Database\Structure` prefers the foreign key referencing fewer columns when several could satisfy a relation, which can change the table resolved by `Nette\Database\Table\Selection::related()` and `getReferencedTable()` for ambiguous composite keys (3e9159e9)

## 3.0.2

- **Changed** `Nette\Database\Structure::getBelongsToReference()` returns the pair `[table, column]` when `$column` is given; previously only the table name (cf962df6)

## 3.0.1

- **Config** `database.<name>.dsn` is required and the remaining keys are type-checked; an invalid or missing configuration fails at compile time instead of later at runtime (302586ea)

## 3.0.0

- **Removed** `Nette\Database\Drivers\Sqlite2Driver` (84a73eff)
- **Removed** `Nette\Database\ISupplementalDriver::formatBool()` from the interface and from all drivers; it was unused (e662ef65)
- **Removed** `Nette\Database\ISupplementalDriver::normalizeRow()` from the interface and from all drivers; the result set normalizes rows itself (0818225c)
- **Replaced** the fifth parameter of `Nette\Database\Connection::__construct()` (the driver class) with the `driverClass` key of `$options` (1155efab)
- **Replaced** the constructor of a driver with `Nette\Database\ISupplementalDriver::initialize()`: the connection instantiates the driver class with no arguments and calls `initialize()` afterwards, so a custom driver needs a no-argument constructor (43f340b5)
- **Deprecated** the `$column` parameter of `Nette\Database\ResultSet::fetchField()`: read the column from the fetched row instead; using it triggers E_USER_DEPRECATED (25cfd27f)
- **Deprecated** `Nette\Database\Table\Selection::fetchField()`: use `fetch()` and read the column from the returned row; the old name is silently deprecated (25cfd27f)
- **Signature** `Nette\Database\IRowContainer` declares `fetchField()`; a custom implementation must define it (66ef4a7e)
- **Signature** `Nette\Database\IRowContainer::fetchField()` lost its `$column` parameter (25cfd27f)
- **Signature** `Nette\Database\IStructure` declares `getPrimaryAutoincrementKey()`, which was commented out before; a custom implementation must define it (1adcc203)
- **Signature** `Nette\Database\ISupplementalDriver` declares `formatDateInterval()`, which was commented out before; a custom driver must define it (1adcc203)
- **Signature** the `: int` return type was dropped from `Nette\Database\Table\Selection::aggregation()`, `Nette\Database\Table\Selection::min()`, `Nette\Database\Table\Selection::max()`, `Nette\Database\Table\Selection::sum()` and `Nette\Database\Table\GroupedSelection::aggregation()`; the raw value of the database is returned instead of being cast to `int` (9b6a8e7b)
- **Signature** `Nette\Database\Table\Selection::get()` and `Nette\Database\Table\Selection::offsetGet()` return `?Nette\Database\Table\ActiveRow` instead of `?Nette\Database\IRow`; affects subclasses overriding them (4ac63916)
- **Signature** native parameter and return types were added throughout, most visibly on `Nette\Database\Connection::__construct()`, `Nette\Database\Connection::literal()`, `Nette\Database\Connection::preprocess()`, `Nette\Database\Table\Selection::insert()`, `Nette\Database\Table\GroupedSelection::insert()`, `Nette\Database\Table\ActiveRow::ref()`, `Nette\Database\Table\ActiveRow::related()`, `Nette\Database\Table\Selection::count()`, `Nette\Database\Table\Selection::limit()`, `Nette\Database\Table\SqlBuilder::addJoinCondition()` and `Nette\Database\Helpers::createDebugPanel()`, and on every method of `Nette\Database\IConventions`, `Nette\Database\IStructure`, `Nette\Database\IRowContainer`, `Nette\Database\ISupplementalDriver` and `Nette\Database\Table\IRow`; subclasses overriding them and custom implementations must match the declarations (52e8d59a, 3f35235f)
- **Changed** `Nette\Database\ResultSet::fetch()` returns `null` when there is no row; previously `false` (e29841bf)
- **Changed** `Nette\Database\ResultSet::fetchField()` returns `null` when there is no row or field; previously `false` (e29841bf)
- **Changed** `Nette\Database\Connection::fetch()` returns `null` when there is no row; previously `false` (e29841bf)
- **Changed** `Nette\Database\Context::fetch()` returns `null` when there is no row; previously `false` (e29841bf)
- **Changed** `Nette\Database\Table\Selection::fetch()` returns `null` when there is no more row; previously `false` (e29841bf)
- **Changed** `Nette\Database\Table\Selection::fetchField()` returns `null` when there is no row; previously `false` (e29841bf)
- **Changed** `Nette\Database\Table\Selection::get()` returns `null` when there is no such row; previously `false` (e29841bf)
- **Changed** `Nette\Database\IStructure::getColumns()` no longer reports the `unsigned` key (d1e6f530)
- **Changed** `Nette\Database\IStructure::getForeignKeys()` no longer reports the `onDelete` and `onUpdate` keys (d1e6f530)
- **Changed** `Nette\Database\Table\ActiveRow::__isset()` on a key that is not a column reports whether the referenced has-one relation exists; previously it always returned `false` (66983b41)
- **Changed** `Nette\Database\Connection::query()` binds every scalar parameter through PDO; previously a short string was inlined through `quote()` and an int, float or bool was written directly as an SQL literal, so the resulting query string and the types handed to the driver differ (644b587f)
- **Changed** `Nette\Database\Connection::query()` binds a scalar parameter only for SELECT, INSERT, UPDATE, DELETE, REPLACE and EXPLAIN statements; for others (SET, CREATE, ALTER and the like) the value is inlined as an escaped literal (3538dd71)
- **Changed** `Nette\Database\Connection::query()` throws `Nette\InvalidArgumentException` when a `?values`, `?and`, `?or`, `?set` or `?order` placeholder auto-detects a multi-insert whose first item is not an array; previously such input silently produced incorrect SQL (9bdad65e)
- **Changed** `Nette\Database\Helpers::toPairs()` casts a non-object column value used as an array key to `string`; previously the original type was kept and PHP truncated a decimal key to `int` (422e9d2f)
- **Changed** `Nette\Database\Table\Selection::update()` appends ORDER BY to the UPDATE statement when the selection has an `order()`; previously the ordering was ignored (bfd0de85)
- **Changed** `Nette\Database\Drivers\OciDriver::getColumnTypes()` returns an empty array, so Oracle rows are no longer converted to native PHP types (ca96ac09)
- **Changed** `Nette\Database\Drivers\OdbcDriver::getColumnTypes()` returns an empty array, so ODBC rows are no longer converted to native PHP types (cd45bf98)
