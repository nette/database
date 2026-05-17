# Connection & drivers

## Execution path

`Connection` connects **lazily** — the constructor opens the PDO only when the `lazy`
option is falsy; otherwise the first `getPdo()`/`getDriver()`/`preprocess()` triggers
`connect()` (which also instantiates the driver from `PDO::ATTR_DRIVER_NAME`, builds
the `SqlPreprocessor`, and fires `onConnect`).

`query()` = `preprocess()` (which runs the preprocessor only when there are
parameters) → `new ResultSet(...)`. **The SQL actually executes in the `ResultSet`
constructor, not in `query()`** — so timing, binding, and exception conversion all
live there (see results-and-types.md). The `fetch*` shortcuts on `Connection` just
delegate to `query(...)->fetch*()`.

## The `Driver` dialect abstraction

Each engine implements `Driver`: `delimite` (identifier quoting — MySQL backticks,
PgSql double-quotes, both doubling), `formatDateTime`/`formatDateInterval`/`formatLike`,
`applyLimit`, schema reflection (`getTables`/`getColumns`/`getIndexes`/`getForeignKeys`/
`getColumnTypes`), `convertException`, and `isSupported` over the `Support*` feature
constants.

**`applyLimit` is the most dialect-divergent piece** — MySQL uses `LIMIT` (with the
`LIMIT 18446744073709551615 OFFSET` trick for offset-only), PgSql separate `LIMIT`/
`OFFSET`, SQL Server `OFFSET … ROWS FETCH NEXT … ROWS ONLY`, MS SQL/ODBC inject a
`TOP n` (no offset), Oracle wraps in a `ROWNUM` subquery. Result-set type detection is
per-driver `getColumnTypes`: PgSql and MsSql map the whole result set via
`Helpers::detectTypes`, MySQL/SQLite/Sqlsrv go column-by-column via
`Helpers::detectType`, and Odbc/Oci detect nothing. MySQL adds dialect rules
(`NEWDECIMAL` precision 0 → integer, `TINY` len 1 + `convertBoolean` → bool, `TIME` →
interval).

## Exception mapping

```
\PDOException → DriverException
                 ├── ConnectionException → ConnectionLostException
                 ├── ConstraintViolationException
                 │     ├── ForeignKey / NotNull / Unique / CheckConstraintViolation
                 ├── DeadlockException
                 └── LockTimeoutException
```

Note `Deadlock`/`LockTimeout` extend `DriverException` **directly**, not the
constraint hierarchy. There is **no** `RetryableException` marker and `transaction()`
performs no retries — retrying on deadlock/lock-timeout/connection-lost is the
caller's job. The mapping is **per driver** in
`convertException`: MySQL keys on the numeric error code, PgSql on the SQLSTATE; an
unrecognized error falls back to a bare `DriverException::from()`. `DriverException::from`
parses `errorInfo`, or the `SQLSTATE[..] [..] ..` pattern from the message when
`errorInfo` is absent. Conversion is invoked in the `ResultSet` constructor (which also
attaches the query string and params) and in `getInsertId`; `connect()`/`quote()` use
`ConnectionException::from`/`DriverException::from` directly because the driver may not
exist yet.
