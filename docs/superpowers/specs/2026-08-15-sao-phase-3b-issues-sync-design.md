# SAO phase 3b — bindings and issues synchronization design

**Date:** 2026-08-15
**Module:** `Modules/SAO`
**Parent spec:** `docs/superpowers/specs/2026-07-31-sao-module-design.md` (phase 3)
**Builds on:** `docs/superpowers/specs/2026-08-15-sao-phase-3a-driver-framework-foundation-design.md` (slice 3a)
**Status:** Design proposed. No implementation started.

---

## 1. Purpose

Phase 3a built the driver-framework foundation: contracts, an open registry, the `Connection` model with resolved credentials, and a per-capability conformance suite proven offline. It deliberately stopped at the point where a capability call needs to know **which project, which remote object, and with which status/priority mapping** it operates — because that is binding, not connection, and binding is this slice.

Phase 3b closes the parent spec's Phase 3 exit criterion — *"ticket synchronized in a configurable direction"* — by adding:

- `ProjectBinding`: the join of a project, a connection, a capability, a remote identifier, and binding-scoped configuration (sync direction, status/priority maps).
- An enriched capability-call context carrying that binding configuration, so drivers can act without knowing the database.
- A normalized issue shape and an idempotent, direction-aware **issue synchronization** service.
- `TicketLink`: the Ticket ↔ external-tracker record.
- The `internal` issues driver as the network-free **reference implementation** that validates the normalized shape and the sync mechanics with no external system.
- The first external driver, **Redmine** (`issues`), passing conformance against recorded, anonymized fixtures.

### Slices

| Slice | Content | Offline? |
|-------|---------|----------|
| **3b-core** (this plan's spine) | `Connection.config`, `ProjectBinding`, `TicketLink`, `SyncDirection`, `NormalizedIssue`, enriched `BindingContext`, `IssueSyncService`, and the `internal` issues driver + conformance | **Yes** — fully buildable and testable with no network |
| **3b-redmine** | The Redmine `issues` driver + recorded fixtures + conformance | Needs committed fixtures (captured from a real Redmine); no live API in CI |
| **3b-ui** | Filament: connection resource with a driver-schema-generated form + test button, and binding management (§11) | Yes |

3b-core is the load-bearing part and is the reference implementation of the whole sync. 3b-redmine closes the parent exit criterion (an *external* target). 3b-ui makes it operable by a superadmin.

---

## 2. Locked decisions

| # | Decision | Reason |
|---|----------|--------|
| G1 | `ProjectBinding` = project × connection × capability × remote identifier + binding config (sync direction, status map, priority map). Multiple bindings of the same family are allowed. | Parent §4: a project holds bindings, not URLs/credentials. |
| G2 | Capability methods receive a **`BindingContext`** (resolved credentials + base URL + non-secret connection `config` + the binding's maps and remote identifier). It composes the 3a `ConnectionContext`; drivers still never touch the Eloquent model. | Resolves the 3a wall: a driver needs binding config to act, without knowing persistence. |
| G3 | `Connection` gains a non-secret `config` JSON column for connection-level settings (e.g. an internal driver's project/type). Secrets stay in `credential`/`credential_ref` (F4, unchanged). | Non-secret configuration is not a secret and must be editable; F5 already separates the two. |
| G4 | Status and priority maps live on the **binding** (3a F7), with defaults proposed by the driver and correctable per binding. | Parent §5: statuses are per-installation; a hardcoded map makes SAO single-tenant. |
| G5 | Every outbound write carries a **persisted idempotency key**; a retry can never create a second ticket or comment. | Parent §5: trackers rarely offer idempotent write APIs, so the guarantee lives on our side. |
| G6 | `SyncDirection` is `Inbound` (remote → SAO), `Outbound` (SAO → remote), `Bidirectional`, or `Disabled`, configured per binding (D5). | "Configurable direction" is the phase's exit criterion. |
| G7 | The `internal` issues driver is built **first** as the reference implementation and validates the `NormalizedIssue` shape before any external driver. | Guards against a fake abstraction the second driver barely fits. |
| G8 | The Redmine driver is done only when it **passes the same `issues` conformance battery** as the internal driver, run against an in-memory double and recorded, anonymized real responses. | Parent §12. |
| G9 | `TicketLink` records Ticket ↔ (connection, remote id, url, last sync state); a ticket with no link is internal. Internal tickets remain authoritative in SAO. | Parent §4/§8. |

---

## 3. Non-goals (later phases)

- `vcs`/`logs` drivers and their capabilities driven end to end (Phases 4/5); only `issues` synchronizes here.
- Fingerprinting, `Signal`, ingest pipeline (Phase 2).
- `SourceProfile`, generic webhook ingest, replay (Phase 4).
- Release/census/`ChangeRef` (Phase 5).
- Real-time/push sync transport beyond a queued, idempotent job; scheduling cadence is minimal.

---

## 4. Domain model (this slice)

| Entity | Role |
|--------|------|
| `ProjectBinding` | `project_id`, `connection_id`, `capability`, `remote_identifier` (e.g. a Redmine project id), `sync_direction`, `status_map` (json: remote status → canonical category), `priority_map` (json), `config` (json, binding-scoped). Unique on (project, connection, capability, remote_identifier). |
| `TicketLink` | `ticket_id`, `connection_id`, `remote_id`, `url`, `last_synced_at`, `last_sync_state`. Absent link ⇒ internal ticket. Unique on (connection, remote_id). |
| `Connection.config` | New non-secret JSON column (G3) for connection-level settings. |

New tables use the `sao_` prefix via `SAOTables`. Both models extend `Modules\Core\Overrides\Model`. Authorization stays Laraplate's (`PermissionName` + ACL), consistent with 1a/3a.

### Value objects and enums

- `SyncDirection` enum (G6).
- `NormalizedIssue` (G7): `remoteId`, `key`, `title`, `body`, `remoteStatus`, `remotePriority`, `assignee`, `url`, `createdAt`, `updatedAt`. The capability's `lookup`/`list` return these; the sync layer translates `remoteStatus`/`remotePriority` through the binding maps.
- `BindingContext` (G2): wraps `ConnectionContext` and adds `remoteIdentifier`, `config`, `statusMap`, `priorityMap`. Capability method signatures change from `ConnectionContext` to `BindingContext`; the 3a fake/in-memory drivers and conformance helpers are updated accordingly (a mechanical evolution, not a rewrite).

---

## 5. Issue synchronization

`IssueSyncService` is the single path that reconciles a SAO ticket with its remote counterpart, honouring the binding's `SyncDirection`:

```
resolve binding → build BindingContext (creds + config + maps)
  Outbound: SAO ticket changed → create/update/comment on the remote (idempotent by key)
  Inbound:  remote issue fetched → upsert SAO ticket + TicketLink, translating status/priority via maps
  Bidirectional: last-writer-wins by updated_at, with the link's last_sync_state as the tie-breaker
  Disabled: no-op
```

Idempotency (G5): each outbound operation derives a stable key from (ticket, connection, operation, content-hash); a `sao_sync_operations` ledger (or a reuse of an existing idempotency primitive if Core provides one) records completion so a retry is a no-op. Status translation always goes through the binding's `status_map`; an unmapped remote status is surfaced as an explicit sync outcome, never silently defaulted.

---

## 6. Drivers in this slice

- **`internal` issues driver** (G7): network-free, backed by SAO's ticket domain (`TicketQueryService` for ACL-scoped reads, `TicketCreationService`/`WorkflowService` for writes). Its `BindingContext.config` carries the target `project` and `ticket_type`. Reads map tickets to `NormalizedIssue`; writes open/update tickets; `translateStatus` is identity over canonical categories. It is the reference every external `issues` driver is measured against.
- **Redmine issues driver** (3b-redmine): real HTTP client, API-key credential (resolved via the 3a resolver), pagination over Redmine's capped page size, status map defaults proposed from Redmine's statuses. Conformance uses committed anonymized fixtures (G8). If fixtures cannot be captured, the driver ships behind its conformance gate and the phase exit criterion is demonstrated with the internal driver plus a recorded-response double, with live validation tracked as a follow-up.

---

## 7. Authorization

Unchanged from 1a/3a: `PermissionName` permissions and Core ACL. Connection and binding management are superadmin-facing; binding a project to a connection requires a dedicated permission so a project owner cannot silently wire production to an arbitrary external system.

---

## 8. Testing

- `issues` conformance battery (from 3a) runs against the internal driver and the Redmine driver (in-memory double + recorded fixtures).
- `IssueSyncService`: direction matrix (inbound/outbound/bidirectional/disabled), idempotency (a repeated outbound op creates nothing new), and unmapped-status surfacing.
- `ProjectBinding`/`TicketLink`: uniqueness invariants; an ACL restricting binding management is honoured.
- Zero-connections scenario stays green (1a tracker unaffected).
- Anonymized real Redmine payload corpus drives normalization tests (3b-redmine).

---

## 9. Risks

| Risk | Mitigation |
|------|-----------|
| Normalized shape locked without a real consumer. | The internal driver AND the sync service are the first consumers, built together in 3b-core before Redmine. |
| No live Redmine in CI. | Recorded, anonymized fixtures committed with the driver; the offline 3b-core proves the mechanics regardless; live validation is a gated follow-up. |
| Changing capability signatures (`ConnectionContext` → `BindingContext`) breaks 3a drivers. | The 3a fake/in-memory drivers and conformance helpers are updated in the same task; the change is mechanical and covered by the existing tests. |
| Bidirectional sync loops or double-writes. | Idempotency ledger (G5) + last-sync-state tie-breaker; a forced retry test asserts no second write. |
