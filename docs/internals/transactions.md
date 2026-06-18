# Transactions

Nesting is **counter-based only** — there are **no savepoints**.

## `transaction()`

`transaction(callable $callback): mixed` runs the callback between `BEGIN` and
`COMMIT`/`ROLLBACK`. Nesting is tracked purely by `$transactionDepth`:

- a real `BEGIN` is issued only when `transactionDepth === 0`; a nested call merely
  increments the counter and **emits no SQL**;
- `COMMIT` likewise only at depth 0; on an exception, `ROLLBACK` only when it unwinds
  back to depth 0, then the exception is rethrown. Any failure of the rollback itself
  (e.g. when the server already rolled back after a deadlock, or an `onQuery` handler
  throws) is swallowed so it cannot mask the original exception.

**The consequence to internalize:** a nested `transaction()` gives **no partial
rollback**. Only the outermost transaction issues real `BEGIN`/`COMMIT`/`ROLLBACK`, so
an inner failure tears down the *entire* outer transaction. The idea of savepoints is
**not implemented** — there is no `SAVEPOINT`/`RELEASE` anywhere in the code.

**No retries either (so don't document them as present):** there is no `$attempts`
parameter, no retry loop, no `RetryableException` marker, no `onRetry` event.
`DeadlockException`, `LockTimeoutException` and `ConnectionLostException` exist, but
nothing in `transaction()` catches and retries them — retrying is the caller's job.

## Manual control is fenced off inside a callback

`beginTransaction()`/`commit()`/`rollBack()` each **throw** if called while
`transactionDepth !== 0`, i.e. manual transaction control is forbidden inside a
`transaction()` callback (they would desynchronize the counter). They execute via the
`::beginTransaction`/`::commit`/`::rollBack` PDO channel (see results-and-types.md).
`getInsertId` returns `lastInsertId` as a string (`'0'` on false), converting a
`PDOException` through the driver.
