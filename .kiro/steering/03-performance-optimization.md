---
inclusion: fileMatch
fileMatchPattern: ['**/Models/**', '**/Services/**', '**/Jobs/**', '**/database/migrations/**']
---

# Performance

## Caching
- Cache values that are read often and change rarely
- Drivers: `database`, `file`, `array` only — no driver-specific features
- Static class properties to avoid repeated queries within same instance

## Queues
- Slow ops go to queue — always. Embeddings, media, emails, AI tasks.
- `ShouldQueue` interface on jobs

## DB
- Eloquent ORM first, query builder for complex ops, `DB::` never
- Eager load to prevent N+1
- Index columns used in WHERE/ORDER/JOIN
- Transactions for multi-step writes
- `$query->latest()->limit(10)` — native in Laravel 12, no package needed
- Cross-DB compatible queries only

## Code
- Efficient algorithms, mind time/space complexity
- Use Laravel helpers and built-ins
