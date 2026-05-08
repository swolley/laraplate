---
inclusion: always
---

# Spec Task Execution Rules

## Working Directory
- Root: `/srv/http/laraplate`
- Never append home dir paths
- Run tests: `php artisan test --compact Modules/{Name}/tests/...` from root

## Class Modifiers — NEVER Remove
- `final` — all classes: controllers, models, services, jobs, listeners, observers, commands
- `readonly` — all service classes
- `static` — all static-safe closures/functions
- Need to mock? Use interfaces or Mockery — never remove `final`

## Tests
- Minimum tests needed — use `--filter` or specific file path
- `php artisan test --compact`
- No `--watch`

## Code Style
- `vendor/bin/pint --dirty` after changes, before task complete
- No new deps without user approval

## Spec Compliance
- Implementation must satisfy task requirements
- Don't touch files unrelated to current task
- Don't remove/alter existing tests unless task requires it
