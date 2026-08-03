# Revision-Centric Aggregate History — Adversarial Review Log

## Status

- Date: 2026-08-04
- Target: `2026-07-29-revision-centric-aggregate-history-design.md`
- Target revision reviewed: `30b75b0`
- Status: open; implementation remains blocked
- Scope: generic Core architecture, not the CMS pilot

This log is the durable hand-off between reviewers. It records findings separately from design decisions so another agent can challenge the proposal without losing earlier reasoning.

Reviewers must not silently close a finding. A finding closes only when the design spec contains an unambiguous resolution, the evidence named here has been rechecked, and the resolution is recorded in this log.

## Severity and states

- `Critical`: the design can publish a false restore guarantee, corrupt revision ordering, or mutate the wrong aggregate.
- `Important`: the design remains unsafe, incomplete, or operationally ambiguous but does not immediately invalidate every restore coordinate.
- States: `open`, `proposed`, `resolved`, `rejected`.

## Finding summary

| ID | Severity | State | Finding |
|---|---|---|---|
| C01 | Critical | proposed | Best-effort capture cannot satisfy the same-transaction invariant |
| C02 | Critical | proposed | The concurrency section contradicts the separation of lock and aggregate revisions |
| C03 | Critical | proposed | A root-local revision counter disappears on hard delete |
| C04 | Critical | proposed | The revision allocation boundary and canonical checkpoint are undefined |
| C05 | Critical | proposed | Disabling versioning or changing descriptors can leave a falsely complete history |
| C06 | Critical | proposed | Multi-root relations do not fit the one-root revision model |
| C07 | Critical | proposed | The force-delete design assumes central mutation paths that do not exist |
| I01 | Important | open | Stable version, subject, root, and history-store identities are missing |
| I02 | Important | open | Skipping a missing subject contradicts complete restore |
| I03 | Important | proposed | JSON-only relation vectors cannot support integrity and retention safely |
| I04 | Important | proposed | Aggregate checkpoints need a scalar snapshot independent of DIFF audit rows |
| I05 | Important | open | Authorization, approvals, side-effect control, and history visibility are absent |
| I06 | Important | open | Review evidence and historical coverage epochs are not represented durably |

## Critical findings

### C01 — Best-effort capture cannot satisfy the same-transaction invariant

**Affected sections:** `Non-goals`, `Capture flows / Unwrapped best-effort flow`, and decision 2.

**Evidence:** model version writes currently run from `updated` and `deleted` callbacks. Without an already active version-set transaction, `VersionWriter` opens a transaction only after the business query has completed.

**Failure scenario:** a pivot insert commits in autocommit mode and the process terminates before revision advancement and checkpoint persistence. Leaving the revision unchanged makes the existing revision describe a state that is no longer live. Advancing it in the business query instead creates a detectable revision gap but violates the current rule that no revision may exist without a history row.

**Violated invariants:** 2, 3, and 4.

**Proposed resolution:** a registered aggregate mutation must enter a Core-owned revision scope before its first SQL statement. Core opens a local transaction automatically when no outer transaction exists. A path that cannot be intercepted before SQL is untracked, invalidates complete coverage, and cannot be repaired retrospectively by an observer. Best-effort history may remain an audit facility but cannot qualify for complete aggregate restore.

### C02 — The two concurrency tokens have contradictory consumers

**Affected sections:** `Goals`, `Concurrency`, and decision 1.

**Failure scenario:** Anna opens a scalar form at aggregate revision 10. Marco attaches a category, advancing only the aggregate revision to 11. If Anna's scalar update supplies expected aggregate revision 10 as required by `Concurrency`, it is rejected even though decision 1 introduced a separate `lock_version` specifically to avoid that rejection.

**Violated invariant:** the stated separation between editing conflicts and aggregate restore coordinates.

**Proposed resolution:** scalar forms use `lock_version`; aggregate restore and commands that replace aggregate membership use `aggregate_revision`; commands that modify both scalar state and authoritative relations explicitly carry both tokens. The API must not describe either token as a substitute for the other.

### C03 — The revision head does not survive hard delete

**Affected sections:** `Root revision provider`, `Restore modes`, and decisions 3–4.

**Failure scenario:** a root reaches revision 12 and is hard-deleted. A later recreation or restore either starts again from the column default or has no row on which to perform the compare-and-swap. Monotonicity and uniqueness are lost.

**Violated invariants:** 2, 3, and 11.

**Proposed resolution:** either keep an authoritative aggregate head/tombstone outside the live root row, or explicitly make hard-deleted roots non-restorable. A root-local `aggregate_revision` column alone cannot support both hard delete and monotonic restore history.

### C04 — The revision boundary and canonical checkpoint are undefined

**Affected sections:** `Proposed history representation`, `Root checkpoints`, and `Required tests`.

**Failure scenario:** one logical mutation saves the root, detaches two pivots, and attaches three. The draft does not say whether this produces one revision or six, when the revision is allocated, or which history row is the authoritative state after the mutation.

**Violated invariants:** 2, 3, 5, and 11.

**Proposed resolution:** one managed operation allocates at most one revision per affected root. An implicit standalone relation adapter wraps the entire public mutation such as `sync()`, not each internal pivot event. Every revision has exactly one canonical checkpoint protected by a database unique constraint on stable aggregate identity plus revision. Scalar, child, and pivot audit rows reference that checkpoint.

### C05 — Coverage can become false across configuration changes

**Affected sections:** `Coverage`, `Root revision provider`, `Retention`, and decision 2.

**Failure scenario:** versioning is disabled at revision 7, business data changes, and versioning is later re-enabled. The root still advertises revision 7 although checkpoint 7 describes an older state. Adding or changing a relation descriptor has the same problem: an old `complete` flag is interpreted against a newer aggregate definition.

**Violated invariants:** 3, 4, 12, and 13.

**Proposed resolution:** persist a history epoch and an aggregate-definition version or canonical hash. Disabling capture, detecting a bypass, or changing authoritative descriptors closes the current epoch. Re-enabling complete restore requires a new full baseline checkpoint. `complete` is always relative to the stored definition version, never a timeless boolean.

### C06 — Some relations affect more than one root

**Affected sections:** `Relation descriptors`, `Managed atomic flow`, and `Multi-connection policy`.

**Failure scenario:** removing a symmetric `Content A ↔ Content B` relation changes the authoritative vector of both contents. Advancing only A leaves B's revision stale; advancing both conflicts with the current one-root set and locking model. Moving an owned child between roots has the same shape.

**Violated invariants:** 2, 3, and 7.

**Proposed resolution:** the first milestone accepts only descriptors with exactly one authoritative root. Inverse views are explicitly non-authoritative. Symmetric relations, ownership transfers, and other multi-root mutations fail descriptor registration until a separate ordered multi-root protocol exists.

### C07 — Force delete is not centralized

**Affected sections:** decisions 3–4.

**Evidence:** production paths include Core CRUD deletion, the generic expired-model command, CMS media helpers, and module services. Builder-level force deletes can bypass per-model events entirely.

**Failure scenario:** a bulk force delete cascades registered pivot rows before Core drains and checkpoints them. No callback can reconstruct the destroyed memberships afterward.

**Violated invariants:** 3, 4, and 13.

**Proposed resolution:** complete aggregates use a mandatory deletion coordinator. Bulk and raw deletes on registered roots or subjects are rejected or explicitly invalidate coverage. Database cascades remain the final referential-integrity guard, not the normal capture mechanism.

## Important findings

### I01 — Stable identities are incomplete

The example uses local numeric IDs, while decisions rely on immutable natural identity and cross-connection version references. CMS categories do not currently expose a suitable immutable natural key.

**Proposed resolution:** add immutable stable IDs for aggregate roots and reference subjects that participate in aggregate history; add a stable UUID to each version independently from its database primary key; identify a history store with a persisted UUID rather than a Laravel connection name. A cross-store locator is `(history_store_uuid, version_uuid)`.

### I02 — Missing references cannot produce a complete restore

Decision 3 says missing shared subjects are skipped and reported, while goals and tests require reconstructability.

**Proposed resolution:** preflight a complete restore and fail before mutation when a required reference is missing. A separately requested partial link restore may skip and report. An `owned` child may be recreated only when its descriptor, authorization, and retained snapshot explicitly allow it.

### I03 — Normalize relation-vector items

JSON-only vectors cannot enforce foreign keys, indexed reverse lookup, or retention reachability. Full scans are particularly unsuitable for shared-subject deletion and cross-version retention.

**Proposed resolution:** use a canonical checkpoint header and normalized relation-item rows. JSON remains acceptable for descriptor-defined stable key payloads and pivot state, accompanied by deterministic hashes where indexed lookup is required.

### I04 — Checkpoints need full scalar state

DIFF rows are useful for audit but create complex and fragile checkpoint reconstruction and retention dependencies.

**Proposed resolution:** every canonical aggregate checkpoint stores the full scalar root state represented by the revision. Existing DIFF or SNAPSHOT record versions remain audit rows and can use their configured strategy independently.

### I05 — Restore security and side effects are unspecified

The predecessor spec required authorization, approval-aware application, scoped restoration mode, and after-commit reconciliation. The successor draft does not preserve those contracts or define who may read potentially sensitive history.

**Proposed resolution:** carry those contracts forward explicitly. Authorize history visibility and each restore mode; preserve approval attribution; never use global `withoutEvents()`; reconcile derived data after commit; preview shared-record impact before any exceptional deep restore.

### I06 — Review and epoch evidence are not durable

The target spec references finding `C2` without preserving the original finding list. Coverage evidence is also described but has no durable descriptor version or proof record.

**Proposed resolution:** keep this log as the finding ledger and store aggregate-definition identity with every checkpoint. Each review pass updates finding state and cites the spec revision that resolved it.

## Candidate resolution paths

### A. Core-owned atomic revision scopes — recommended

Core intercepts every supported aggregate mutation before SQL and opens a local transaction if necessary. One canonical checkpoint is persisted with the business mutation. For the one-root milestone, an explicit version set owns one aggregate revision scope and all nested writes join it; without a version set, one supported public mutation call owns one implicit scope. Callers do not need to create a database transaction themselves.

This provides truthful restore semantics but requires registered mutation adapters and a fail-closed policy for raw or bulk writes.

### B. Detectable best-effort gaps

The business query advances the aggregate revision, and history follows afterward. A crash leaves a detectable missing revision. Restore remains available only up to the last intact checkpoint and the aggregate is visibly degraded until repaired.

This supports more legacy writes but cannot promise complete restore for those mutations.

### C. Database triggers or transactional outbox reconstruction

The database records mutations independently from Eloquent. This covers raw writes better but introduces database-specific trigger logic, makes semantic relation ownership harder to express, and still requires a later checkpoint builder.

This is not recommended as the generic multi-database baseline.

## Eloquent integration without rewriting every call site

Candidate A does not require adding `DB::transaction()` around every existing caller. It requires a small number of framework boundaries that run before SQL:

1. Core's base model persistence boundary opens or joins a revision scope for a registered aggregate root or owned model. Existing `save()`, `create()`, `update()`, `delete()`, and `restore()` calls continue to use Eloquent.
2. A version-aware `BelongsToMany`/`MorphToMany` adapter wraps public relation mutations such as `attach()`, `detach()`, `sync()`, and `updateExistingPivot()`. The entire `sync()` is one scope; internal pivot saves join it instead of creating one revision per row.
3. Core's Pivot/MorphPivot bases join the active descriptor-owned scope and provide direct-pivot protection.
4. A deletion coordinator owns force-delete and relation draining for registered aggregates.
5. Query-builder bulk updates/deletes, application raw SQL, `saveQuietly()`, and database cascades cannot silently qualify as complete capture. They are prohibited on complete aggregates or require an explicit maintenance path that closes the current history epoch.

This concentrates refactoring in Core persistence boundaries and module relation declarations. It does not make arbitrary SQL issued by an operator or external process interceptable, and the design must not claim otherwise; direct database writes remain outside the completeness guarantee.

## Recommended target flow

1. A scalar save, relation adapter, restore, or deletion coordinator enters an aggregate revision scope.
2. Core opens or joins the root connection transaction before the first business query.
3. Core locks the durable aggregate head and validates the relevant concurrency token.
4. The mutation executes.
5. If nothing authoritative changed, the scope exits without allocating a revision.
6. Otherwise Core allocates one root revision, writes audit versions, writes one full checkpoint, and writes normalized relation items.
7. If an explicit version set is active, all nested mutations of the one root share this scope and the checkpoint and audit rows carry its operation identity.
8. The local transaction commits business state and history together.
9. After-commit handlers reconcile caches, search, projections, and permitted external effects.

Any path unable to enter at step 1 is not complete aggregate versioning. It may retain scalar audit history, but it cannot advertise a complete restore point.

## Closure gate

Before this review can close:

1. the user selects or amends one candidate resolution path;
2. the design spec incorporates that path without contradicting its invariants;
3. every Critical finding is resolved or explicitly removed from the milestone by narrowing scope;
4. Important findings have accepted dispositions;
5. another reviewer rechecks the revised spec and this log;
6. only then may an implementation plan be written.
