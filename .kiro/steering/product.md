# Product: Laraplate

Laraplate is a modular Laravel application skeleton (boilerplate) designed for building scalable, production-ready web applications. It provides a solid foundation with authentication, admin panel, content management, and CRM capabilities out of the box.

## Purpose
- Accelerate new Laravel project setup with pre-built modules and conventions
- Enable rapid admin panel development via Filament
- Support enterprise-grade applications with proper testing, CI/CD, and code quality tooling

## Modules

| Module | Priority | Status | Description |
|--------|----------|--------|-------------|
| **Core** | 0 (first) | Stable | Authentication, permissions, user management, optimistic locking, dynamic entities, CRUD API |
| **Cms** | 1 | Stable | Content management, media library, geocoding, author/category management, analytics |
| **AI** | — | Stable | Embeddings, semantic search, LLM chat, translation, action requests |
| **ERP** | 999 | In progress | Accounting and operations — multi-company, fiscal calendar, journal, commercial scaffolding; Filament admin slice |
| **MES** | — | Planned | Manufacturing Execution System — production orders, work centres, scheduling, traceability |
| **Ecommerce** | — | Planned | Online store — catalogue, cart, checkout, orders, payments |

Modules are optional and toggled via `modules_statuses.json`. Each module is an independent git submodule with its own versioning and quality toolchain.

## Module Dependency Rules

**Core principle: modules are isolated by default.** No module should know about the existence of another module unless an explicit dependency is declared and justified.

- **Core** is the only universal dependency — it provides shared infrastructure (auth, permissions, helpers, services) consumed by all other modules, but Core itself has zero knowledge of any other module.
- **All other modules** are independent of each other unless a deliberate dependency is established.
- Inter-module dependencies must be **explicit** (declared in `module.json` and `composer.json`), **minimal**, and **documented with a reason**.
- When a module depends on another, it depends on its **contracts/interfaces**, not on concrete implementations.

### Known / Planned Dependencies
| Module | Depends on | Status | Notes |
|--------|-----------|--------|-------|
| Cms | Core | Stable | — |
| AI | Core | Stable | — |
| ERP | Core | In progress | Repository: `laraplate-erp`; path `Modules/ERP`; namespace `Modules\ERP` |
| MES | Core, possibly ERP | Planned | Dependency on ERP not yet decided |
| Ecommerce | Core, possibly ERP | Planned | Dependency on ERP not yet decided |

## Module Roadmap Notes
- **ERP** (formerly “Business”): submodule `laraplate-erp`, folder `Modules/ERP`. Cross-module docs should use **ERP** for this module.
- **MES** and **Ecommerce**: under evaluation. May extend ERP, but this dependency is not yet decided — do not assume it. When created, they follow the same module conventions (own `composer.json`, `module.json`, priority, test suite, quality toolchain).

## Target Users
Developers building Laravel-based SaaS or enterprise applications who want a well-structured, opinionated starting point rather than a blank slate.
