---
inclusion: fileMatch
fileMatchPattern: ['**/Modules/**', '**/module.json', '**/module.php']
---

# Laraplate — Module Rules

## Stack
- Laravel 12 + PHP 8.5, Filament 5, nwidart/laravel-modules
- DB: MySQL/PostgreSQL/SQLite/Oracle — Eloquent only
- Cache: `database`/`file`/`array` only
- Queues: use heavily (embeddings, media, emails, AI)

## Modules

| Module | Priority | Status | Notes |
|--------|----------|--------|-------|
| Core | 0 | Stable | Auth, permissions, users, CRUD API, dynamic entities |
| Cms | 1 | Stable | Content, media, geocoding, analytics |
| AI | — | Stable | Embeddings, LLM chat, translation, action requests |
| ERP | 999 | WIP | Accounting, multi-company, journal; repo: `laraplate-erp` |
| MES | — | Planned | Production orders, scheduling |
| Ecommerce | — | Planned | Catalogue, cart, orders, payments |

## Module Rules
- Naming: PascalCase (`Core`, `Cms`, `AI`, `ERP`)
- No module knows about another unless explicit dep in `module.json` + `composer.json`
- Deps on contracts/interfaces, not concrete classes
- Priority field controls load order

## Module Structure
```
Modules/{Name}/
├── app/
│   ├── Actions/, Casts/, Console/, Contracts/, Events/
│   ├── Filament/Resources/
│   ├── Http/Controllers/, Http/Requests/
│   ├── Jobs/, Listeners/, Models/, Observers/
│   ├── Providers/{Name}ServiceProvider.php
│   ├── Rules/, Services/
│   └── Helpers/helpers.php
├── config/config.php
├── database/factories/, migrations/, seeders/
├── lang/, resources/views/, routes/web.php, routes/api.php
├── tests/Feature/, tests/Unit/, tests/Pest.php
├── module.json, composer.json, phpstan.neon, pint.json, rector.php
```

## Embeddings
- Always async via `GenerateEmbeddingsJob` — never sync in request
- Store in `ModelEmbedding` (polymorphic)
- `prepareTextForEmbedding()` to extract text
- Rate-limit embedding queue

## Commands
- `composer dev` — server + queue + logs + vite
- `composer lint` — IDE helper + quality tools
- `composer test` — full pipeline
- `composer refactor` — Rector

## API
- API Resources + versioning always
- Sanctum for auth
