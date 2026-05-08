---
inclusion: fileMatch
fileMatchPattern: ['**/Controllers/**', '**/Services/**', '**/Models/**', '**/Repositories/**']
---

# Architecture Patterns

## Controllers
- `final` class, no property mutations
- Method injection only (no constructor DI)
- Thin — delegate to Services

## Models
- `final` class

## Services
- `final readonly` class
- Business logic lives here

## Repositories
- Data access logic lives here
- Keeps Services/Controllers clean

## Routing
- Separate files per concern (`web.php`, `api.php`, `auth.php`, `crud.php`)
- Named routes + `route()` helper — never hardcode URLs

## Laravel Patterns
- Form Requests for all validation — never inline
- API Resources + versioning for APIs
- Sanctum + Policies for auth/authz
- Queues for anything slow (`ShouldQueue`)
- Telescope for dev debugging
- Filament for admin panels

## Module Rules
- Self-contained: own routes, controllers, models, services
- Loose coupling — clear interfaces between modules
- `module.json` declares deps + priority
- No module knows about another unless explicit dep declared

## DB
- MySQL/PostgreSQL/SQLite/Oracle compatible — use Eloquent abstractions
- No DB-specific SQL unless unavoidable
