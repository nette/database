# ResultSet & type normalization

## ResultSet executes eagerly and iterates once

The `ResultSet` constructor **runs the query immediately** — it times, prepares, binds
(PDO param types by PHP type: bool→`PARAM_BOOL`, int→`PARAM_INT`, resource→`PARAM_LOB`,
null→`PARAM_NULL`, else `PARAM_STR`), sets `FETCH_ASSOC`, and executes; a `PDOException`
becomes a converted `DriverException`. A query string beginning with **`::`** is a
special channel: it calls the named PDO method directly (this is how transactions issue
`::beginTransaction`/`::commit`/`::rollBack`).

The iterator is **one-way** — `rewind()` throws once the iterator has advanced past
the first row. `fetchAssoc` is the core (each `fetch` → `normalizeRow`, with a
duplicate-column check on the first row only); `fetch` wraps it in a `Row`; `fetchAll`
caches `iterator_to_array` into `$rows`. `getRowCount()` returns `rowCount()` (affected
rows for non-SELECT; `null` for `::`-channel calls with no statement).

## DB→PHP conversion is a function, not a class

There is **no `TypeConverter` class**. Conversion is `Helpers::normalizeRow()`, wired
into the `Connection` as the `rowNormalizer` closure (which the `newDateTime` option
swaps between `Nette\Database\DateTime` and `Nette\Utils\DateTime`). It can be replaced
via `setRowNormalizer`.

`normalizeRow` iterates the result-set column types (`IStructure::FIELD_*`) and
converts, with a few traps worth knowing:

- **`FIELD_INTEGER`** → `$value * 1`, but keeps the original **string** if it would
  overflow to a float.
- **`FIELD_FLOAT`/`FIELD_DECIMAL`** → `(float)` — **a decimal is coerced to float**
  (precision hazard).
- **`FIELD_BOOL`** → `$value && $value !== 'f' && $value !== 'F'` (handles PostgreSQL's
  `'f'`).
- **`FIELD_DATETIME`/`FIELD_DATE`** → `new $dateTimeClass($value)`, but `'0000-00…'`
  → `null`; `FIELD_TIME` sets the date to `0001-01-01`; `FIELD_TIME_INTERVAL` →
  `\DateInterval`; `FIELD_UNIX_TIMESTAMP` → a datetime via `setTimestamp`.
- **Binary and JSON are not converted** — they come back as the raw PDO string.
  `FIELD_BINARY` exists only for detection, not value conversion.
