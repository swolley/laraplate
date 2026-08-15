# SAO Phase 3a — Driver Framework Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the load-bearing integration abstraction for SAO — the driver base contract, the per-capability contracts domain services depend on, an **open** driver registry, the `Connection` model, environment-resolved credentials, and a reusable **per-capability conformance suite** — with **no network access and no concrete external driver**. The deliverable proves the abstraction end-to-end offline via one in-memory reference driver that passes conformance. Concrete drivers (Redmine, GitHub, …) and ticket synchronization are explicitly out of scope; they land in Phase 3b once this foundation and Phase 2 exist.

**Architecture:** A **driver** is registered code; a **connection** is a configured instance of it. The registry is populated by `SAOServiceProvider` but is **open** — any module or third-party package registers a driver without editing SAO (spec §5: "if adding a provider requires editing SAO, the abstraction has failed"). The base contract declares key, exposed capabilities, supported ingest modes, a configuration schema (so connection forms are generated, not hand-written), and a health check. Domain services depend **only** on the per-capability contracts (`issues`, `vcs`, `logs`, `releases`), never on a concrete driver. Every capability list operation paginates and the conformance suite proves it with a multi-page fixture.

**Tech Stack:** PHP 8.5, Laravel 12, `nwidart/laravel-modules` 12, Pest 4, PHPStan/Larastan 3, Pint. No HTTP client work in this phase.

## Global Constraints

- **Spec:** `docs/superpowers/specs/2026-08-15-sao-phase-3a-driver-framework-foundation-design.md` (phase-3a design, decisions F1–F8), rooted in `docs/superpowers/specs/2026-07-31-sao-module-design.md` §4/§5/§12. This plan implements slice 3a; the Redmine driver and sync direction (the spec's phase-3 `3b` row) are deferred.
- Every PHP file starts with `declare(strict_types=1);`. Classes are `final` unless a sibling proves otherwise. Explicit param and return types throughout; constructor property promotion; `#[Override]` when overriding.
- Models extend `Modules\Core\Overrides\Model` (provides `HasFactory`, `HasPrefixedTableName`, `HasValidations`, `HasVersions`, `SoftDeletes`), matching the phase-1a convention.
- Table names are `sao_`-prefixed and declared through the `SAOTables` enum (`Modules\Core\Enums\Concerns\HasModuleTablesUtils`), never inline strings.
- Enum keys are TitleCase; enum backing values are the lowercase wire strings (`vcs`, `issues`, `releases`, `logs`, `push`, `pull`, `in_process`).
- Authorization is entirely Laraplate's (`PermissionName` + Core ACL). No SAO-specific auth mechanism.
- Tests live in `Modules/SAO/tests/`. **Never** declare classes/interfaces/enums inside a test file — fakes and doubles go in `Modules/SAO/tests/Stubs/` with PSR-4 namespaces registered in the module `composer.json` `autoload-dev`.
- Run the minimum relevant tests per step: `php artisan test --compact Modules/SAO/...`. Before each commit: `vendor/bin/pint` (SAO module config) on the touched files.

## Decision to confirm before Task 4 (credentials location)

The spec has an internal tension the implementer must not silently pick:

- §4 describes `Connection` as holding **"encrypted credentials"**.
- §5 ("Where configuration lives") states secrets **"stay in the environment and are never written to the database … a secret must be rotatable without a UI and must never be readable from one."**

**This plan follows §5** (recorded as spec decision **F4**): `Connection` stores a **credential reference** (an environment/config key) plus non-secret coordinates (driver key, base URL, declared capabilities, health state); the raw secret is resolved from the environment at use time and is never persisted or exposed. Product-behaviour configuration (thresholds, RC suffixes, …) uses Core settings, not a SAO mechanism. **If the spec owner instead wants encrypted-at-rest DB credentials, stop and revise F4 and Task 4 before implementing.**

---

## Task 1: Capability and ingest-mode enums

- Create: `Modules/SAO/app/Enums/Capability.php`
- Create: `Modules/SAO/app/Enums/IngestMode.php`
- Create: `Modules/SAO/tests/Unit/Enums/DriverEnumsTest.php`

`Capability`: `Vcs='vcs'`, `Issues='issues'`, `Releases='releases'`, `Logs='logs'`. `IngestMode`: `Push='push'`, `Pull='pull'`, `InProcess='in_process'`.

- [ ] **Step 1: Write the failing test** — assert each enum resolves from its wire string (`Capability::from('issues')`), that `Capability::cases()` has exactly the four families, and that a bogus value throws.
- [ ] **Step 2: Run the test to verify it fails** (enums do not exist yet).
- [ ] **Step 3: Create the two enums** with the backing values above.
- [ ] **Step 4: Run the test to verify it passes.**
- [ ] **Step 5: Format, analyse and commit** (`feat(sao): capability and ingest-mode enums`).

---

## Task 2: Driver base contract and per-capability contracts

- Create: `Modules/SAO/app/Drivers/Contracts/DriverInterface.php`
- Create: `Modules/SAO/app/Drivers/Contracts/IssuesCapability.php`
- Create: `Modules/SAO/app/Drivers/Contracts/VcsCapability.php`
- Create: `Modules/SAO/app/Drivers/Contracts/LogsCapability.php`
- Create: `Modules/SAO/app/Drivers/Contracts/ReleasesCapability.php`
- Create: `Modules/SAO/app/Drivers/Support/DriverConfigurationSchema.php` (value object: named fields, each with type/label/required/secret flag — drives form generation and marks which fields are secrets so they never land in the DB)
- Create: `Modules/SAO/app/Drivers/Support/HealthCheckResult.php` (value object: `healthy: bool`, `detail: ?string`)
- Create: `Modules/SAO/app/Drivers/Support/Page.php` (generic paginated result: `items: list`, `nextCursor: ?string`) — the shape every capability list returns so pagination is structural, not per-driver.

`DriverInterface` declares: `key(): string`, `capabilities(): list<Capability>`, `ingestModes(): list<IngestMode>`, `configurationSchema(): DriverConfigurationSchema`, `healthCheck(Connection $connection): HealthCheckResult`. Per-capability contracts declare only method signatures returning `Page` for list operations (e.g. `IssuesCapability::lookup`, `create`, `update`, `comment`, `translateStatus`; `VcsCapability::commits(): Page`, `compare`, `fileAtRef`, `openPullRequest`; `LogsCapability::verifySignature`, `unpack(): Page`, `carriesNativeGroupKey(): bool`; `ReleasesCapability::tags(): Page`, `firstTagContaining`).

- [ ] **Step 1: Write the failing test** — in `tests/Unit/Drivers/DriverContractTest.php`, create a fake driver in `tests/Stubs/Drivers/` implementing `DriverInterface` + `IssuesCapability`; assert it advertises `Capability::Issues`, exposes a configuration schema whose secret fields are flagged, and that `instanceof` checks per capability work.
- [ ] **Step 2: Run the test to verify it fails.**
- [ ] **Step 3: Create the contracts and value objects.**
- [ ] **Step 4: Run the test to verify it passes.**
- [ ] **Step 5: Format, analyse and commit** (`feat(sao): driver base and per-capability contracts`).

---

## Task 3: Open driver registry

- Create: `Modules/SAO/app/Drivers/DriverRegistry.php`
- Create: `Modules/SAO/app/Exceptions/UnknownDriverException.php`
- Create: `Modules/SAO/tests/Unit/Drivers/DriverRegistryTest.php`

`DriverRegistry`: `register(DriverInterface $driver): void` (keyed by `key()`, last registration wins with a documented rule or throws on duplicate — pick throw-on-duplicate to catch collisions early), `has(string $key): bool`, `get(string $key): DriverInterface` (throws `UnknownDriverException` when absent), `all(): list<DriverInterface>`, `withCapability(Capability $c): list<DriverInterface>`. Registered as a singleton so any provider can contribute.

- [ ] **Step 1: Write the failing test** — register a stub driver, resolve it, filter by capability, and assert `get('nope')` throws `UnknownDriverException` and duplicate registration throws.
- [ ] **Step 2: Run the test to verify it fails.**
- [ ] **Step 3: Create the registry and exception.**
- [ ] **Step 4: Run the test to verify it passes.**
- [ ] **Step 5: Format, analyse and commit** (`feat(sao): open driver registry`).

---

## Task 4: Connection model and migration

> Implement the §5-consistent design confirmed above: no raw secret in the DB.

- Create: `Modules/SAO/database/migrations/2026_08_15_100000_create_sao_connections_table.php`
- Create: `Modules/SAO/app/Models/Connection.php`
- Create: `Modules/SAO/app/Database/Factories/ConnectionFactory.php`
- Edit: `Modules/SAO/app/Enums/SAOTables.php` (add `Connections = 'sao_connections'`)
- Create: `Modules/SAO/tests/Feature/Models/ConnectionTest.php`

`sao_connections` columns: `id`, `driver_key` (string, indexed), `name` (string), `base_url` (string, nullable), `credential_ref` (string, nullable — the env/config key the secret is read from; never the secret), `capabilities` (json — a subset of the driver's declared capabilities), `health_state` (string enum-backed: `unknown`/`healthy`/`unhealthy`, default `unknown`), `last_checked_at` (nullable), Core timestamps + soft deletes. `Connection` casts `capabilities` to `list<Capability>` and exposes `driver(DriverRegistry): DriverInterface`. A model invariant rejects a `capabilities` value not ⊆ the driver's `capabilities()`.

- [ ] **Step 1: Write the failing test** — a factory-made connection persists, `capabilities` round-trips as `Capability` instances, `credential_ref` holds no secret, and declaring a capability the driver does not expose is rejected.
- [ ] **Step 2: Run the test to verify it fails.**
- [ ] **Step 3: Create the migration, model, factory, and `SAOTables` entry.**
- [ ] **Step 4: Run the test to verify it passes.**
- [ ] **Step 5: Format, analyse and commit** (`feat(sao): connection model with env-referenced credentials`).

---

## Task 5: Environment credential resolution

- Create: `Modules/SAO/app/Drivers/Support/ConnectionCredentialResolver.php`
- Create: `Modules/SAO/app/Exceptions/MissingCredentialException.php`
- Create: `Modules/SAO/tests/Unit/Drivers/ConnectionCredentialResolverTest.php`

`ConnectionCredentialResolver::resolve(Connection $connection): array` reads the secret from `config()`/env by `credential_ref`, returns it for in-memory use, and **never** writes it back or logs it. A missing reference throws `MissingCredentialException`. This is the single sanctioned path from a `Connection` to its secret.

- [ ] **Step 1: Write the failing test** — set a config value, resolve it via a connection's `credential_ref`, assert the secret is returned and that a missing ref throws; assert the resolver never mutates the connection.
- [ ] **Step 2: Run the test to verify it fails.**
- [ ] **Step 3: Create the resolver and exception.**
- [ ] **Step 4: Run the test to verify it passes.**
- [ ] **Step 5: Format, analyse and commit** (`feat(sao): environment credential resolver for connections`).

---

## Task 6: Per-capability conformance suite + in-memory reference driver

> The defence against a fake abstraction (spec §12). Any driver implementing a capability passes the same battery; the battery includes a **multi-page** fixture (spec §5: "a fixture with more items than one page holds").

- Create: `Modules/SAO/tests/Support/Conformance/IssuesConformance.php` (reusable assertions a driver's `issues` implementation must satisfy; same pattern for `Vcs`, `Logs`, `Releases` — start with `issues` and `releases`, the two whose dependencies exist in 1a)
- Create: `Modules/SAO/tests/Stubs/Drivers/InMemoryDriver.php` (a network-free reference driver implementing `issues` + `releases`, backed by arrays, that paginates)
- Create: `Modules/SAO/tests/Feature/Drivers/InMemoryDriverConformanceTest.php`

The conformance helpers assert: capability list operations return a `Page`; requesting beyond one page follows `nextCursor` to completion (the fixture holds more items than the page size, proving no first-page-only bug); lookups by key behave; status translation is driven by the binding-provided map, not hardcoded (the map is passed in, defaults proposed by the driver). The `InMemoryDriver` exists **only** in test support and proves the whole stack — registry → connection → credential resolver → capability calls — runs offline.

- [ ] **Step 1: Write the failing test** — `InMemoryDriverConformanceTest` runs the `issues` and `releases` conformance batteries against `InMemoryDriver`, including the multi-page fixture.
- [ ] **Step 2: Run the test to verify it fails.**
- [ ] **Step 3: Create the conformance helpers and the in-memory driver.**
- [ ] **Step 4: Run the test to verify it passes.**
- [ ] **Step 5: Register the autoload-dev namespaces** in `Modules/SAO/composer.json` for the new `Stubs`/`Support` paths if not already covered; `composer dump-autoload`.
- [ ] **Step 6: Format, analyse and commit** (`test(sao): per-capability conformance suite with in-memory driver`).

---

## Task 7: Provider wiring, config, and docs

- Edit: `Modules/SAO/app/Providers/SAOServiceProvider.php` (bind `DriverRegistry` as a singleton; expose an extension point where SAO and third parties register drivers — no concrete driver registered yet, since none ships in this phase)
- Edit: `Modules/SAO/config/config.php` (a `drivers` section documenting how drivers register and where product-behaviour config lives vs environment secrets)
- Edit: `Modules/SAO/docs/rag/MODULE.md` (add a "Driver framework" developer section: the open registry, capability contracts, the `Connection` = configured instance rule, env-referenced credentials per §5, and the conformance-suite contract that a driver "is done when it passes conformance")
- Create: `Modules/SAO/docs/rag/DRIVERS_USER.md` (audience: user, cross_cutting_user: false — a short operator-facing note that external connections do not exist yet and that configuring one will, in a later phase, generate its form from the driver's schema; secrets come from the environment, product settings from the UI)

- [ ] **Step 1** — wire the registry singleton and the registration extension point; assert via a small feature test that the container resolves `DriverRegistry` and that registering a stub through the provider hook makes it resolvable.
- [ ] **Step 2** — add the config section and both doc updates.
- [ ] **Step 3: Run the full SAO suite** (`php artisan test --compact Modules/SAO`) and confirm green.
- [ ] **Step 4: Format, analyse and commit** (`feat(sao): register driver framework and document it`).

---

## Slice exit criteria

1. `DriverRegistry` resolves drivers by key and by capability; unknown key throws; the registry is open (a driver registered from outside SAO is resolvable).
2. A `Connection` persists non-secret coordinates and a credential **reference**; no raw secret is ever written to the database; declaring a capability its driver does not expose is rejected.
3. `ConnectionCredentialResolver` is the single path from a connection to its secret, reading from the environment only.
4. The `issues` and `releases` conformance batteries pass against the in-memory reference driver, **including** the multi-page fixture — proving pagination is structural and the abstraction runs fully offline.
5. `php artisan test --compact Modules/SAO` is green; Pint and PHPStan clean; RAG docs updated.

## Known gaps carried into Phase 3b

- No concrete external driver (Redmine is the spec's phase-3 first driver) and no HTTP client, signature verification, or real credential rotation.
- No ticket synchronization / sync direction, no `ProjectBinding`, no status-map persistence UI — the conformance suite exercises the map as passed-in data only.
- `vcs`/`logs` conformance batteries are stubbed for shape but not driven by a real driver until their dependencies (Phase 2 signals for `logs`, Phase 5 for `vcs`) exist.
- Filament connection form generation from the driver schema is deferred to the phase that ships the first real driver.
