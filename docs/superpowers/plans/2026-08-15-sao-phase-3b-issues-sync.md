# SAO Phase 3b — Bindings and Issues Synchronization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close Phase 3's exit criterion — *a ticket synchronized in a configurable direction* — by adding project bindings, an enriched capability-call context, a normalized issue shape, an idempotent direction-aware sync service, ticket links, the network-free `internal` issues driver (reference implementation), and the first external driver (Redmine) behind its conformance gate.

**Architecture:** Bindings (`ProjectBinding`) carry the project↔connection↔capability join plus sync direction and status/priority maps. Capability methods receive a `BindingContext` composing the 3a `ConnectionContext` with the binding's config and maps, so drivers act without touching persistence. `IssueSyncService` is the single reconciliation path; every outbound write is idempotent by a persisted key. The `internal` driver is built and conformance-passed before Redmine, so the normalized shape is validated by a real consumer first.

**Tech Stack:** PHP 8.5, Laravel 12, `nwidart/laravel-modules` 12, Filament 5, Pest 4, PHPStan/Larastan 3, Pint. HTTP client work (Laravel `Http`) only in the Redmine slice.

## Global Constraints

- **Spec:** `docs/superpowers/specs/2026-08-15-sao-phase-3b-issues-sync-design.md` (decisions G1–G9), rooted in `docs/superpowers/specs/2026-07-31-sao-module-design.md` §4/§5/§8/§12 and building on 3a.
- Every PHP file `declare(strict_types=1);`; `final` unless a sibling proves otherwise; explicit types; constructor promotion; `#[Override]`.
- Models extend `Modules\Core\Overrides\Model`; tables `sao_`-prefixed via `SAOTables`; enum keys TitleCase, lowercase backing values.
- Secrets stay per F4 (encrypted `credential` or env `credential_ref`); the new `config` column is **non-secret only**. Product-behaviour config uses Core settings.
- Tests in `Modules/SAO/tests/`; fakes/doubles/fixtures under `Modules/SAO/tests/Support/` (the registered autoload-dev namespace) — never declare classes in a test file.
- Per step: run the minimal relevant tests (`php artisan test --compact Modules/SAO/...`); before each commit run Pint (SAO config) on touched files. If autoload needs regenerating, use `COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload` so module plugins stay enabled.

## Slice map

- **3b-core** — Tasks 1–7 (fully offline).
- **3b-redmine** — Tasks 8–9 (need committed anonymized fixtures; no live API in CI).
- **3b-ui** — Task 10 (Filament).

---

## Task 1: SyncDirection enum and NormalizedIssue value object

- Create: `Modules/SAO/app/Enums/SyncDirection.php` (`Inbound='inbound'`, `Outbound='outbound'`, `Bidirectional='bidirectional'`, `Disabled='disabled'`; `values()` + `validationRule()`).
- Create: `Modules/SAO/app/Drivers/Support/NormalizedIssue.php` (readonly: `remoteId`, `key`, `title`, `body`, `remoteStatus`, `remotePriority`, `assignee`, `url`, `createdAt`, `updatedAt`; a `toArray()` for transport).
- Create: `Modules/SAO/tests/Unit/Enums/SyncDirectionTest.php` and `Modules/SAO/tests/Unit/Drivers/NormalizedIssueTest.php`.

- [ ] **Step 1: failing tests** — enum resolves from wire values and exposes an `in:` rule; `NormalizedIssue` round-trips through `toArray()`.
- [ ] **Step 2: run to fail.**
- [ ] **Step 3: implement.**
- [ ] **Step 4: run to pass.**
- [ ] **Step 5: Pint + commit** (`feat(sao): sync-direction enum and normalized issue value object`).

---

## Task 2: BindingContext and capability-signature evolution

- Create: `Modules/SAO/app/Drivers/Support/BindingContext.php` composing `ConnectionContext` and adding `remoteIdentifier: ?string`, `config: array`, `statusMap: array<string,string>`, `priorityMap: array<string,string>`.
- Edit: the four capability contracts to take `BindingContext` instead of `ConnectionContext` (reads/writes); `DriverInterface::healthCheck` keeps `ConnectionContext` (health is connection-level, not binding-level).
- Edit: 3a `FakeIssuesDriver`, `FakeReleasesDriver`, `InMemoryDriver`, and the `IssuesConformance`/`ReleasesConformance` helpers to construct/accept a `BindingContext`.

- [ ] **Step 1: failing test** — `tests/Unit/Drivers/BindingContextTest.php` asserts it exposes the connection context plus binding fields; update the 3a conformance run to pass a `BindingContext` (this is the red driver for the signature change).
- [ ] **Step 2: run to fail.**
- [ ] **Step 3: implement `BindingContext`; migrate the contracts, the fakes/in-memory driver, and the conformance helpers.**
- [ ] **Step 4: run the full driver test set to pass** (`tests/Unit/Drivers`, `tests/Feature/Drivers`).
- [ ] **Step 5: Pint + commit** (`refactor(sao): capability calls receive a BindingContext`).

---

## Task 3: Connection gains a non-secret config column

- Create: migration `..._add_config_to_sao_connections_table.php` (nullable `config` json).
- Edit: `Connection` — add `config` to fillable, cast `config` to `array`; a helper `connectionContext(array $credentials): ConnectionContext` (base_url + credentials).
- Edit: `ConnectionTest` to cover config round-trip.

- [ ] **Step 1: failing test** — config persists and round-trips as an array; it is not treated as a secret (plain, not encrypted).
- [ ] **Step 2: run to fail.**
- [ ] **Step 3: implement.**
- [ ] **Step 4: run to pass.**
- [ ] **Step 5: Pint + commit** (`feat(sao): non-secret config column on connections`).

---

## Task 4: ProjectBinding model and migration

- Create: migration `..._create_sao_project_bindings_table.php` (`project_id`, `connection_id`, `capability`, `remote_identifier`, `sync_direction`, `status_map` json, `priority_map` json, `config` json, timestamps + soft deletes; unique on the four-tuple; FKs to projects/connections).
- Create: `Modules/SAO/app/Models/ProjectBinding.php` (belongsTo Project + Connection; casts `capability` → `Capability`, `sync_direction` → `SyncDirection`, maps → array; `bindingContext(ConnectionCredentialResolver): BindingContext`).
- Create: `ProjectBindingFactory`; add `ProjectBindings` to `SAOTables` (+ update `SaoEnumsTest`).
- Create: `tests/Feature/Models/ProjectBindingTest.php`.

- [ ] **Step 1: failing test** — a binding persists; the four-tuple uniqueness is enforced; `bindingContext()` yields a `BindingContext` with the resolved credentials, remote identifier and maps; the capability must be one the connection exposes.
- [ ] **Step 2: run to fail.**
- [ ] **Step 3: implement.**
- [ ] **Step 4: run to pass.**
- [ ] **Step 5: Pint + commit** (`feat(sao): project binding model with binding context`).

---

## Task 5: TicketLink model and migration

- Create: migration `..._create_sao_ticket_links_table.php` (`ticket_id`, `connection_id`, `remote_id`, `url`, `last_synced_at`, `last_sync_state`, timestamps + soft deletes; unique on (connection_id, remote_id)).
- Create: `Modules/SAO/app/Models/TicketLink.php` (belongsTo Ticket + Connection); `TicketLinkFactory`; `SAOTables` entry (+ `SaoEnumsTest`).
- Edit: `Ticket` — add `links(): HasMany` and an `isInternal(): bool` (no links).
- Create: `tests/Feature/Models/TicketLinkTest.php`.

- [ ] **Step 1: failing test** — a link persists and enforces (connection, remote_id) uniqueness; a ticket with no link reports internal.
- [ ] **Step 2–4: red → implement → green.**
- [ ] **Step 5: Pint + commit** (`feat(sao): ticket link model`).

---

## Task 6: internal issues driver (reference implementation)

- Create: `Modules/SAO/app/Drivers/Internal/InternalIssuesDriver.php` — key `internal`, capability `Issues`, ingest `InProcess`; configuration schema declares non-secret `project` and `ticket_type`. Reads via `TicketQueryService` (ACL-scoped) mapped to `NormalizedIssue`; writes via `TicketCreationService`/`WorkflowService`; `comment` appends a `TicketComment` (origin system); `translateStatus` is identity over canonical categories. Project/type come from `BindingContext.config`.
- Create: `tests/Feature/Drivers/InternalIssuesDriverTest.php` running the `IssuesConformance` battery against a seeded project/type, plus internal-specific assertions (created ticket is real; reads are ACL-scoped).

- [ ] **Step 1: failing test** — conformance + internal specifics.
- [ ] **Step 2: run to fail.**
- [ ] **Step 3: implement the driver against the domain services.**
- [ ] **Step 4: run to pass.**
- [ ] **Step 5: Pint + commit** (`feat(sao): internal issues driver over the ticket domain`).

---

## Task 7: IssueSyncService (direction-aware, idempotent)

- Create: migration `..._create_sao_sync_operations_table.php` (idempotency ledger: `key` unique, `binding_id`, `outcome`, timestamps) unless a Core idempotency primitive is preferred.
- Create: `Modules/SAO/app/Services/IssueSyncService.php` — resolves a binding, builds `BindingContext`, and applies `SyncDirection`: Outbound (create/update/comment remote, idempotent), Inbound (upsert Ticket + `TicketLink`, translate status/priority via maps), Bidirectional (last-writer-wins by `updated_at` with link state tie-breaker), Disabled (no-op). Unmapped status returns an explicit outcome.
- Create: `tests/Feature/Services/IssueSyncServiceTest.php` — direction matrix, idempotency (repeated outbound op creates nothing new), unmapped-status surfacing. Uses the `internal` driver (and a recorded-response double) as the remote so the whole test is offline.

- [ ] **Step 1: failing tests** — direction matrix + idempotency + unmapped status.
- [ ] **Step 2: run to fail.**
- [ ] **Step 3: implement.**
- [ ] **Step 4: run to pass; then run the full SAO suite green.**
- [ ] **Step 5: Pint + commit** (`feat(sao): idempotent direction-aware issue sync`). **End of 3b-core.**

---

## Task 8 (3b-redmine): Redmine issues driver

- Create: `Modules/SAO/app/Drivers/Redmine/RedmineIssuesDriver.php` — Laravel `Http` client; API-key credential resolved via `ConnectionCredentialResolver`; paginates Redmine's `limit`/`offset`; proposes a default status map from Redmine statuses; maps Redmine issues to `NormalizedIssue`.
- Create: `Modules/SAO/tests/Support/Fixtures/Redmine/*` — anonymized recorded responses (issues list across >1 page, single issue, statuses).
- Create: `tests/Feature/Drivers/RedmineIssuesDriverTest.php` — the `IssuesConformance` battery driven by the fixtures via a faked `Http`.

- [ ] **Step 1: failing test** — conformance against fixtures.
- [ ] **Step 2: run to fail.**
- [ ] **Step 3: implement the driver + capture/commit fixtures.** If real fixtures cannot be captured in this environment, `log()`/note the gap, ship the driver behind its conformance gate with synthetic fixtures clearly labelled, and record live validation as a follow-up.
- [ ] **Step 4: run to pass.**
- [ ] **Step 5: Pint + commit** (`feat(sao): redmine issues driver`).

---

## Task 9 (3b-redmine): register Redmine and close the exit criterion

- Edit: config `sao.drivers.registered` docs to show enabling `redmine`; wire an end-to-end feature test that binds a project to a Redmine connection and runs `IssueSyncService` Outbound, asserting the (faked) remote received exactly one idempotent write.
- [ ] Red → implement → green; Pint + commit (`test(sao): end-to-end redmine issue sync in a configurable direction`).

---

## Task 10 (3b-ui): Filament connection and binding surfaces

- Create: Filament resources for `Connection` (form generated from the driver's configuration schema, secret fields write-only, a **test** action calling `healthCheck`) and `ProjectBinding` (choose connection + capability + remote identifier + direction + editable maps).
- Create: `tests/Feature/Filament/ConnectionResourceTest.php` and `BindingResourceTest.php` (resource pages render, secret field is write-only, capability options limited to the connection's).
- [ ] Red → implement → green; Pint + commit (`feat(sao): filament connection and binding management`).

---

## Slice exit criteria

- **3b-core:** a project can be bound to a connection with a `SyncDirection`; `IssueSyncService` reconciles a ticket through the `internal` driver in every direction; outbound writes are idempotent; unmapped statuses surface. Full SAO suite green offline.
- **3b-redmine:** the Redmine driver passes the `issues` conformance battery; an end-to-end test synchronizes a ticket to a (faked) Redmine in a configurable direction — the parent Phase 3 exit criterion.
- **3b-ui:** a superadmin can create a connection (schema-generated form, write-only secret, test button) and bind a project to it.

## Known gaps carried forward

- Live Redmine validation depends on captured fixtures; if unavailable in CI it is a gated follow-up.
- `vcs`/`logs` capabilities remain contract-only (Phases 4/5); `logs` still has no `Signal` consumer until Phase 2.
- Scheduled/push sync transport is minimal (a queued idempotent job); cadence and webhook ingest are Phase 4.
