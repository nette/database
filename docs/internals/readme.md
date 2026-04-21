# Database internals

How `nette/database` works underneath, for agents editing it. Two independent
worlds — the low-level core and the Explorer (ActiveRow) layer — so split by seam:

- **[explorer.md](explorer.md)** — the flagship: `Selection`/`ActiveRow` lazy
  execution, `accessColumn`, SELECT narrowing, N+1 batching (`refCache`), lazy
  insert. The subtlest, trap-richest code.
- **[sql-preprocessor.md](sql-preprocessor.md)** — parameter substitution and the
  context-detected array modes.
- **[connection-drivers.md](connection-drivers.md)** — connection/execution, the
  `Driver` dialect abstraction, and exception mapping.
- **[results-and-types.md](results-and-types.md)** — `ResultSet` and the DB→PHP
  value normalization.
- **[transactions.md](transactions.md)** — `transaction()`, nesting, and retries.
