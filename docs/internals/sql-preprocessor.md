# SqlPreprocessor

Substitutes parameters into SQL, with **context-detected array modes** — the same
array means different SQL depending on where it appears.

## Context detection

`process()` scans the SQL fragments with one regex, recognizing the leading command
(`SELECT|INSERT|UPDATE|DELETE|REPLACE|EXPLAIN`) and the keywords
`SET|WHERE|HAVING|ORDER BY|GROUP BY|KEY UPDATE` **only when followed by end-of-string
or `?`**. A matched keyword sets `$arrayMode` via `CommandToMode`:

| context | mode | array becomes |
|---|---|---|
| `INSERT`/`REPLACE` | `ModeValues` | `(cols) VALUES (…)` |
| `SET` / `KEY UPDATE` | `ModeSet` | `col = ?, …` |
| `WHERE` / `HAVING` | `ModeAnd` | `col = ? AND …` |
| `ORDER BY` / `GROUP BY` | `ModeOrder` | order list |

An array parameter with no context and no explicit `?mode` **defaults to `ModeSet`**.
Explicit placeholders `?values`/`?set`/`?and`/`?or`/`?order`/`?list`/`?name` force a
mode; `?name` quotes an identifier by splitting on `.` and delimiting each part with
the driver.

## Value formatting

`formatValue` (`match(true)`) handles scalars (int/bool/float), a binary **resource**
(quoted stream contents), strings (`connection->quote`), `null`, and several object
types worth knowing:

- **`SqlLiteral` is not escaped** — and may itself carry parameters
  (`SqlLiteral($sql, $params)`), spliced in via a cloned preprocessor.
- **`ActiveRow` → its primary key** (this is how a row/subquery value is used).
- `DateTimeInterface`/`DateInterval` → the **driver's** format; `BackedEnum` → its
  `value`; `Stringable` → its string.

The same code either **inlines literals** or emits a bound `?` (pushing the value to
`remaining`), switched by `useParams` — which is turned on for the parametric
commands. `formatWhere` carries the fiddly bits: a compound key `'col op'` splits on
the first space; an empty array under a bare key or `IN` becomes `1=0`
(short-circuiting a whole `?and`), under `NOT`/`NOT IN` it becomes `1=1`; a `NULL`
value under a bare key renders `IS` (under `NOT`, `IS NOT`) — an explicitly written
`=` operator is **not** remapped, so `'col =' => null` yields a broken `= NULL`.
`formatSet` turns a `'points+='` key into `col = col + ?`.

## Not implemented (so don't document as present)

- **No `@` escaping.** There is no `@`-prefix logic anywhere in the preprocessor.
- **No PHP-array → PostgreSQL-array-literal serialization.** Arrays only travel through
  the modes; on the read side a pg array column is merely detected as text (no
  parsing).
