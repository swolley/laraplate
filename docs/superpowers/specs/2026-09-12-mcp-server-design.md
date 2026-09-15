# MCP Server (external LLM access) — Design

**Status:** Draft (for review)
**Date:** 2026-09-12
**Author:** swolley + Claude
**Related:**
- stack `.cursor/rules/01-ecosystem.mdc` — the three HTTP surfaces
- `Modules/Core/routes/crud.php`, `graph.php`, `info.php`
- `Modules/Core/app/Services/Crud/CrudService.php`
- Memories: `locking-policy`, `optimistic-locking-was-broken`, `crud-facet-counters-wip`

## Goal

Expose a controlled, authenticated slice of Laraplate to **external** LLM clients
(Claude Desktop/Code, ChatGPT, custom agents) over the Model Context Protocol, as a
**fourth HTTP surface** next to `/admin`, `/app` and `/api/v1`.

## Direction: this is not the AI module

The two must not be confused; they point opposite ways.

| | Laraplate's role | Who initiates | Trust of input |
|---|---|---|---|
| `Modules/AI` | client / host — calls LLMs, may consume other MCP servers | Laraplate | we control the prompt |
| **MCP server (this spec)** | served resource — an external LLM calls us | the external client | **untrusted, model-driven** |

Consequence that drives every decision below: every exposed tool is invocable by a
model steered by input we do not control. The tool surface is a public API with
prompt-injection as a standing threat, not an internal convenience layer.

## Transport

Streamable HTTP (+SSE), not stdio. stdio would mean one local process per user with
no network auth, which defeats multi-tenant use. Practically: a route group at
`/mcp` speaking JSON-RPC 2.0 and answering `initialize`, `tools/list`, `tools/call`,
`resources/list`, `resources/read`.

**Open:** pin the exact MCP spec revision and pick the PHP/Laravel package before
implementation. Do not rely on recalled API shapes — the protocol moves fast.

## Gating

Same pattern already used for `/api/v1` (`crud_api` middleware in
`Modules/Core/app/Providers/RouteServiceProvider.php:127`, with the currently
commented `expose_crud_api` flag in `Modules/Core/config/config.php:47`).

- `MCP_ENABLED` — default **false**. When off the routes are **not registered at
  all**, not 403. No endpoint to fingerprint.
- `MCP_MODULES` — allowlist of modules whose entities are reachable (e.g. `cms,sao`).
  Lets you ship CMS access without exposing ERP.

## Authentication: Sanctum personal access tokens

**Decision: Sanctum PAT. Not JWT.**

Sanctum ships two independent modes. `/app` uses the SPA/cookie mode (session, CSRF,
`EnsureFrontendRequestsAreStateful`, restricted to `SANCTUM_STATEFUL_DOMAINS`). MCP
uses the **token** mode: `Bearer` header, no cookie, no session, no CSRF, lookup on
`personal_access_tokens`, with per-token `abilities`.

JWT was considered and dropped: it buys self-contained claims without a DB hit, and
costs a blocklist for revocation plus key rotation. Revocability matters more here,
because an MCP token lives inside a long-running external client. If third-party
clients ever need to self-onboard, the answer is OAuth 2.1 (the MCP spec's own
authorization story: protected-resource metadata + dynamic client registration), not
a static JWT — that is a later, separate decision.

### What is missing today

Token mode is **not** wired yet, despite Sanctum being installed:

- `config/auth.php` already declares the `api` guard with driver `sanctum`. ✅
- The `personal_access_tokens` migration already exists
  (`database/migrations/2025_03_06_214617_...`). ✅
- `HasApiTokens` is **absent** from `Modules/Core/app/Models/User.php`. ❌ — to add.
- A stateless route group is needed. ❌ — to add.

### Trap: the `auth` middleware group is cookie-laden

`bootstrap/app.php:71` appends `EncryptCookies`, `StartSession`, `VerifyCsrfToken`
and `EnsureFrontendRequestsAreStateful` to the `auth` group. Mounting `/mcp` on that
group silently makes it cookie-based again. MCP needs its **own** group: pure bearer,
`auth:sanctum`, nothing stateful, no session.

### Per-call user resolution

Resolve the token to a real `User` and `Auth::setUser()` **per HTTP request**, so
policies, Spatie permissions and `LocaleScope` apply unchanged. No credentials, no
`Auth::attempt`, no session.

Two traps:

1. **One MCP session spans many HTTP requests.** Never memoize the resolved user in a
   singleton or static — it would leak across tenants.
2. **Long-lived SSE streams can outlive token validity.** Re-check the token per
   JSON-RPC message, not once per connection.

### Two gates, not one

Authorization is the **intersection** of two independent checks:

- **Token ability** — may this *client* invoke this tool? (`mcp:read`, `mcp:write`, …)
- **User policy** — may this *user* see or touch this record? (existing policies)

The token is a delegation: it can only ever narrow what the user can already do.

### Ability granularity

Action abilities, intersected with module abilities:

| Ability | Grants |
|---------|--------|
| *(none required)* | `about`, `entities` — discovery only, already user-scoped |
| `mcp:read` | list, detail, tree, history, graph, approval state |
| `mcp:search` | `search` — separate because it hits ES and has a real cost profile |
| `mcp:write` | insert, update, delete |
| `mcp:lifecycle` | activate, inactivate, approve, disapprove, lock, unlock |
| `mcp:module:{cms\|erp\|mes\|sao\|core}` | restricts which modules the above reach |

`mcp:read` alone reaches nothing without at least one `mcp:module:*`. Sanctum's
`tokenCan` is an exact match, so the intersection needs a small helper.

## Tool surface

### Design choice: generic tools, not N tools per entity

Clients degrade badly past a few dozen tools, and the context cost of `tools/list` is
paid on every conversation. Laraplate already has a generic gateway keyed on
`{module}/{entity}` (`routes/crud.php`), so the tool surface is a thin projection of
it: ~15 parameterized tools instead of hundreds. `entities` is what replaces
per-entity tools — the model discovers the entity set at runtime.

### Discovery

| Tool | Backed by | Notes |
|------|-----------|-------|
| `entities` | `modules()` + `models()` | module/entity pairs the current user can read, each with fields and filterable columns. Load-bearing: without it the generic tools are unusable. |

### Enumeration: reuse `modules()` and `models()`

Both helpers live in `Modules/Core/app/Helpers/helpers.php`
(`modules()` at :68, `models()` at :375) and already do the job:

- `modules(onlyActive: true)` returns **enabled** modules only. `MCP_MODULES`
  therefore becomes an *intersection* on top of existing module enablement, not a
  parallel mechanism.
- `models($onlyActive, $onlyModule, $filter)` returns concrete `Model` class-strings,
  memoized, abstracts skipped, and accepts a **filter callable** — which is exactly
  where the MCP allowlist plugs in.

The decisive point: `DynamicEntity::tryResolveModel` (`Modules/Core/app/Models/DynamicEntity.php:78`)
**already calls `models()`** to resolve `{module}/{entity}`. So `entities` is a
projection of the same function the gateway resolves through, and the advertised tool
surface cannot drift from what the gateway actually accepts.

### Per-entity capabilities: reuse `ModelCapabilityScanner`

`Modules/Core/app/Seeding/ModelCapabilityScanner` already walks `models()` and reports
per-model traits (`hasVersions`, `hasSoftDeletes`, `hasLocks`, `hasOptimisticLocking`,
`hasTranslations`, `hasApprovals`). `entities` should surface these, because they say
**which tools even apply** to a given entity:

| Capability | Gates |
|------------|-------|
| `hasVersions` | `history` |
| `hasLocks` | `lock`, `unlock` |
| `hasApprovals` | `approve`, `disapprove`, `pending_approvals`, `latest_disapproval` |
| `hasOptimisticLocking` | whether `update` must carry `lock_version` |
| `hasTranslations` | whether writes are per-locale |

This is what makes a generic tool set usable: the model stops guessing which
operations exist on which entity, and stops burning turns on calls that can only
fail.

### Schema source: the model class, not the DB

Superseding the earlier open point. For concrete models the schema comes from the
class itself — `$fillable`, `$casts`, and the in-model validation rules — which are
curated by convention (memory `laraplate-model-standard`). DB introspection via
`DynamicEntityService::getInspectedTable` stays a fallback only, since it cannot see
casts, accessors or validation, and would leak physical columns that are not part of
the entity's contract.

### Hard requirement: `crud.dynamic_entities` must be off for MCP

`DynamicEntityService::resolve` falls back to a **DB-introspected** `DynamicEntity`
for any table with no concrete model, gated on `config('crud.dynamic_entities')`
(currently commented out in `Modules/Core/config/config.php:44`, so effectively
`false`). Two regimes follow:

- **off** — the reachable surface is exactly what `models()` enumerates. Safe.
- **on** — the gateway resolves *any table in the database*, including `users`,
  `personal_access_tokens`, `password_reset_tokens` and the Spatie permission tables.

The MCP layer must force the off behaviour for its own requests regardless of the
global config value. Relying on the default is not enough: someone enabling dynamic
entities for an unrelated reason would silently widen the MCP surface to the whole
schema.

### What the existing ACL already covers

Enforcement is **uniform and gateway-level**, so it does not depend on per-model
policies existing:

- `AuthorizationService::ensurePermission` derives the permission name from the
  model's **table** (plus connection) via `PermissionName`, the same source of truth
  the permission seeder uses. Every entity is therefore guarded by construction.
- `AuthorizationService::getAclFilters` resolves the user's row-level ACL and injects
  it into the query, so filters are ANDed in before the read runs.

Consequence: a positive, exhaustive per-model allowlist would mostly **duplicate the
permission model** and rot against it. Dropped.

### Where it stops: the superadmin bypass

Authorization protects against a *narrow* user. It cannot protect against the
*breadth of a legitimately broad* user, and it abstains entirely in three places:

| Site | Effect |
|------|--------|
| `Modules/Core/app/Providers/CoreServiceProvider.php:242` | `Gate::before` returns `true` for superadmin — every policy bypassed |
| `Modules/Core/app/Services/Authorization/AuthorizationService.php:318` | `passesPermission` returns `true` for superadmin |
| `Modules/Core/app/Services/Authorization/AuthorizationService.php:163` | `getAclFilters` returns `null` for superadmin — **no row-level filters at all** |

A superadmin token turns the entire `models()` set into reachable surface with no row
filtering. That includes `User` (email, password hash) and `PersonalAccessToken`
(token hashes — reading them escalates one MCP token into every other token). And a
superadmin token is precisely the first one anyone setting this up would mint.

This is exactly the gap the two-gate design exists to close: the user gate answers
*what may this person see*, the ability gate answers *what may this client do on their
behalf*, and it can only ever narrow. The token is a delegation, so it must not
inherit a bypass.

### Decision: a hard deny-list, not an allowlist

Small, coarse, and **not overridable by any role, superadmin included** — applied as
the `$filter` callable to `models()` and re-checked at resolution:

- `User`, and anything holding credentials or password-reset state
- `PersonalAccessToken` / Sanctum token models
- Spatie `Role` / `Permission` / pivot models
- `DynamicEntity`

Everything else stays governed by permissions and ACL as it is today. The deny-list
is not a second authorization model; it is the floor that holds when the ACL
deliberately abstains.

## Tool surface

### Design choice: generic tools, not N tools per entity

Clients degrade badly past a few dozen tools, and the context cost of `tools/list` is
paid on every conversation. Laraplate already has a generic gateway keyed on
`{module}/{entity}` (`routes/crud.php`), so the tool surface is a thin projection of
it: ~15 parameterized tools instead of hundreds. `entities` is what replaces
per-entity tools — the model discovers the entity set at runtime.

### Discovery

| Tool | Backed by | Notes |
|------|-----------|-------|
| `entities` | `modules()` + `models()` | module/entity pairs the current user can read, each with fields and filterable columns. Load-bearing: without it the generic tools are unusable. |

### Enumeration: reuse `modules()` and `models()`

Both helpers live in `Modules/Core/app/Helpers/helpers.php`
(`modules()` at :68, `models()` at :375) and already do the job:

- `modules(onlyActive: true)` returns **enabled** modules only. `MCP_MODULES`
  therefore becomes an *intersection* on top of existing module enablement, not a
  parallel mechanism.
- `models($onlyActive, $onlyModule, $filter)` returns concrete `Model` class-strings,
  memoized, abstracts skipped, and accepts a **filter callable** — which is exactly
  where the MCP allowlist plugs in.

The decisive point: `DynamicEntity::tryResolveModel` (`Modules/Core/app/Models/DynamicEntity.php:78`)
**already calls `models()`** to resolve `{module}/{entity}`. So `entities` is a
projection of the same function the gateway resolves through, and the advertised tool
surface cannot drift from what the gateway actually accepts.

### Per-entity capabilities: reuse `ModelCapabilityScanner`

`Modules/Core/app/Seeding/ModelCapabilityScanner` already walks `models()` and reports
per-model traits (`hasVersions`, `hasSoftDeletes`, `hasLocks`, `hasOptimisticLocking`,
`hasTranslations`, `hasApprovals`). `entities` should surface these, because they say
**which tools even apply** to a given entity:

| Capability | Gates |
|------------|-------|
| `hasVersions` | `history` |
| `hasLocks` | `lock`, `unlock` |
| `hasApprovals` | `approve`, `disapprove`, `pending_approvals`, `latest_disapproval` |
| `hasOptimisticLocking` | whether `update` must carry `lock_version` |
| `hasTranslations` | whether writes are per-locale |

This is what makes a generic tool set usable: the model stops guessing which
operations exist on which entity, and stops burning turns on calls that can only
fail.

### Schema source: the model class, not the DB

Superseding the earlier open point. For concrete models the schema comes from the
class itself — `$fillable`, `$casts`, and the in-model validation rules — which are
curated by convention (memory `laraplate-model-standard`). DB introspection via
`DynamicEntityService::getInspectedTable` stays a fallback only, since it cannot see
casts, accessors or validation, and would leak physical columns that are not part of
the entity's contract.

### Hard requirement: `crud.dynamic_entities` must be off for MCP

`DynamicEntityService::resolve` falls back to a **DB-introspected** `DynamicEntity`
for any table with no concrete model, gated on `config('crud.dynamic_entities')`
(currently commented out in `Modules/Core/config/config.php:44`, so effectively
`false`). Two regimes follow:

- **off** — the reachable surface is exactly what `models()` enumerates. Safe.
- **on** — the gateway resolves *any table in the database*, including `users`,
  `personal_access_tokens`, `password_reset_tokens` and the Spatie permission tables.

The MCP layer must force the off behaviour for its own requests regardless of the
global config value. Relying on the default is not enough: someone enabling dynamic
entities for an unrelated reason would silently widen the MCP surface to the whole
schema.

### Still open: the exposability predicate

`models()` returns *every* concrete model, `User`, `PersonalAccessToken`, Spatie
`Role`/`Permission` and `DynamicEntity` included. An allowlist is required, passed as
the `$filter` callable, **deny by default**.

"Has a policy" does not work as the predicate: policies are per-module and generic
(`ERPModelPolicy`, `MesModelPolicy`, `SaoModelPolicy`), so they do not discriminate
between models within a module. The choice is therefore between an explicit config
allowlist and a marker interface on exposable models. Undecided.

### Read — `mcp:read`

| Tool | `CrudService` method |
|------|----------------------|
| `list` | `list` |
| `detail` | `detail` |
| `tree` | `tree` |
| `history` | `history` |
| `pending_approvals` | `pendingApprovals` |
| `latest_disapproval` | `latestDisapproval` |
| `graph_expand`, `graph_stats` | `GraphController::expand` / `stats` |

### Search — `mcp:search`

| Tool | Backed by |
|------|-----------|
| `search` | `CrudService::search` (ensemble / semantic) |

Highest-value tool of the set: it is the one an external LLM actually wants, and the
one that makes Laraplate content usable as a knowledge source.

### Write — `mcp:write`

| Tool | `CrudService` method |
|------|----------------------|
| `insert` | `insert` |
| `update` | `update` |
| `delete` | `delete` |

**Optimistic locking constraint.** Writes go through the same locking path as `/app`,
so `update` requires a current `lock_version` or it throws
`MissingLockVersionException` / `StaleModelLockingException`. The tool description
must state that `update` is a two-step dance: `detail` first to read
`lock_version`, then `update` carrying it. A model that guesses will simply fail,
which is the correct outcome. See memory `locking-policy`.

### Lifecycle — `mcp:lifecycle`

`activate`, `inactivate`, `approve`, `disapprove`, `lock`, `unlock`.

Kept out of `mcp:write` deliberately: approving content is a governance act, not an
edit, and you will want to grant editing without granting approval.

### Resources (not tools)

Static-ish reads belong in `resources/*`, where they cost nothing per turn:

- `about` — `SettingController::siteInfo`
- `translations` — `SettingController::getTranslations`
- entity schemas — the same data `entities` returns, addressable per entity

## Explicitly excluded

Not oversights. Each would break the security model.

| Excluded | Why |
|----------|-----|
| `auth.impersonate` / `leaveImpersonate` | Lets the model become another user. Destroys the entire per-user authorization design in one call. **Never expose.** |
| Any Artisan passthrough (`cms:import`, reindex, `migrate`, `perf:bench`) | A `run_artisan(cmd)` tool is arbitrary remote code execution driven by untrusted text. If a specific command is ever needed, wrap it as a named tool with fixed arguments, queued — never a passthrough. |
| `CrudService::doActivateOperation` raw / `domainAction` | `DomainActionDispatcher` resolves actions dynamically, so the reachable surface is not knowable at review time. |
| `clearModelCache` | Operational lever with no read value; a plausible denial-of-service knob. |
| `dev.php` (`phpinfo`, swagger merge) | Environment disclosure. |
| Media upload / `imports.run` | File ingestion from a model-driven client. Separate threat model, separate design if ever wanted. |
| `facets` (`facetCounts` / `facetValues`) | Facet counts exist to render a filter sidebar. A model cannot render one, and the two questions it would actually ask are already answered elsewhere: *what can I filter on* by `entities`, *is this query too broad* by `CrudMeta::totalRecords` on a small-page `list`. Per-value cardinality is not decision-relevant to a model. Also still WIP (funnel → Crud), so exposing it would pin a moving target. |
| `freshness` | Same shape as `facets`: it answers "has the list I am displaying gone stale". An external LLM holds no list and has no polling loop. |
| Notifications | Per-user UI state; no value to an external LLM. |

## Open points

1. MCP spec revision + PHP package choice (blocks implementation).
2. Where the deny-list is enforced so a superadmin cannot lift it — config is not
   enough if config is editable from the backoffice.
3. Rate limiting per token: an LLM loops, and `search` is the expensive tool.
4. Audit: every `tools/call` should be attributable to (token, user, tool, args).
   Check whether the existing audit layer can absorb this or needs a new channel.
5. Whether `mcp:module:*` should go finer, down to per-entity.
