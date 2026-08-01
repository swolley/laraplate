# SAO phase 1a — internal ticketing core design

**Date:** 2026-08-01
**Module:** `Modules/SAO`
**Parent spec:** `docs/superpowers/specs/2026-07-31-sao-module-design.md` (phase 1)
**Status:** Design agreed. No implementation started.

---

## 1. Purpose

Phase 1 of the SAO roadmap is "internal ticketing core". Scoping it revealed that it is not one
project but three, so it is delivered as three slices, each independently usable:

| Slice | Content |
|-------|---------|
| **1a** — this spec | Projects, ticket keys, tickets, types, workflow schemes with enforced transitions, comments, history, permissions, Filament surfaces |
| **1b** | Labels, watchers, attachments, due dates, ticket relations, advanced search |
| **1c** | Kanban board |

**1a is the slice that unblocks phase 2.** Once it exists, an error can become a ticket. 1b and 1c
are enrichment and can be deferred, reordered, or dropped in favour of moving to phase 2.

The deliverable is a tracker a person can actually work in: open a ticket, assign it, move it
through a workflow, discuss it, and close it — with **no connection configured**, which is decision
D3 of the parent spec.

---

## 2. Locked decisions

| # | Decision | Rationale |
|---|----------|-----------|
| E1 | Phase 1 is delivered as slices 1a / 1b / 1c | One spec covering all of it would be unreadable and nothing would be runnable until all of it was approved. |
| E2 | Statuses are **global per installation**, each carrying a canonical category (`open`, `in_progress`, `resolved`, `closed`, `rejected`) | Defining "In review" once and reusing it beats eight near-duplicates. The category is what phase 3 maps against and what phase 6 reasons about. |
| E3 | Workflow schemes are first-class and **shareable** across ticket types | Per-type workflows were requested. Shareable schemes are the escape valve that stops every new type from spawning a new scheme. |
| E4 | Transitions are **enforced in the domain service**, not merely hidden in the UI, with an override permission | The API and phase 2's automation move tickets too. A configuration mistake must not be able to deadlock everyone's work. |
| E5 | Ticket types are global, enabled per project through a pivot, and carry an `is_defect` flag | Phase 2 needs to know which type to create from an error without matching on a human-editable label. |
| E6 | Priority is a **fixed enum** (`low`, `normal`, `high`, `urgent`), not configurable | Priority needs no transitions and no canonical mapping separate from its name — it is already canonical. Making configurable what does not ask for it is how data models bloat. |
| E7 | Per-project ticket key (`PREFIX-123`); the prefix is **immutable** once the first ticket exists | Keys end up in commit messages, which phase 5 parses. A changing prefix makes already-written history unreadable. |
| E8 | One comment entity, with a nullable author and a `human`/`system` origin | Phase 2 posts automated comments. A bot user would be assignable, appear in filters, and be impersonable. |
| E9 | History is a **read model** over Core versions plus comments. No activity table in 1a | Core's `HasVersions` in diff strategy already records which attributes changed and by whom. |
| E10 | Every ticket mutation accepts a **change context** from day one | Phase 3 brings remote actors, which Core versioning cannot attribute. The seam costs one parameter now and avoids touching every call site later. |
| E11 | Authorization is **exactly Laraplate's**: `Core\Support\PermissionName` plus spatie for permissions, and Core's `ACL` filters for row-level visibility. SAO adds no mechanism of its own | The ACL chain is implemented and wired into Core's CRUD, grid and graph layers. Per-project visibility is therefore an ACL to configure, not code to write. |
| E12 | No integration of any kind | Connections, bindings and drivers are phase 3. 1a must not contain a single line that anticipates them. |

---

## 3. Non-goals

Deferred to 1b: labels, watchers, attachments, due dates, ticket-to-ticket relations (blocks,
duplicates, relates), advanced search and saved filters.

Deferred to 1c: the kanban board.

Deferred to later phases: everything involving an external system, error signals, and AI.

Explicitly **not** built at all in 1a:

- A project membership pivot, or any visibility mechanism of SAO's own. Row-level visibility is
  Core's ACL chain, configured rather than coded (E11, §8).
- An activity/audit table parallel to Core versions (E9).
- A bot user for automation (E8).

---

## 4. Domain model

Tables use the `sao_` prefix through a `SAOTables` enum with
`Modules\Core\Enums\Concerns\HasModuleTablesUtils`, matching `CMSTables` and `ERPTables`. Models
extend `Modules\Core\Overrides\Model`, which provides soft deletes, validations, versioning and
automatic table-name prefixing.

### Configuration entities

Few rows, rarely changed.

| Entity | Fields | Notes |
|--------|--------|-------|
| `TicketStatus` | name, colour, sort order, **canonical category** | Global. The category is a PHP enum: `open`, `in_progress`, `resolved`, `closed`, `rejected`. |
| `WorkflowScheme` | name, description, `is_default` | Shareable across types. Exactly one default. |
| `WorkflowTransition` | scheme, `from_status` (nullable = initial), `to_status`, action label, optional required permission | The transitions **define** which statuses belong to a scheme; no separate membership table. A nullable `from_status` is the creation transition, and every scheme needs at least one. |
| `TicketType` | name, slug, icon, colour, workflow scheme, `is_defect` | Global. `is_defect` is the machine-readable hook phase 2 uses. |

### Working entities

| Entity | Fields | Notes |
|--------|--------|-------|
| `Project` | name, key prefix, description, active flag, next-number counter | No external bindings — those are phase 3. |
| `ProjectTicketType` | project, ticket type, `is_default`, optional workflow scheme override | How types are "configurable per project" without duplicating their definition. The nullable override answers "type X must behave differently in project Y" with one column instead of a duplicated type; when null, the type's own scheme applies. |
| `Ticket` | project, number, key, type, status, priority, title, description, reporter, assignee, `lock_version` | Optimistic locking matters here more than anywhere: tickets are the most concurrently-edited object in the system. |
| `TicketComment` | ticket, author (nullable), origin, source key (nullable), body | System comments are immutable from the UI. |

---

## 5. Workflow and transitions

A ticket's permitted moves come from the workflow scheme resolved for its **type within its
project**: the project-level override if one is set, otherwise the type's own scheme. The domain
service is the only place that moves a ticket:

1. Resolve the scheme for the ticket's (project, type) pair.
2. Find a transition from the current status to the requested one.
3. If none exists, reject — unless the actor holds the override permission.
4. If the transition declares a required permission, check it.
5. Apply the change within the change context (§7).

The UI asks the same service which transitions are available and renders exactly those. The UI never
computes the answer itself, because phase 2's automation will move tickets without any UI involved.

**Creation** uses the transition whose `from_status` is null: that is how a scheme declares its
initial status, and it means "which status does a new ticket start in" is configuration rather than
code.

---

## 6. Ticket key allocation

Each project holds a prefix and a counter. On creation, inside the same transaction as the insert:
lock the project row, read and increment the counter, insert the ticket with its number and its
denormalized key under a unique index.

**Gaps are accepted.** A rolled-back transaction loses its number. Making the sequence gapless would
require serializing every creation, which no serious tracker does.

**The prefix is immutable once the first ticket exists** (E7). Enforced by a model rule, not left to
convention.

Concurrency is the one place in 1a where a race produces corrupt data — two tickets sharing a key —
so it is tested with genuinely concurrent creation, not with a mocked lock.

---

## 7. History and change context

There is no activity table. The ticket timeline is a **read model** merging two sources that already
exist: comments, and Core versions in diff strategy, which record which attributes changed and by
whose hand.

Every mutating operation on a ticket accepts a `ChangeContext` value object describing **who** acted
and **on behalf of what**:

- a local user;
- a local automation, identified by a source key;
- (from phase 3) a remote connection, with an external actor identity and the originating event.

In 1a the context only determines authorship attribution. Its persistence beyond what versioning
captures is deliberately deferred: remote actors do not exist yet, so there is nothing to record and
nothing to backfill. What matters is that the parameter exists on every call site from the start.

**Known risk.** If building a timeline by querying versions turns out to be slow or awkward in
practice, the remedy is a dedicated activity table. It is not built pre-emptively, but it is the
first thing to revisit if the ticket page becomes slow.

---

## 8. Authorization

Permission names come from `Core\Support\PermissionName` — the `{connection}.{table}.{operation}`
convention — with operations beyond CRUD where the domain needs them: `transition`, `assign`,
`transition_override`.

Row-level visibility uses Core's existing ACL chain, unchanged. `AclResolverService` resolves the
effective ACLs for a (user, permission) pair — inheriting along the role hierarchy, combining
non-hierarchical roles with OR, treating `unrestricted` ACLs as transparent, and honouring priority
— and `AuthorizationService` applies the resulting `FiltersGroup` through `applyAclFiltersToQuery()`
and `injectAclFilters()`. That chain is already wired into `Crud\CrudService`, `Crud\QueryBuilder`,
the grid actions and `GraphService`.

**Per-project visibility is therefore configuration, not code**: an ACL carrying a filter on the
ticket's project, attached to the relevant permission. SAO ships no membership table and no scope of
its own.

**The rule this imposes on SAO.** `HasACL`'s model-level global scope is an unimplemented TODO, so
ACL filtering is *not* automatic at the Eloquent level. Every SAO read path must go through Core's
CRUD/grid layer, or call `applyAclFiltersToQuery()` explicitly. A domain service that queries
tickets with raw Eloquent silently bypasses visibility — which is exactly the kind of hole that is
invisible until it matters. A test asserts that the ticket read paths honour a restricting ACL.

---

## 9. Filament surfaces

Resources for project, ticket, status, type and workflow scheme, with transitions as a relation
manager inside the scheme. Generated with Laraplate's `filament:make-resources` and the
`HasTable`/`HasForm` traits rather than written by hand.

The ticket page shows the merged timeline, the comment composer, and the **permitted transition
actions as computed by the domain service**.

Per the parent spec's boundary: no orchestration logic in a Filament resource. Removing the panel
must leave a fully functional module.

---

## 10. Testing

- **Unit:** transition validation including the override path; the initial-transition rule; the
  immutable-prefix rule; canonical category mapping.
- **Concurrency:** two simultaneous ticket creations against one project must produce two distinct
  keys. This is the one test in 1a that must exercise real concurrency.
- **Feature:** permission enforcement per operation; Filament pages render; the domain service
  refuses a transition the UI would not have offered.
- **Visibility:** with an ACL restricting the relevant permission to one project, every ticket read
  path returns only that project's tickets. This is the test that catches a read path built on raw
  Eloquent, which would bypass the filters silently (§8).
- **Standalone scenario:** the complete work flow — create, assign, transition, comment, close —
  with no connection configured, because in 1a that is the only mode there is.

---

## 11. Risks

| Risk | Mitigation |
|------|-----------|
| Per-type workflows multiply into unexplainable configuration | Schemes are shareable and there is a default; a type without an explicit scheme uses it |
| Timeline from versions proves slow | Documented fallback to an activity table (§7); the read model is a single service, so swapping its source touches one file |
| Key allocation race | Row lock inside the creating transaction, plus a genuine concurrency test |
| 1a grows to absorb 1b | The non-goals list (§3) is explicit, and each deferred item names its slice |
