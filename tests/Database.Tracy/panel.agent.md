## Database queries (foo)

4 queries, time %a% ms

```sql
-- %a% ms
::beginTransaction;

-- %a% ms, 0 rows
SELECT 1;

-- %a% ms
::commit;

-- ERROR: %A%
SELECT;
```
