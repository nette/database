# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works
internally and the rationale behind key design decisions - lives in `docs/`.
Consult it before non-trivial changes; it is the source of truth from which the
public manual is distilled.

Two independent worlds - the low-level core and the Explorer (ActiveRow) layer -
each with sharp edges (lazy execution, accessed-column narrowing, N+1 batching,
context-detected preprocessor modes). Read `docs/internals/` before touching them.

## Project Overview

**Nette Database** is a database abstraction layer offering two components:

1. **Core** - a PDO wrapper with an advanced SQL preprocessor and parameter
   substitution.
2. **Explorer** - an ActiveRow layer (inspired by NotORM) with convention-based
   relationships and automatic N+1 prevention.

Supports MySQL, PostgreSQL, SQLite, MS SQL Server, and Oracle.

- **PHP Version**: 8.1 - 8.5
- **Package**: `nette/database`

## Essential Commands

```bash
# Run all tests
vendor/bin/tester tests -s -C

# Run one test directory / file
vendor/bin/tester tests/Database/Explorer -s -C
vendor/bin/tester tests/Database/Explorer/Explorer.basic.phpt -s -C

# Static analysis (PHPStan level 5 + phpstan-nette)
composer phpstan
```

Most tests connect to real MySQL/PostgreSQL/MS SQL servers via
`@dataProvider databases.ini`. Bring the servers up with the repo's
`docker-compose.yml` (`docker compose up -d`, wait for `healthy`) before running
them; a `Connection refused` / `could not find driver` failure means the servers
aren't up yet, not a broken test.

## Conventions

- Every file starts with `declare(strict_types=1);`; everything typed; single
  quotes unless the string contains an apostrophe; Nette Coding Standard.
- Use generic annotations for IDE/PHPStan support: `@return Selection<ProductRow>`,
  `@template T of ActiveRow`. Method phpDoc starts with a 3rd-person verb (Returns,
  Formats, Checks); document a param/return only when it adds info beyond the type.
- Tests are Nette Tester `.phpt` files; use `@dataProvider databases.ini` to run
  against every engine, `test()` / `testException()` with descriptive names, and
  **no comment before `test()`**. Fixtures: `tests/Database/files/{driver}-nette_test1.sql`.

## Working in this repo

- **The Explorer is lazy and self-narrowing.** `accessColumn` is the single seam
  every read passes through; a first query fetches `SELECT *`, later ones narrow to
  the accessed columns (cached), and relations are batched to avoid N+1. The cache
  key even depends on the call-site (`debug_backtrace`) - a real refactor trap. See
  `docs/internals/explorer.md`.
- **The SQL preprocessor picks its array mode from surrounding SQL context**
  (`?and`/`?set`/`?values`/`?order`/`?list`), so the same array expands differently
  after `WHERE` vs `SET` vs `INSERT`. See `docs/internals/sql-preprocessor.md`.
- **Nested transactions use a depth counter, not savepoints** - only the outermost
  `transaction()` issues a real BEGIN/COMMIT/ROLLBACK; there is no partial rollback,
  and no retry mechanism (no `$attempts`, no `RetryableException`, no `onRetry`).
  There is no `TypeConverter` class either (DB->PHP conversion is
  `Helpers::normalizeRow`). Don't document designed-but-absent features as present.
- **Array expansion is a mass-assignment surface.** Passing raw user input as the
  array to `insert`/`update`/`where` lets an attacker set arbitrary columns and
  inject operators/SQL via keys - always whitelist columns first. Full guidance is
  web-manual material.
- User-facing how-to (Explorer/Selection API, `?`-placeholder reference, NEON
  config, transactions, Reflection API) is manual material and lives in the public
  web docs, not here.
