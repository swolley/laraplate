# Revision-Centric Aggregate History

## Status

- Date: 2026-07-29
- Status: draft for adversarial review
- Scope: generic Core versioning architecture; no module pilot is authorized by this draft
- Relationship to the current design: proposed successor to `2026-07-21-aggregate-version-sets-design.md`
- Implementation gate: paused until this document has completed cross-agent review and explicit user approval

This document exists so multiple reviewers can challenge the same concrete proposal. It does not supersede the approved operation-centric design yet, and it must not be used as authorization to continue the CMS pilot or relation implementation.

## Review instructions

Reviewers should return findings classified as `Critical`, `Important`, or `Minor`, with a file section, a concrete failure scenario, and a proposed correction.

The review should actively look for:

- hidden transaction assumptions;
- histories that appear complete but are not;
- relation changes that do not advance the root revision;
- shared records that would be mutated by an unsafe deep restore;
- raw SQL, cascades, bulk operations, and jobs that bypass capture;
- cyclic or unbounded relation snapshots;
- concurrency races during best-effort capture;
- multi-connection identity ambiguity;
- retention that could make a recorded revision unreconstructable.

## Why the previous design is being reconsidered

The operation-centric design groups scalar and relation changes in `vend_versions_sets`. It provides strong semantics when all relevant writes run inside `VersionSetManagerInterface::run()`, on one connection and in one database transaction.

That is valuable but insufficient as the universal foundation:

- not every application mutation can be assumed to run inside a managed aggregate transaction;
- converting every existing and future mutation path is difficult to prove complete;
- a relation may change without a scalar update to its root;
- users primarily want to recover a state they previously observed, not always to reverse the exact internal command that produced it;
- an operation boundary and a reconstructable state boundary are related concepts, but they are not the same concept.

The proposed design therefore makes the aggregate revision the primary restore coordinate. A version set remains an optional operation and atomicity envelope.

## Evidence already present in Laraplate

Core already provides optimistic record revisions through `HasOptimisticLocking`:

- the configured `lock_version` starts at `1`;
- an update includes the expected current version in its `WHERE` predicate;
- a successful update increments it by exactly one;
- a stale update throws `StaleModelLockingException`.

CMS `Content` already uses `HasOptimisticLocking` and has a `lock_version` column.

`HasLocks` is a separate advisory application lock based on `locked_at` and `locked_by`. A database `lockForUpdate()` is the pessimistic database lock. These three mechanisms must not be described as interchangeable.

## Goals

- Restore an aggregate to the state represented by root revision `R`.
- Record which related records and pivot states belonged to that revision.
- Allow scalar-only, link-only, owned-child, and complete restore modes.
- Preserve a version set when a managed atomic operation exists without requiring one for every history row.
- State honestly whether a captured revision is atomic or best-effort.
- Refuse complete restore when relation coverage is partial or capture is inconsistent.
- Reuse `lock_version` as the root revision where the aggregate already supports optimistic locking.
- Keep Core generic while modules explicitly declare relation ownership, identity, and capture coverage.

## Non-goals

- Pretending that an application callback can provide database atomicity after the business write committed.
- Recursively traversing every Eloquent relation.
- Automatically deep-restoring shared reference data.
- Providing distributed atomic commit across connections.
- Treating database cascades or raw relation writes as captured when they bypass registered mutation paths.
- Starting the CMS pilot before the revised Core design and plan are approved.

## Terminology

### Record version

An immutable history row describing the state transition or checkpoint of one versioned record.

### Aggregate root revision

A monotonically increasing logical revision of one aggregate root. Any authoritative scalar or registered relation change advances it.

### Relation version vector

A bounded, one-hop snapshot of the registered relation membership at a root revision. Each entry identifies the related subject, its recorded version, the pivot identity, the pivot state or pivot version, and the ownership policy.

### Version set

An optional envelope identifying the logical operation that produced one or more record versions. When created by the managed transaction boundary it also proves atomicity for the rows on that connection.

### Coverage

The set of registered authoritative relations whose mutation and restore paths have been proven. Coverage is `partial` or `complete`.

### Capture quality

- `atomic`: business rows, revision advance, version rows, and relation snapshot committed in one local transaction;
- `best_effort`: history was captured synchronously outside a shared business transaction;
- `incomplete`: capture detected a race, bypass, missing version reference, or unsupported relation.

## Proposed invariants

1. One Core writer is the only production component allowed to persist record-version rows.
2. Every aggregate revision has exactly one root type, root key, connection, and table reference.
3. Any authoritative scalar or registered relation mutation advances the root revision.
4. Complete restore is allowed only for `coverage = complete` and capture quality `atomic` or a separately validated stable best-effort snapshot.
5. A version set is required only when claiming operation-level grouping or atomicity.
6. A history row without a version set must never be presented as operation-atomic.
7. Relation vectors contain only explicitly registered one-hop relations.
8. Relation identity uses stable subject and pivot keys, never only a generated pivot ID.
9. Shared `reference` relations restore membership and pivot state automatically, but not the related record's own state.
10. `owned` relations may restore both membership and the recorded child version.
11. A restore never rewrites historical rows; it creates a new root revision and, when managed atomically, a new version set.
12. Retention removes only data whose removal preserves every advertised reconstructable revision.
13. A raw write, cascade, or bulk operation that bypasses revision advancement invalidates complete coverage for that relation.
14. Multi-connection references use globally stable version identities and never assume local numeric IDs are globally meaningful.

## Architecture alternatives

### A. Operation-first version sets

This is the current approved design. It is strongest for exact command rollback but depends on comprehensive adoption of managed transaction boundaries.

Decision: not recommended as the only restore foundation.

### B. Revision snapshots without version sets

Every root revision stores a complete relation vector. This is straightforward for state restore but loses durable operation grouping and atomicity evidence.

Decision: not recommended because Laraplate still benefits from explicit atomic operation envelopes.

### C. Revision-first with optional version sets

Root revision identifies the state. Relation vectors describe dependencies. A version set correlates rows and proves atomicity when a managed transaction exists.

Decision: recommended.

## Root revision provider

Core should depend on an `AggregateRevisionProviderInterface`, not directly on a hard-coded column.

For models using `HasOptimisticLocking`, the provider uses `lock_version`. A relation-only mutation must perform a compare-and-swap revision advance on the root even when no scalar root attribute changes.

For the first revised milestone, an aggregate that cannot provide a monotonic revision may keep scalar history but cannot advertise aggregate restore. This avoids introducing an unreviewed generic revision-head table merely to support models that have not opted into aggregate semantics.

The CMS pilot may use `Content.lock_version`, but Core contracts must not mention CMS classes or tables.

## Proposed history representation

Each root record version or root checkpoint needs:

| Field | Meaning |
|---|---|
| stable version reference | Globally unambiguous identity for cross-connection references |
| `root_revision` | Aggregate revision represented by this snapshot |
| nullable `version_set_id` | Atomic/logical operation envelope when available |
| `capture_quality` | `atomic`, `best_effort`, or `incomplete` |
| `coverage` | `partial` or `complete` |
| scalar before/after image | Existing versioning payload |
| `relation_versions` | Canonical relation version vector |

The initial representation proposed for review is a canonical JSON `relation_versions` field on the root version/checkpoint. Keys are registered relation paths, sorted deterministically. Subjects are sorted by their descriptor-defined stable identity.

Example:

```json
{
  "categories": {
    "ownership": "reference",
    "subjects": [
      {
        "subject_type": "taxonomy",
        "subject_key": {"id": 7},
        "subject_version_ref": "01J...",
        "pivot_key": {"content_id": 42, "taxonomy_id": 7},
        "pivot_version_ref": "01K...",
        "pivot_state": {"order": 1}
      }
    ]
  }
}
```

The semantic contract is the important part. A reviewer may recommend normalizing this data into a child table if JSON creates unacceptable integrity, size, or queryability problems.

## Root checkpoints

A relation-only change still advances the root revision. Because there may be no scalar root change, Core records a root checkpoint:

- scalar state is inherited from the latest scalar version at or before the new revision;
- the checkpoint stores the complete registered relation vector;
- it is not presented as a fabricated scalar change;
- restore targets the revision, not the checkpoint's internal row ID.

Full relation vectors are the baseline semantic model. Storage may later use structural sharing or deltas only if reconstruction remains deterministic and reviewers approve the optimization.

## Relation descriptors

Every registered relation declares:

- root class;
- relation path;
- stable subject identity;
- stable pivot identity;
- ownership: `reference` or `owned`;
- whether pivot attributes are authoritative;
- how mutation advances the root revision;
- how membership is captured;
- how membership and state are restored;
- known bypass paths;
- coverage evidence.

Automatic Eloquent relation discovery is forbidden.

## Capture flows

### Managed atomic flow

1. Validate the expected root revision.
2. Open the local database transaction.
3. Lock or compare-and-swap the root revision.
4. Apply scalar and relation mutations.
5. Persist record versions and the canonical relation vector.
6. Associate the rows with one version set.
7. Commit.

The resulting revision is `atomic`.

### Unwrapped best-effort flow

1. Observe or advance the root revision through the revision provider.
2. Capture scalar state and all registered relation memberships.
3. Read stable version references for every related record and pivot.
4. Re-read the root revision and any required relation revision tokens.
5. Retry if the observed vector changed during capture.
6. Persist the snapshot synchronously without claiming operation atomicity.

The resulting revision is `best_effort`. A process crash can still leave a business mutation without its history row. This limitation must be visible and must not be described as solved by optimistic validation.

If a stable snapshot cannot be proven, capture is `incomplete` and complete restore is disabled.

## Restore modes

For target revision `R`, Core may offer:

### Scalar restore

Restore only the root's scalar fields.

### Link restore

Restore registered membership and pivot attributes. Shared related records are not mutated.

### Owned-state restore

Restore membership, pivot state, and recorded versions of `owned` children.

### Complete aggregate restore

Available only when:

- the aggregate definition is complete;
- the target snapshot is reconstructable;
- no required version reference has been retained away;
- every registered relation supports restore;
- cross-connection policy permits the operation.

### Shared-reference deep restore

Never automatic. It requires an explicit impact preview because changing a shared Category, Tag, User, or Taxonomy can affect other aggregates.

## User-facing semantics

The timeline should expose:

- root revision;
- operation/version-set identity when present;
- `atomic`, `best effort`, or `incomplete`;
- `complete` or `partial` coverage;
- available restore modes;
- affected shared records before an optional deep restore.

The UI must not use the label “complete restore” when only registered links can be restored.

## Concurrency

Interactive updates and restores accept an expected root revision. A mismatch fails before mutation.

Optimistic revision checks protect against stale writers. They do not make multiple independent SQL statements atomic.

Pessimistic `lockForUpdate()` remains available inside managed transactions. Advisory `HasLocks` controls user-level editing and is independent from revision history.

## Multi-connection policy

The first revised milestone still performs atomic work on one connection only.

Relation vectors may reference versions on another connection only through globally stable references. Such a snapshot cannot claim cross-connection atomicity. Deep restore across connections remains disabled until a recovery protocol is designed and approved.

## Retention

Retention must preserve:

- every root revision still exposed to users;
- scalar reconstruction bases;
- referenced child and pivot versions;
- version sets used as revert targets;
- globally referenced versions from another connection.

A JSON relation vector creates reference-integrity obligations that ordinary foreign keys cannot enforce. This is a principal review point when comparing JSON with normalized storage.

## Effect on the current implementation

Potentially reusable:

- one synchronous Core writer;
- canonical pre-query before-image capture;
- version-set manager for explicitly atomic operations;
- stable relation descriptors;
- connection-aware contracts;
- append-only restore history.

Must be revised before further implementation:

- `version_set_id` and set-local `sequence` are currently mandatory;
- the current restore coordinate is a version set rather than a root revision;
- pivot history is modeled primarily as operation rows instead of a state vector;
- relation-only mutations do not yet define root revision advancement;
- capture quality is not represented;
- current retention work assumes set-level reachability rather than revision-vector reachability.

No existing migration or commit should be reverted until this draft is approved and a replacement implementation plan identifies the exact compatibility path.

## Required tests for an approved design

- scalar update advances root revision exactly once;
- relation-only attach, update, detach, and sync advance root revision exactly once;
- stale expected revision rejects before mutation;
- atomic capture rolls back business and history together;
- best-effort capture is never labeled atomic;
- a racing capture retries or becomes incomplete;
- reference relations restore links without mutating shared subjects;
- owned relations restore recorded child versions;
- partial coverage disables complete restore;
- raw/cascade bypass tests prevent a false complete-coverage declaration;
- deleted relation subjects and pivots remain reconstructable;
- repeated restore creates a new revision without rewriting history;
- retention cannot delete a version referenced by a retained vector;
- local IDs from different connections cannot collide in a version vector;
- cycles remain bounded to registered one-hop paths.

## Decisions requiring adversarial review

1. Is `lock_version` safe as both optimistic concurrency token and aggregate revision?
2. Should a relation-only mutation advance the root before or after its business write in best-effort mode?
3. Is JSON acceptable for `relation_versions`, or is a normalized child table required?
4. What globally stable reference identifies a version across connections?
5. How should root checkpoints interact with DIFF and SNAPSHOT scalar strategies?
6. Can a best-effort snapshot ever qualify for complete restore after stable-read validation?
7. Which bypasses must automatically downgrade aggregate coverage?
8. Is requiring a revision provider for aggregate restore acceptable for models without `HasOptimisticLocking`?
9. How does retention prove reachability efficiently when relation vectors reference other versions?
10. Which CMS category semantics are `reference` and which pivot attributes are authoritative?

## Review and approval gate

Before implementation resumes:

1. at least one independent reviewer must challenge this draft;
2. all Critical and Important findings must be resolved in the document;
3. the user must approve the revised semantics;
4. the previous Core and CMS plans must be replaced or explicitly amended;
5. a new implementation plan must identify which existing commits remain, change, or revert;
6. no CMS pilot work may start before the new Core plan passes review.
