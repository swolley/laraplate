# SAO phase 3a — driver framework foundation design

**Date:** 2026-08-15
**Module:** `Modules/SAO`
**Parent spec:** `docs/superpowers/specs/2026-07-31-sao-module-design.md` (phase 3)
**Status:** Design proposed. No implementation started.

---

## 1. Purpose

Phase 3 of the SAO roadmap is "driver framework, `Connection`, capabilities, registry, conformance + Redmine", with the exit criterion *"ticket synchronized in a configurable direction"*. Scoping it separates the load-bearing **abstraction** from the first concrete **driver**:

| Slice | Content |
|-------|---------|
| **3a** — this spec | Driver base contract, per-capability contracts, open registry, `Connection` model, environment-resolved credentials, per-capability conformance suite, one in-memory reference driver — **no network, no concrete external driver** |
| **3b** | First real driver (Redmine `issues`), HTTP client, signature/credential handling, `ProjectBinding`, status-map persistence, sync direction, Filament connection forms |

**3a is the slice that makes every future driver cheap and safe.** The spec is categorical (§5): *"if adding a provider requires editing SAO, the abstraction has failed."* This slice builds and proves that abstraction offline, so 3b and every later driver are additive.

### Ordering note (deviation from roadmap sequence)

The roadmap places Phase 2 (fingerprinting / `Signal` / internal log source / loop protection) before Phase 3. This slice is **deliberately built before Phase 2** because the driver abstraction does not depend on signals: the `issues` and `releases` capabilities rest only on entities that exist since phase 1a (`Ticket`, `Release` is later but not needed for the contract shape), and the `logs` capability's contract can be declared without a `Signal` consumer. Building 3a first de-risks the abstraction early; Phase 2 and 3b can then proceed in either order. This ordering is an explicit, agreed decision, recorded here so a later reader does not read it as an accident.

---

## 2. Locked decisions

| # | Decision | Reason |
|---|----------|--------|
| F1 | A **driver** is registered code; a **connection** is a configured instance of it. | Matches parent §5; one token configured once, reused across capabilities. |
| F2 | The registry is **open**: SAO's provider populates it, but any module or third-party package registers a driver without editing SAO. | The parent spec's failure test for the abstraction. |
| F3 | Domain services depend **only** on per-capability contracts (`issues`, `vcs`, `logs`, `releases`), never on a concrete driver. | Keeps provider names (`github`, `redmine`, `graylog`) out of everything but `app/Drivers`. |
| F4 | **Credentials are SAO domain data on `sao_connections`, stored encrypted-at-rest and write-only, with an optional env `credential_ref` that wins when set.** Never in Core Settings (those hold product behaviour only). | Multiple connectors need multiple secrets created from the panel (§11), so per-connection secrets are entity data with a lifecycle tied to the row — not global app config. Honours §5's *requirements* (never readable from a UI → write-only; rotatable without a UI → env-ref or a rotate command) while supporting §11's UI-created, N-per-driver connections. This is the same split the AI module uses (behaviour in settings, secret out of the settings row); SAO moves the secret into its own table because it has N accounts, not one. |
| F5 | Product-behaviour configuration uses **Core settings**; only secrets/infrastructure live in the environment. | Parent §5: a threshold that needs a deploy to change never gets tuned. |
| F6 | Every capability list operation returns a **`Page`** and the conformance suite proves multi-page traversal. | Parent §5: "a driver that reads the first page and stops loses data on a real project." |
| F7 | **Status/priority maps live on the binding, not the driver** — passed into capability calls as data, with driver-proposed defaults. | Parent §5: Redmine statuses are per-installation; a hardcoded map makes the module single-tenant. |
| F8 | A driver "is done when it passes conformance", not when it works. | Parent §12: the defence against a fake abstraction. |

---

## 3. Non-goals (deferred to 3b or later)

- Any concrete external driver (Redmine is 3b), any HTTP client, signature verification, or credential rotation mechanics.
- Ticket synchronization, sync direction, `ProjectBinding`, persisted status maps, `IngestEvent`, `SourceProfile`.
- Filament forms generated from a driver's configuration schema (ships with the first real driver).
- `vcs`/`logs` behaviour driven by a real driver — their contracts are declared and shape-tested only; `logs` has no `Signal` consumer until Phase 2.

---

## 4. Domain model (this slice)

Only one persisted entity enters in 3a.

| Entity | Role |
|--------|------|
| `Connection` | A configured instance of a driver: `driver_key`, `name`, optional `base_url`, declared `capabilities` (a subset of the driver's), `health_state` (`unknown`/`healthy`/`unhealthy`), `last_checked_at`, and its secret via **one of two paths** (F4): an `encrypted` **`credential`** column (JSON, write-only — supports multi-field secrets like token + webhook secret) or an optional **`credential_ref`** env key that overrides it when present. Prevents configuring the same token twice. |

Everything else in this slice is code, not tables: enums (`Capability`, `IngestMode`), contracts (`DriverInterface` + the four capability interfaces), value objects (`DriverConfigurationSchema`, `HealthCheckResult`, `Page`), the `DriverRegistry`, and the `ConnectionCredentialResolver`.

`Connection` extends `Modules\Core\Overrides\Model`; its table is `sao_connections` via the `SAOTables` enum. Invariant: `capabilities ⊆ driver.capabilities()`.

---

## 5. Driver contract and capabilities

The base `DriverInterface` declares `key()`, `capabilities()`, `ingestModes()`, `configurationSchema()`, and `healthCheck(Connection)`. `DriverConfigurationSchema` names each configuration field with type, label, required flag, and a **secret flag** — secret fields resolve from the environment and are never persisted, non-secret fields may back Core settings.

Per-capability contracts (method shapes only; every list returns `Page`):

| Capability | Methods (3a contract shape) | Driven by a real driver in |
|------------|------------------------------|-----------------------------|
| `issues` | `lookup`, `create`, `update`, `comment`, `translateStatus(map)`, `list(): Page` | 3b (Redmine) |
| `releases` | `tags(): Page`, `firstTagContaining` | Phase 5 |
| `vcs` | `commits(): Page`, `compare`, `fileAtRef`, `openPullRequest` | Phase 5 |
| `logs` | `verifySignature`, `unpack(): Page`, `carriesNativeGroupKey(): bool` | Phase 2/4 |

`issues` and `releases` get full conformance batteries in 3a (their contract shapes are stable and dependency-free); `vcs`/`logs` are declared and shape-tested but their conformance batteries are stubbed until their consumers exist.

---

## 6. Credentials and configuration (F4/F5)

`ConnectionCredentialResolver::resolve(Connection): array` is the **single** path from a connection to its secret. Resolution order: if `credential_ref` is set, read from `config()`/environment by that key (infra-managed path); otherwise decrypt the `credential` column. It returns the value for in-memory use, never writes it back and never logs it; a connection with neither source throws `MissingCredentialException`. The raw secret is **never rendered back to a UI** (the Filament field is write-only, replaced by a rotate action, arriving in 3b) and **never stored in Core settings**. Product-behaviour configuration (thresholds, RC suffixes, policy toggles) is stored through Core settings in later phases, not by a SAO mechanism.

---

## 7. Authorization

Entirely Laraplate's, consistent with phase 1a: `Connection` access is governed by `PermissionName` permissions and Core's ACL chain. No SAO-specific authorization mechanism. (Connection management is superadmin-facing; fine-grained policies arrive with the Filament forms in 3b.)

---

## 8. Testing

- **Per-capability conformance suite** (`tests/Support/Conformance/*`): reusable assertions any driver implementing a capability must satisfy, run in 3a against one in-memory reference driver. Includes a **multi-page fixture** (more items than one page) proving pagination is structural (F6), and a status-translation case proving the map is passed-in data (F7).
- **In-memory reference driver** (`tests/Stubs/Drivers/InMemoryDriver.php`): implements `issues` + `releases` over arrays; exists only in test support; proves registry → connection → credential resolver → capability calls run fully offline.
- **Registry tests**: resolve by key and capability; unknown key throws; duplicate registration throws; a driver registered from outside SAO is resolvable (F2).
- **Connection tests**: non-secret coordinates persist; the `credential` column is **encrypted at rest** (the stored DB value is ciphertext, not the plaintext secret); the resolver returns the env value when `credential_ref` is set and otherwise the decrypted column; a capability the driver does not expose is rejected.
- **Zero-connections scenario** stays green (parent §12): the phase-1a tracker keeps working with no connection configured.

---

## 9. Risks

| Risk | Mitigation |
|------|-----------|
| Framework built before a real driver ossifies the wrong shape. | The conformance suite + in-memory driver force at least one full implementation; `issues`/`releases` shapes come straight from parent §5, which already anticipated the driver waves. |
| Building 3a before Phase 2 leaves `logs` unexercised. | `logs` is declared and shape-tested only; its conformance battery is explicitly deferred to its consumer phase, recorded as a known gap. |
| Credential-location tension (§4 vs §5) picked silently. | Settled in F4: encrypted-at-rest on `sao_connections` (write-only) with an optional env `credential_ref` override, never in Core settings. DB dump exposes only ciphertext; a leaked APP_KEY is the residual risk, mitigated by the env-ref path for shops that require infra-managed secrets. |
| Registry duplicate keys mask a collision. | Duplicate registration throws rather than last-wins. |
