# Transactions

Nesting is **counter-based only** — there are **no savepoints**.

## `transaction()`

`transaction(callable $callback, int $attempts = 1)` runs a phase machine
(`begin`/`body`/`commit`) inside a retry loop. Nesting is tracked purely by
`$transactionDepth`:

- a real `BEGIN` is issued only when `transactionDepth === 0`; a nested call merely
  increments the counter and **emits no SQL**;
- `COMMIT` likewise only at depth 0; on an exception, `ROLLBACK` only when it unwinds
  back to depth 0 (wrapped in try/catch, since the server may have rolled back
  already);
- a **retry** happens only at the outermost level, when `$attempt < $attempts` and the
  exception implements `RetryableException` (deadlock / lock timeout; a lost
  connection is deliberately not retryable — the outcome of an in-flight `COMMIT`
  is unknown) — firing `onRetry` between attempts.

**The consequence to internalize:** a nested `transaction()` gives **no partial
rollback**. Only the outermost transaction issues real `BEGIN`/`COMMIT`/`ROLLBACK`, so
an inner failure tears down the *entire* outer transaction. The idea of savepoints is
**not implemented** — there is no `SAVEPOINT`/`RELEASE` anywhere in the code.

## Manual control is fenced off inside a callback

`beginTransaction()`/`commit()`/`rollBack()` each **throw** if called while
`transactionDepth !== 0`, i.e. manual transaction control is forbidden inside a
`transaction()` callback (they would desynchronize the counter). They execute via the
`::beginTransaction`/`::commit`/`::rollBack` PDO channel (see results-and-types.md).
`getInsertId` returns `lastInsertId` as a string (`'0'` on false), converting a
`PDOException` through the driver.
