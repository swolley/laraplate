---
inclusion: always
---

# Product: Laraplate

Modular Laravel boilerplate for scalable, production-ready apps. Auth, admin panel, CMS, CRM out of the box.

## Modules

| Module | Priority | Status | Description |
|--------|----------|--------|-------------|
| Core | 0 | Stable | Auth, permissions, users, optimistic locking, dynamic entities, CRUD API |
| Cms | 1 | Stable | Content, media library, geocoding, analytics |
| AI | — | Stable | Embeddings, semantic search, LLM chat, translation, action requests |
| ERP | 999 | WIP | Accounting, multi-company, fiscal calendar, journal; repo: `laraplate-erp` |
| MES | — | Planned | Production orders, work centres, scheduling, traceability |
| Ecommerce | — | Planned | Catalogue, cart, checkout, orders, payments |

Modules toggled via `modules_statuses.json`. Each is an independent git submodule.

## Module Dependency Rules
- **Modules isolated by default.** No cross-module knowledge unless explicit dep.
- Core: universal dep — provides auth, permissions, helpers. Knows nothing about other modules.
- All other modules: independent unless deliberate dep established.
- Deps must be explicit (in `module.json` + `composer.json`), minimal, documented.
- Depend on contracts/interfaces, not concrete implementations.

| Module | Depends on | Notes |
|--------|-----------|-------|
| Cms | Core | — |
| AI | Core | — |
| ERP | Core | path `Modules/ERP`, namespace `Modules\ERP` |
| MES | Core, maybe ERP | not decided yet |
| Ecommerce | Core, maybe ERP | not decided yet |

## Target Users
Devs building Laravel SaaS or enterprise apps who want an opinionated, well-structured starting point.
