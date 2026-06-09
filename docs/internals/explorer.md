# Explorer internals (Selection & ActiveRow)

How the Explorer layer (`Selection`, `ActiveRow`, `GroupedSelection`, `SqlBuilder`)
really works inside — data flow, caching, the two optimizations, and above all the
**traps and invariants**. This is the reference for the current code.

## Two layers

- **Core** (`src/Database/`): `Connection` (PDO wrapper), `SqlPreprocessor`,
  `ResultSet` (iterator + type normalization), `Driver` (per-engine), `Structure`/
  reflection.
- **Explorer** (`src/Database/Table/`): `Selection` (lazy fluent builder),
  `ActiveRow` (a row), `GroupedSelection` (has-many), `SqlBuilder`.

Explorer is NotORM-inspired, and its value is two optimizations:

1. **N+1 prevention** — related rows are fetched in batches (`WHERE id IN (...)`),
   a constant number of queries.
2. **SELECT narrowing** — after an initial `SELECT *` it learns which columns are
   actually read and next time selects only those. **Works only with a cache.**

## Selection: lazy execution

`Selection` is a **lazy** fluent builder — `where`/`order`/`select`/… only push into
`SqlBuilder` and return `$this`. **Nothing executes until you touch data.**

Key fields: `$rows` (`?array` of `[signature => ActiveRow]`; `null` = not executed),
`$data` (iterable form — for a plain Selection a COW copy of `$rows`, but for a
`GroupedSelection` **bound by reference** into the shared refCache — hence sharing),
`$cache` (without it narrowing is off), `$accessedColumns` (columns read *now*),
`$previousAccessedColumns` (learned last time, from cache — **per-instance**), the
two cache keys, `$refCache`/`$globalRefCache`, `$observeCache` (which instance owns
saving learned columns), and `$dataRefreshed` (a re-query happened; signal to
`ActiveRow` to pull fresh data).

**`execute()`**: idempotent if `$rows !== null`; runs `query(getSql())` where
`getSql()` builds the SELECT column list from `previousAccessedColumns`; **on a
`DriverException` retries with `SELECT *`**, but only when the SELECT was actually
narrowed (non-empty `previousAccessedColumns`, no explicit `select()`) — a narrowed
SELECT can reference a column dropped by a schema change; builds an
`ActiveRow` per PDO row keyed by **signature** (PK values joined by `|`, or numeric
when PK-less); then marks the primary column(s) as accessed.

`get($key)` = `clone $this` then `wherePrimary($key)->fetch()` — it **clones** so as
not to dirty the original.

## ActiveRow: storage & access

**All data lives in one private `$data` array** (`[column => value]`); no column is a
separate property. A subclass may declare typed properties (`public int $id`) for
IDE/PHPStan, but the constructor **`unset()`s them**, so reads fall into `__get` and
flow through `$data`. That is the only way to track which columns are actually read
(SELECT narrowing) — and the reflectable real-property type is also what enables
**enum conversion** (a `@property` annotation would not).

- `__get($key)`: maps property→column via `EntityMapping` → `accessColumn` → returns
  `$data[$column]` (with `BackedEnum` conversion by the declared type); if the column
  is absent it tries a **relation**, else throws `MemberAccessException`. `__set` is
  read-only (throws). `toArray()` forces all columns via `accessColumn(null)`.
- `getPrimary()`/`getSignature()` read **only `$data[$primary]`, with no query** — so
  row-cache writes can call them without triggering a fetch.

## `accessColumn`: the single shared seam

**Every** row-data access flows through `ActiveRow::accessColumn(?string $key)` —
`__get`, `__isset`, `toArray()` (via `accessColumn(null)`), and forward references
(`getReferencedTable` calls `$row->accessColumn($fkColumn)`). One hook covers every
path, which is why SELECT narrowing hangs off it. It: (1) delegates to
`$this->table->accessColumn($key)` and, if the Selection reports a re-query, pulls
fresh data from `$this->table[signature]->data`, (2) returns whether the column
exists.

## Cache keys

- `getGeneralCacheKey()` hashes `[table, conditions, debug_backtrace]` (plus a fixed
  `Selection::class` constant — `self::class`, identical even for a `GroupedSelection`). It
  **deliberately omits limit and select**, so it is stable across limited variants of
  the same query (used by the limit re-query). **The trap:** it includes
  `debug_backtrace`, so the cache key depends on the **call site** — two call sites
  with identical conditions get different keys and different learned columns. This is
  intentional (different sites read different columns) but surprising, and a
  refactor that moves a call site (e.g. wraps `table()` in a factory) silently
  "forgets" the learned columns.
- `getSpecificCacheKey()` hashes the **whole built select query** via
  `SqlBuilder::getSelectQueryHash()` — conditions, order, aliases, limit/offset,
  parameters, and the column list — so `SELECT id,name` vs `SELECT *` land in
  different cache slots.

## SELECT narrowing

State: `$accessedColumns` (`array<string,bool>|false|null`; `false` = "all", forced
e.g. by `toArray()`), `$previousAccessedColumns` (learned last time). Off entirely
when `$cache === null` or when an explicit `select(...)` (even `select('*')`) is set.

Flow: `accessColumn($key)` records the access; a **re-query** fires only when **all**
hold — the access wants a select column, `previous` is non-empty (something was
learned; the very first query with nothing learned just runs `SELECT *`), the key is
**not** in `previous`, and there is no explicit `select()`. `saveCacheState()` (from
`__destruct` / `emptyResultSet`) merges accessed into the cache, but only when
`observeCache === $this` — so only the owning instance saves.

**The re-query** on an unlearned column: **without a limit**, `emptyResultSet`, set
`previous = []`, `execute()` again (now `SELECT *`); **with a limit**, it must not
re-run the original limited query (different rows), so it collects the already-loaded
rows' PKs, clones the SqlBuilder, **drops the limit**, `wherePrimary(collected PKs)`,
`execute()` (a `SELECT *` of exactly those rows), then restores the SqlBuilder — the
`generalCacheKey` is preserved throughout. In both branches, when the trigger was
`accessColumn(null)` (e.g. `toArray()` mid-iteration), the iterator position is
captured and restored after the re-query. It sets `dataRefreshed = true` so
`ActiveRow::accessColumn` pulls fresh data. This mechanism is **intrinsically inside
Selection** (it manipulates the SqlBuilder, rows, execute).

**Shared vs per-instance — the key asymmetry.** `accessedColumns` is **shared** across
grouped clones (for the N+1 batch), bound by reference into
`&$referencing[$hash]['accessed']`. `previousAccessedColumns` is **not** shared. They
cannot be trivially unified into one shared object: the shared `accessed` slot is
selected by `hash` (`specificCacheKey`), which **depends on `previous`** — so if
`previous` lived inside the hash-selected object you'd get a cycle (object → need hash
→ need previous → need object). `previous` therefore must exist earlier and
independently, as a per-instance array. (A WeakMap does not help — the blocker is a
computation-order dependency, not object identity.)

`Selection::__clone` clones only the SqlBuilder; `accessedColumns` is an **array** and
so is copied by value on clone (a `get()` clone is automatically independent).

## N+1 prevention & refCache

A Selection holds `$refCache` as a reference into the **root** selection's
`globalRefCache[$refPath]` (a GroupedSelection climbs up to build a `book.author.`
path), so relations are shared across the whole chain. Keys: `['referenced']` (forward
belongs-to batches), `['referencing']` (backward has-many batches + shared `accessed`,
`rows`, `data`), `['referencingPrototype']` (grouped prototypes).

- **Forward (`$book->author`):** collect `author_id` from **all** parent rows, build
  **one** `SELECT * FROM author WHERE id IN (...)`, index by FK — constant queries.
- **Backward (`$author->related('book')`):** `getReferencingTable` returns a **prototype**
  `GroupedSelection` (cloned per access); its `execute` binds through `loadRefCache` to
  the shared slot (`observeCache`, `rows`, `data`, **and** `accessedColumns` — all by
  reference). The first clone queries **all** children of **all** parents at once and
  buckets them by group value; later clones find the data cached.

`loadRefCache` binds `$this->accessedColumns = &$referencing[$hash]['accessed']` (a
by-ref array), so a mutation from one clone is seen by all clones of the same relation
(they share the learned columns of the whole N+1 batch).

## insert() & insertMany()

`Selection::insert(iterable)`: a **single associative row** returns a **lazy
`ActiveRow`** knowing only its PK (or `null` when the full PK can't be determined); a
**list or a Selection** is routed to `insertMany()`, and doing that through `insert()`
is **deprecated**.

Lazy is the **only** mode — used whenever the **full PK is known** (single or
composite; an autoincrement part is filled from `getInsertId`). There is no
eager-fetch fallback: a PK-less table or an incomplete PK returns `null`
(+ `clearReferencingCache()`). A returned row is also registered into `rows`/`data`
if the Selection was already executed.

`insertMany(iterable)` owns the bulk logic and always returns an int. Three details
are not obvious from the signature:

- an **empty list returns 0 without touching the database**, whereas `insert([])`
  inserts one row of database defaults (`?values` with an empty array) — the two are
  deliberately not equivalent;
- rows are materialized by `Helpers::materializeRows()`, which drains a Traversable
  **by position**, so nothing is lost when a generator yields rows under colliding
  keys (`yield from` restarts at 0), and `Helpers::isRowList()` then accepts gaps left
  by e.g. `array_filter()` — a plain `array_is_list()` would misread such rows as a
  single associative row;
- a single associative row is **rejected up front** rather than inserted, so the
  mistake surfaces before the write.

`GroupedSelection` overrides both: `insert()` assigns the grouping column to a single
row, `insertMany()` to every row of the list — on a **clone** of each `Row`, never on
the caller's object.

## update() / delete()

`ActiveRow::update($data)` runs UPDATE via `createSelectionInstance()->wherePrimary()`
then **re-fetches `SELECT *`** into `$this->data` (returns whether it changed).
`ActiveRow::delete()` runs DELETE and removes the row from `table[$signature]`.

## The invariants that matter most

1. **Row identity.** A returned row **must** be the same object that is in its
   `table->rows` (else update + relations = stale).
2. **`debug_backtrace` in the cache key.** `generalCacheKey` depends on the call site;
   a refactor that moves the call can change keys and "forget" learned columns — an
   unsuspected source of "why is it suddenly `SELECT *`".
3. **Shared vs per-instance.** `accessedColumns` shared by reference,
   `previousAccessedColumns` per-instance; not unifiable due to the previous→hash→slot
   cycle above.
4. **`previousAccessedColumns` is transiently mutated** (`false` on retry, `[]` before
   re-query, `null` after save) — which is exactly why it must **not** be shared across
   grouped clones (it would corrupt another clone's hash computation).
5. **`select('*')` disables the optimization** — which is why `insert()` re-fetches
   with `select('*')` (to avoid a cache-narrowed row and to avoid dirtying the cache).
6. **`offsetSet` on Selection writes only `rows`, not `data`** (asymmetry vs
   `offsetUnset`) — latent fragility.
7. **refCache invalidation.** All "no identifiable row" insert branches call
   `clearReferencingCache()`; a successful insert does not (it adds the row to `rows`
   in place).

## Three flows worth tracing

**A) N+1 prevention (forward reference):**

```php
$books = $explorer->table('book');          // lazy, nothing runs
foreach ($books as $book) {                 // execute(): SELECT * FROM book       (query 1)
    echo $book->author->name;               // $book->author → getReferencedTable
}
```

On the **first** `$book->author`, `getReferencedTable` walks **all** `$books->rows`,
collects the `author_id`s and runs **one** `SELECT * FROM author WHERE id IN (...)`
(query 2), indexed by id. Later iterations just hit the cached selection — **2 queries
total** regardless of row count. (Backward `related()` works analogously via
`GroupedSelection`.)

**B) insert():**

```php
$row = $explorer->table('book')->insert([   // INSERT ...                          (query 1)
    'title' => 'Foo', 'author_id' => 1,
]);                                         // then SELECT * WHERE id = ?          (query 2)
                                            //   the PK comes from getInsertId()
$id = $row->id;                             // the row is already complete → no further query
echo $row->title;
```

**C) SELECT narrowing across requests (with cache):**

```php
// request 1 (cache empty for this call site):
$book = $explorer->table('book')->get(1);   // SELECT * FROM book WHERE id = 1
echo $book->title;                          // accessColumn('title') records the access
                                            // at the end: saveCacheState stores {id, title} under generalCacheKey

// request 2 (cache already knows {id, title}):
$book = $explorer->table('book')->get(1);   // SELECT id, title FROM book WHERE id = 1  (narrowed!)
echo $book->author->name;                   // needs author_id, missing from the narrowed {id, title}
                                            //   → re-query: SELECT * WHERE id = 1
                                            //   getReferencedTable calls accessColumn('author_id') → marks it
                                            //   at the end: the cache grows to {id, title, author_id}
```

The learning is keyed by `generalCacheKey` (including `debug_backtrace`), so **the same
call site** gradually learns its own column set; a different call site learns separately.

## Refactoring boundary

The narrowing optimization is architecturally fused into Selection: the re-query
mechanism touches SqlBuilder/rows/execute, and the cache-persistence policy needs the
per-instance `previous` + cache + generalKey (the cycle blocker). Realistically only
the shared `accessed` state and its write operations are cleanly extractable.
