# MCP Server (external LLM access) — Design

**Status:** Draft (for review)
**Date:** 2026-09-12 (revised 2026-09-16)
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

## Phasing: phase 1 is read-only

**Decided.** Phase 1 ships the read and search tools. No write tool, no lifecycle tool, no
`mcp:write` token is issued or honoured.

This is a sequencing decision, not a verdict on writes. **Writes will have to be opened**:
an MCP server that can only read is half a product, and the point of binding a session to a
specific user is precisely that the user's own actions become reachable. What is missing is
not the will but a home for the approval mechanism, and shipping writes before that home
exists would mean shipping the most exposed surface in the system with the least protection
on it. See *Write approval* for what has to be solved and the order to solve it in.

Two consequences worth stating so phase 1 does not quietly become permanent:

- The write and lifecycle tool sets below stay **designed and specified**, marked as phase 2.
  They are not struck out, and the next person does not redesign them.
- The `mcp:write` ability stays in the token model from the start. Minting must be able to
  refuse it in phase 1 while the concept, the naming and the two-gate intersection remain in
  place, so that opening writes is a matter of lifting a refusal rather than retrofitting a
  permission axis into a shipped protocol.

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

No per-module switch. The caller is a real application user, so which modules and
entities it reaches is already decided by its roles, permissions and ACL.

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

### Token lifecycle: who mints one

**The owner mints their own token.** A token is a delegation of the holder's own
rights, so an administrator never mints one for another person: that would create a
credential its owner does not know exists, while the audit trail attributes every
action to them.

The administrator governs three other things instead: the permission to create MCP
tokens at all, a list of existing tokens to inspect and revoke (never their values),
and the `MCP_ENABLED` kill switch.

**Service accounts are the exception.** The dedicated non-superadmin account MCP runs
under exists only for the integration, so an administrator creates it and mints its
token.

Rules at creation:

| Rule | Why |
|------|-----|
| Read-only by default | The safe choice should be the default one. **In phase 1 read-only is also the only option**: minting refuses `mcp:write` outright (see *Phasing*) |
| Ability fixed at creation, never edited | A token that changes powers while it sits in an external client is impossible to reason about. More powers means a new token and the old one revoked |
| Finite expiry required | The column is nullable, i.e. "never expires". An MCP token sits in a client's config file for months |
| Name required | It says what you are revoking. Logs carry the token id, which on its own means nothing |
| Value shown once | Only the hash is stored |

**No schema change is needed.** `personal_access_tokens` already carries `name`,
`abilities`, `last_used_at` and `expires_at`, and Sanctum checks a token's own
`expires_at` independently of the global `sanctum.expiration`
(`vendor/laravel/sanctum/src/Guard.php:129`), so a per-token deadline works even
though the global expiry is off. `last_used_at` is tracked by default and should be
surfaced in the list, since it is what makes a forgotten token visible.

What Sanctum does **not** record is the caller's IP and client. That belongs to the
audit log line, not to the token row.

### Surfaces, given the UI is in construction

Nothing exists today: there is no token management in Filament and none in `/app`.
For v1, an Artisan command creates and revokes, and a read-only Filament page lists
tokens with their last use and a revoke action. Self-service moves into `/app` when
the Vue frontend reaches it.

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

- **Token ability** — may this *client* read, or also write?
- **User authorization** — may this *user* see or touch this record? Existing
  permissions and ACL, unchanged.

The token is a delegation: it can only ever narrow what the user can already do.

### Ability granularity: read or write, nothing finer

| Ability | Grants |
|---------|--------|
| *(none required)* | `about`, `entities` — discovery only, already user-scoped |
| `mcp:read` | every read tool, `search` included |
| `mcp:write` | every write and lifecycle tool; implies nothing about reads, so a writing token carries both. **Phase 2: not minted and not honoured in phase 1** (see *Phasing*), but part of the token model from the start |

Everything finer belongs to permissions and ACL. Per-module or per-entity abilities
were considered and dropped: they would duplicate the permission model on the token
and drift from it. The only axis the token adds is one permissions cannot express:
a user allowed to write can hand a client a **read-only** token.

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

- `modules(onlyActive: true)` returns **enabled** modules only.
- `models($onlyActive, $onlyModule, $filter)` returns concrete `Model` class-strings,
  memoized, abstracts skipped, and accepts a **filter callable** — which is where
  `entities` drops the models the current user holds no read permission on.

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

For concrete models the schema comes from the
class itself — `$fillable`, `$casts`, and the in-model validation rules — which are
curated by convention (memory `laraplate-model-standard`). DB introspection via
`DynamicEntityService::getInspectedTable` stays a fallback only, since it cannot see
casts, accessors or validation, and would leak physical columns that are not part of
the entity's contract.

### Note: `crud.dynamic_entities`

`DynamicEntityService::resolve` falls back to a **DB-introspected** `DynamicEntity`
for any table with no concrete model, gated on `config('crud.dynamic_entities')`
(currently commented out in `Modules/Core/config/config.php:44`, so effectively
`false`). When on, the gateway resolves *any table*, `users` and
`personal_access_tokens` included.

This needs no MCP-specific guard. Permissions are keyed on the table name, so a
non-superadmin caller still reaches only tables its roles were granted, and
superadmin tokens are refused (below). `entities` should still advertise concrete
models only, so the model is never invited to probe raw tables.

### What the existing ACL already covers

Enforcement is **uniform and gateway-level**, so it does not depend on per-model
policies existing:

- `AuthorizationService::ensurePermission` derives the permission name from the
  model's **table** (plus connection) via `PermissionName`, the same source of truth
  the permission seeder uses. Every entity is therefore guarded by construction.
- `AuthorizationService::getAclFilters` resolves the user's row-level ACL and injects
  it into the query, so filters are ANDed in before the read runs.

Consequence: any MCP-side allowlist or deny-list of entities would **duplicate the
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
(token hashes — reading them escalates one MCP token into every other token).

This is not a privilege MCP adds: a superadmin already reads those tables from
`/app`. What changes is **who drives**. Over MCP the caller is a model reading
untrusted text, so an instruction hidden in a content body can steer it through the
superadmin's unlimited reach.

### Decision: MCP refuses superadmin tokens

The MCP middleware rejects a request whose token resolves to a superadmin, before
any tool runs. MCP access goes through a dedicated user with ordinary roles, sized
like any other account. Consequences:

- No entity allowlist or deny-list exists. Permissions and ACL are the whole
  authorization model, as in `/app`.
- The superadmin bypass stays untouched in Core; MCP simply never runs under it.
- Minting a token for a superadmin should fail at creation too, so the refusal is
  not first discovered by the client.

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

### Search — `mcp:read`

| Tool | Backed by |
|------|-----------|
| `search` | `CrudService::search` (ensemble / semantic) |

Highest-value tool of the set: it is the one an external LLM actually wants, and the
one that makes Laraplate content usable as a knowledge source.

**It passes `mode=deep`.** Search cost is becoming a per-request choice rather than a global
setting (`2026-09-16-search-modes-and-strategy-resolution-design.md`), and this is the caller
that should pay it: an external agent tolerates seconds where a rendered grid does not. Two
consequences for this server. The per-user rate limiting reasoned about below is not optional
for this tool, it is the control that makes a deep search affordable. And `deep` is gated by a
permission, so the MCP service account needs it like any other user: a token cannot grant what
the person behind it does not have.

### Write — `mcp:write` (phase 2)

Specified now, shipped once *Write approval* is settled. Nothing below changes when it is;
what changes is whether a call executes directly or raises an approval request first.


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

### Lifecycle — `mcp:write` (phase 2)

`activate`, `inactivate`, `approve`, `disapprove`, `lock`, `unlock`.

No separate ability. Approving content is a governance act, and whether a user may
do it is already a permission (`approve`, `lock`, …) checked by the gateway.

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

## Write approval: what MCP inherits from the AI module, and what it must not

The AI module is building an approval-gated tool path: when a model proposes a write,
`ToolRegistry` creates an `ActionRequest` classified by `RiskClassifier`, a person approves
or rejects it, and `ExecuteActionRequestJob` runs the handler only after approval. It is
being reconnected to the in-app assistant through a policy capability, so that which tools a
caller may reach is decided by the compiled policy for its profile rather than by the calling
code. The same shape was proposed for this server, on the reasoning that an MCP session is
always bound to one specific user.

**Two things transfer, one does not.**

*Does not transfer:* the AI module's `AssistantPolicyCatalog`. That catalog governs prompts
Laraplate itself composes, in the direction described under *Direction: this is not the AI
module*. Reusing it here would couple a served resource to a client, and its profiles answer
a question this server does not ask (which corpora and instructions shape an answer we
generate). Tool eligibility here is already answered by ability plus ACL.

*Transfers, and should:* **human approval for writes.** Reconsider the current position that
`mcp:write` plus ordinary ACL is enough. In-app, the input steering a tool call is typed by
the person sitting there. Here, by this spec's own opening premise, it is untrusted and
model-driven. The *more* exposed surface is the one currently without the safeguard, which is
backwards. Under the same threat model the *Audit* section already accepts (manipulated
content steering the model into an update, with the trail pointing at the person), an audit
log records the damage; an approval gate prevents it.

*Transfers as a principle:* the tool set a caller can reach is **declared, not assembled by
the caller**. Whatever declares it here (the ability, a profile, a capability), it must not be
a list built inside the request handler, or this server and the assistant will drift apart
tool by tool, and the user's "one day expose a few safe, controlled tools" over `/api/v1`
becomes a third hand-maintained list.

### Consequence to settle before implementation

`ActionRequestService` and `RiskClassifier` live in `Modules/AI`. Making MCP writes pass
through them as they stand would make this server depend on the AI module, against the
direction this spec sets. Three ways out, in order of preference:

1. **Move the approval mechanism to Core** behind a contract, and let both the AI assistant
   and MCP depend on the contract, not on each other. This matches how search already works
   (`ISearchPlanner` / `IReranker` in Core, AI overlaying implementations) and keeps each
   perimeter readable on its own. Cost: one refactor of the AI module before MCP writes ship.
2. **Ship MCP read-only first**, defer every write until the mechanism has a home. Cheapest,
   and read tools are where the value is anyway (`search` is named here as the highest-value
   tool of the set).
3. Accept the dependency `MCP → Modules/AI` for writes. Fastest, and the one that will be
   regretted, because it inverts the direction on the exact axis this spec opens by defining.

**Decided: 2 now, 1 when writes are opened.** Phase 1 ships read-only (see *Phasing*), which
buys the time to give the approval mechanism a home in Core without holding up the read tools,
where the value already is. Option 3 stays available only with a written reason, because it
inverts the direction this spec opens by defining.

### What phase 2 has to answer, recorded now so it is not rediscovered

Writes are a certainty, so these are open questions with a deadline, not hypotheticals:

1. **Where the approval mechanism lives.** Moving `ActionRequestService` and `RiskClassifier`
   to Core behind a contract, with `Modules/AI` overlaying its implementation the way the AI
   module already overlays `ISearchPlanner` and `IReranker`. This is the piece of work that
   gates everything else.
2. **Which writes need a human at all.** Requiring approval for every `insert` would make the
   server useless for the ordinary cases it exists to serve. `RiskClassifier` already maps a
   tool name to a risk level; the question is whether risk here should also read the entity
   and the operation, since `delete` on an ERP document and `update` on a draft note are not
   the same act. Deciding this badly in either direction is how a safety gate gets switched
   off wholesale by whoever finds it annoying.
3. **How an approval reaches the person.** In `/app` the requester is looking at the screen.
   Here the caller is an external client, possibly unattended, and the person may be nowhere
   near. A pending request needs somewhere to surface and a way to answer the client that the
   call is parked rather than failed, which is a protocol-shaped question and not only a UI
   one: MCP has no native "come back later".
4. **What a token may pre-approve.** A user might legitimately want a client that can write
   low-risk entities without a prompt each time. If so, that is a third gate, and it belongs
   in the token model, not in a config file.

None of this blocks phase 1. All of it blocks the first write tool.

## Rate limiting

A named Laravel rate limiter keyed on the **user**, applied to the MCP route group.

It does not limit LLM tokens: those are spent and paid by the external client. It
protects two things on our side:

- **Load.** An agent in a retry or planning loop hammers the database and
  Elasticsearch far faster than a person in `/app`.
- **Cost.** In advanced mode, `search` embeds the query on every call
  (`AdvancedSearchService::resolveVector` → `ITextEmbedder::embed`), which is a
  provider call when the embedder is a paid API.

Context: today only AI text generation is rate limited
(`ai.features.text_generation.rate_limit`); `/app` and `/api/v1` carry no HTTP
throttle (`throttle:api` is commented in `bootstrap/app.php`). A stricter bucket for
`search` than for the other reads is reasonable; exact numbers are a plan detail.

## Audit

Because the caller is a real user, every MCP action is recorded as that user's
action. After the fact, an edit made by the person in `/app` and one made by their
LLM client are indistinguishable. If manipulated content steers the model into an
update or an approval, the trail points at the person.

Decision: log every `tools/call` on a dedicated log channel with user id, token id
and name, tool, arguments, and outcome (ok, denied, validation error, exception).
The token id is what identifies the channel. Arguments are logged without secrets.

No generic audit layer exists today to absorb this: the only audits are
domain-specific (ERP document sequences, SAO closure). A log channel is enough for
v1; a queryable table is a later decision.

Out of scope, recorded because it surfaced here: model versions do not record the
acting user. `HasVersions::getVersionUserId` returns `null` unless the model has its
own user column, with `auth()->id()` commented out. This affects `/app` equally.

## Open points

1. MCP spec revision + PHP package choice (blocks implementation).
2. Implementation plan — written after point 1, since the package shapes it.
3. **Settled for phase 1:** the server ships read-only, so no write tool is implemented yet.
   Reopening the write set means answering the four questions under *Write approval → What
   phase 2 has to answer*, starting with giving the approval mechanism a home in Core. Blocks
   phase 2 only; the read set is unaffected.
