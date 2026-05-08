---
inclusion: always
---

# Master Rules

## Always Active
- `laravel-boost.md` — Laravel ecosystem rules
- `06-coding-principles.md` — coding principles
- `spec-task-execution-rules.md` — spec task rules
- `product.md` — modules & dependencies
- `structure.md` — directory layout
- `tech.md` — stack, versions, commands

## Contextual (auto-loaded by file pattern)
- `01-php-laravel-standards.md` — PHP files
- `02-architecture-patterns.md` — Controllers, Services, Models
- `03-performance-optimization.md` — Models, Jobs, migrations
- `04-error-handling-security.md` — Controllers, Middleware, Exceptions
- `05-testing-development.md` — test files
- `07-laraplate-specific.md` — Modules/**

## Non-negotiable
- Chat: Italian. Code/comments: English.
- Run `vendor/bin/pint --dirty` before done.
- Every change needs a test.
- Never remove `final`, `readonly`, `static`.
