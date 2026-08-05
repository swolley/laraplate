# Revision-Centric Aggregate History

## Status

- Date: 2026-07-29, revised 2026-07-30 and 2026-08-04
- Status: draft, blocked by unresolved Critical findings
- Scope: generic Core versioning architecture; no module pilot is authorized by this draft
- Relationship to the current design: proposed successor to `2026-07-21-aggregate-version-sets-design.md`
- Implementation gate: paused until the findings in `2026-08-04-revision-centric-aggregate-history-review.md` are resolved and the user approves

This document exists so multiple reviewers can challenge the same concrete proposal. It does not supersede the approved operation-centric design yet, and it must not be used as authorization to continue the CMS pilot or relation implementation.

The durable finding ledger is `2026-08-04-revision-centric-aggregate-history-review.md`. Reviewers update finding states there rather than referring to findings that are not preserved in the repository.

The 2026-07-30 revision does three things: it replaces the description of the existing code with what the code was verified to do, records the decisions already taken, and separates them from what is still open. Four defects surfaced while checking the draft's own premises — the optimistic locking that had never run, the per-model settings that were never read, the model observers dropped at boot, and the locking column leaking into snapshots. All four are fixed, and the sections below reflect the repaired system, not the one the first draft assumed.

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

## Current review state

The 2026-08-04 review found seven unresolved Critical and six open or proposed Important findings. Proposed resolutions for the Critical findings are now reflected below, but they remain unapproved. In particular, a callback that runs after an autocommitted business query cannot make that query and its history atomic retrospectively.

The recommended resolution is a Core-owned aggregate revision scope that starts before the first supported business query and opens a local transaction automatically when the caller did not provide one. An explicit version set remains the optional logical-operation envelope. Unsupported application mutation paths cannot advertise complete aggregate restore. Arbitrary SQL issued outside Core's supported mutation APIs cannot be detected reliably and is explicitly outside the completeness guarantee.

Three candidate paths and their trade-offs are recorded in the review log. They are proposals pending user review, not implementation authorization.

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

This section records what the code does, verified on 2026-07-30. The first revision of this document described intent rather than behaviour, and several of its claims were false.

Core provides optimistic record revisions through `HasOptimisticLocking`:

- the configured `lock_version` column starts at `1`;
- an update carries the expected current version in its `WHERE` predicate;
- a successful update increments it by exactly one;
- a stale update throws `StaleModelLockingException`;
- an update on a row whose version is `NULL` throws `MissingLockVersionException` instead of writing without the guard.

**None of this worked until 2026-07-30.** The trait declared `bootOptimisticLocking()` where Laravel looks for `bootHasOptimisticLocking()`, so the hook never ran: `lock_version` stayed `NULL` and the first update raised a `TypeError`. No test covered the trait, and the `Content` tests are skipped as requiring a full Core runtime, so it went unnoticed. Coverage now lives in `Modules/Core/tests/Integration/Locking/HasOptimisticLockingTest.php`.

CMS `Content` is still the only model carrying the trait. `cms_contents.lock_version` is now non-nullable with default `1`.

The mechanism protects a caller holding a stale in-memory instance. It does **not** yet protect a Filament form: Livewire re-hydrates the model from the database on the request that submits the form, so the value read when the form opened never reaches the update. `HasForm` injects the hidden column, but mass assignment discards it because the column is guarded. A page-level guard is missing, and `StaleModelLockingException` has no renderable, so a conflict would surface as a 500.

`HasLocks` is a separate advisory application lock based on `locked_at` and `locked_by`. A database `lockForUpdate()` is the pessimistic database lock. These three mechanisms must not be described as interchangeable.

`HasLocks` also assigns `lock_version` from `request('lock_version')` on every save, for every model carrying the trait — nine of which have no such column. No form sends that parameter today, so the code is inert, but it is a trap for the Vue frontend under construction.

## What the first review assumed, and what was true

The adversarial review of this draft rests on assumptions that did not hold. They are recorded here so its findings can be re-read correctly.

| The review assumed | Actually true until 2026-07-30 |
|---|---|
| Versioning active on 88 tables | Active on the 9 ERP models with a hardcoded `$versionStrategy`. Everywhere else `getVersionStrategy()` returned `false`: the seeder wrote `version_strategy__cms_contents` while the readers looked up `version_strategy_cms_contents`, so no per-model setting was ever found. |
| Per-model settings govern behaviour | Every reader fell back to its hardcoded default. Six capabilities went unnoticed because the default matched the configured value. |
| Saving a setting takes effect | `Setting` ran with **no observers at all** — they were dropped because the model is booted while providers are still registering, before Eloquent has an event dispatcher. The group cache was never invalidated, and the approval capture never ran. |
| `HasOptimisticLocking` works | It had never run. See above. |

All four are fixed. The consequence worth stating plainly: **versioning is now genuinely active on every table whose setting says so**, and every versionable lifecycle change on those tables produces history. That is what the settings always asked for, but it began on 2026-07-30, not before.

Two findings of the review are closed by those fixes. `C2` — a SNAPSHOT restore writing back a stale lock token — no longer applies: `getDontVersionable()` excludes the revision column for models carrying the trait. The claim that DIFF rows cannot be mapped to a revision still stands.

## Goals

- Restore an aggregate to the state represented by root revision `R`.
- Record which related records and pivot states belonged to that revision.
- Allow scalar-only, link-only, owned-child, and complete restore modes.
- Preserve a version set when a managed atomic operation exists without requiring one for every history row.
- State honestly whether a history entry is an atomic aggregate revision or best-effort audit only.
- Refuse complete restore when relation coverage is partial or capture is inconsistent.
- Keep the root revision distinct from the optimistic locking token, so a relation change never invalidates an open editing form.
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

### Aggregate revision scope

A Core-owned local transaction boundary entered before the first supported business query. It allocates at most one revision for one root, persists exactly one canonical checkpoint, and commits business state and required history together. If no outer transaction exists, Core opens one automatically.

### Relation version vector

A bounded, one-hop snapshot of the registered relation membership at a root revision. Each entry identifies the related subject, its recorded version, the pivot identity, the pivot state or pivot version, and the ownership policy.

### Version set

An optional envelope identifying the logical operation that produced one or more record versions. When created by the managed transaction boundary it identifies the atomic operation for the rows on that connection.

### Coverage

The set of registered authoritative relations whose mutation and restore paths have been proven. Coverage is `partial` or `complete`.

### Capture quality

- `atomic`: business rows, revision advance, version rows, and relation snapshot committed in one local transaction;
- `best_effort`: audit history was captured synchronously outside a shared business transaction; it is never a complete aggregate restore point;
- `incomplete`: capture detected a race, bypass, missing version reference, or unsupported relation.

## Proposed invariants

1. One Core writer is the only production component allowed to persist record-version rows.
2. Every aggregate revision has exactly one root type, root key, connection, and table reference.
3. Any authoritative scalar or registered relation mutation advances the root revision.
4. Complete restore is allowed only for `coverage = complete` and capture quality `atomic`.
5. A version set is required for operation-level grouping, but not for one atomic standalone revision scope.
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

The recommended resolution for C03 uses a durable aggregate head that survives deletion of the live root. It owns the history epoch, aggregate-definition identity, latest allocated revision, and completeness state. A dedicated `aggregate_revision` column on a live root may mirror the head for efficient reads, but it is not the sole source of truth and is **not** `lock_version` (decision 1).

A relation-only mutation performs a compare-and-swap revision advance on the durable aggregate head even when no scalar root attribute changes, inside the same transaction that writes the pivot rows.

The provider is the only component allowed to advance the revision. This is a deliberate move away from the current arrangement, where `HasOptimisticLocking::performUpdate()` increments its column as a side effect of any dirty save: that coupling is what produces revisions with no history behind them (a `touch()`, a non-versionable column, a `saveQuietly()`).

An aggregate without a registered durable head keeps scalar audit history but cannot advertise aggregate restore. Enabling aggregate history is explicit and creates a full baseline checkpoint; Core must never infer a revision of `1` from a missing head.

The CMS pilot may use `Content`, but Core contracts must not mention CMS classes or tables.

## Proposed history representation

Each canonical root checkpoint needs:

| Field | Meaning |
|---|---|
| stable version reference | Globally unambiguous identity for cross-connection references |
| `root_revision` | Aggregate revision represented by this snapshot |
| nullable `version_set_id` | Atomic/logical operation envelope when available |
| `capture_quality` | `atomic` or `incomplete`; best-effort audit rows are not canonical checkpoints |
| history epoch | Continuous interval in which complete capture remained valid |
| aggregate-definition identity | Version or canonical hash of the descriptors used to capture the checkpoint |
| `coverage` | `partial` or `complete` relative to the stored definition identity |
| scalar state | Full root state represented by this revision |

Relation-vector entries are stored as normalized child rows of the checkpoint. This permits foreign-key integrity where both versions are local, indexed reverse lookup, and retention reachability. Descriptor-defined stable keys and pivot state may remain canonical JSON payloads, with deterministic hashes for indexed equality lookup.

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

The JSON example illustrates the semantic payload of one relation entry, not the physical storage of the full vector in one history column.

## Root checkpoints

A relation-only change still advances the root revision. Because there may be no scalar root change, Core records a root checkpoint:

- the checkpoint stores the full scalar root state represented by the new revision;
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

### Atomic aggregate revision flow

1. Enter an aggregate revision scope before the first supported business query.
2. Open or join the local database transaction.
3. Lock the durable aggregate head and validate the relevant concurrency token.
4. Apply scalar and relation mutations.
5. Exit without a revision when no authoritative state changed.
6. Otherwise allocate one root revision and persist audit rows, one full checkpoint, and normalized relation entries.
7. Associate them with the active version set when an explicit logical operation exists.
8. Commit.

The resulting revision is `atomic`.

### Unwrapped legacy audit flow

An observer may still record scalar best-effort audit history after an unwrapped Eloquent write for compatibility. It does not allocate an advertised aggregate revision, does not create a canonical aggregate checkpoint, and cannot restore complete aggregate state.

An unwrapped authoritative aggregate write closes the current complete history epoch because Core cannot prove what committed with it. A process crash can still leave a business mutation without its audit row. This limitation must be visible and must not be described as solved by optimistic validation.

Re-enabling complete aggregate restore requires a new atomic full baseline checkpoint under the current aggregate-definition identity.

## Eloquent integration boundary

The recommended approach does not require every application caller to open a transaction manually:

- Core's base model persistence boundary opens or joins a revision scope before SQL for registered aggregate roots and owned models;
- version-aware `BelongsToMany` and `MorphToMany` adapters wrap complete public mutations such as `attach()`, `detach()`, `sync()`, and `updateExistingPivot()`;
- Core Pivot/MorphPivot bases join the descriptor-owned active scope;
- a deletion coordinator owns force-delete and relation draining;
- application raw SQL, query-builder bulk mutations, `saveQuietly()`, and uncoordinated cascades are prohibited for registered complete aggregates; explicit maintenance paths close the history epoch before such work;
- Core does not claim to detect arbitrary SQL issued by an operator or external process with direct database access.

The entire public relation mutation is the boundary. A `sync()` creates at most one revision for its root; its internal pivot saves do not allocate separate revisions. When a one-root version set is active, scalar and relation writes join its single revision scope.

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
- the checkpoint capture quality is `atomic`;
- the target belongs to an intact history epoch;
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

Scalar-only interactive updates accept an expected `lock_version`. Aggregate restore and membership-replacement commands accept an expected `aggregate_revision`. A command that modifies both scalar state and authoritative relations carries both tokens. A mismatch in the token relevant to that operation fails before mutation.

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

Normalized relation items carry local foreign keys where possible and indexed stable-identity hashes for reverse lookup. Cross-store references remain application-enforced and require the stable history-store and version identities described in the review log.

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
- no durable aggregate head, canonical checkpoint, history epoch, or aggregate-definition identity exists yet.

No existing migration or commit should be reverted until this draft is approved and a replacement implementation plan identifies the exact compatibility path.

## Required tests for an approved design

- scalar update advances root revision exactly once;
- relation-only attach, update, detach, and sync advance root revision exactly once;
- stale expected revision rejects before mutation;
- atomic capture rolls back business and history together;
- unwrapped audit capture never allocates a complete aggregate revision;
- an unsupported authoritative write closes the complete history epoch;
- reference relations restore links without mutating shared subjects;
- owned relations restore recorded child versions;
- partial coverage disables complete restore;
- raw/cascade bypass tests prevent a false complete-coverage declaration;
- a missing shared subject makes complete-restore preflight fail before mutation;
- an explicitly permitted owned subject can be reconstructed from its retained snapshot;
- repeated restore creates a new revision without rewriting history;
- retention cannot delete a version referenced by a retained vector;
- local IDs from different connections cannot collide in a version vector;
- cycles remain bounded to registered one-hop paths.

## Prior decisions under review

The following decisions were recorded on 2026-07-29/30. Decision 1 remains directionally accepted. Decisions 2–4 are reopened by findings C01–C07 and must not be treated as implementation-ready constraints.

**1. `lock_version` is not the aggregate revision.** Two separate logical values. `lock_version` keeps its current job — the user-facing scalar-conflict token — and the durable aggregate head's revision becomes the restore coordinate. A live-root `aggregate_revision` column may mirror that value but is not the sole source of truth.

Reusing one column would make any relation change invalidate an open editing form: attaching a tag would fail Anna's save on a field Marco never touched. It would also leave the restore coordinate reachable from `request('lock_version')`.

**2. Reopened by C01, C04, and C05.** The desired atomic invariant remains that an advertised revision and its checkpoint commit with the authoritative business mutation. The current unwrapped best-effort flow cannot provide that invariant after an autocommitted query. The review log recommends starting a Core-owned revision scope before SQL and refusing complete coverage for paths that cannot do so.

**3. Reopened by C03, C07, I01, and I02.** Database cascades remain valuable referential-integrity guards, but force delete is not limited to two centralised call sites. A complete-coverage design needs a mandatory deletion coordinator, stable subject identity, and an explicit policy for missing shared subjects.

**4. Reopened by C06 and C07.** Draining links before subject deletion remains a candidate strategy, but fan-out may affect many roots and inverse or symmetric relations may have more than one authoritative root. The first milestone must either restrict descriptors to one authoritative root or introduce a separately reviewed multi-root protocol.

## Decisions still open

1. Approve or amend candidate A from the review log: Core-owned atomic revision scopes with fail-closed unsupported writes.
2. Is a durable aggregate head required to preserve monotonic revisions across hard delete, or are hard-deleted roots explicitly non-restorable?
3. Confirm normalized relation-item storage. JSON-only relation vectors cannot provide the required reverse lookup, foreign-key integrity, or efficient retention reachability.
4. What globally stable identities represent versions, roots, subjects, and history stores across connections?
5. Confirm full scalar state on every canonical aggregate checkpoint while retaining DIFF/SNAPSHOT as record-audit strategies.
6. Can best-effort capture remain as audit-only history, with complete aggregate restore permanently disabled for that path?
7. Which bypasses are rejected at runtime, and which close the current history epoch?
8. Is requiring an aggregate revision provider acceptable for models without `HasOptimisticLocking`? The two facilities remain independent.
9. How does retention prove reachability efficiently when relation items reference other versions?
10. Which CMS category semantics are `reference`, which pivot attributes are authoritative, and which stable subject identity is used?

## Milestone 1 — confirmed scope (2026-08-05)

The user confirmed a CMS-grade, minimal, forward-compatible bundle. It resolves the blocking Critical findings for milestone 1 by narrowing scope rather than by building the full machinery, which the review log's closure gate explicitly permits.

- **Restore coordinate and hard delete (C03).** The aggregate revision is the **version set** itself (each supported operation opens one set for the root; `vend_versions_sets` is already root-keyed, monotonic, and revert-aware via `reverted_from_set_id`). No durable aggregate head is introduced. **Hard-deleted roots are non-restorable** as a continuation; the SNAPSHOT tombstone remains for audit/export and manual re-creation as a new entity. This closes C03 for the milestone.
- **Storage (C04 / I03).** Normalized, reusing the existing `vend_versions.relation_path` + `subject_key` columns: each membership entry is a version row (`versionable` = root, `relation_path`, `subject_key`, `contents` = pivot attributes). A new nullable **`subject_version_id`** (self-referential FK to `vend_versions`) references the child's version at the revision, populated **only for `owned` relations**; `reference` relations leave it null (live re-link). No JSON membership blob.
- **Identity (I01).** Local ids for milestone 1 (single connection, per the multi-connection policy). A stable **`uuid` is added to shared `reference` subjects** (CMS categories/tags, which lack an immutable natural key) as cheap forward insurance for re-link survival. A stable uuid on every version row and cross-connection atomicity are deferred.
- **Multi-root (C06)** remains out of scope for the milestone.

Preceding delivery (already implemented and tested on 2026-08-05): revert marker (`revertToVersion()` opens a `Revert`-kind version set with `reverted_from_set_id`) and delete semantics (hard delete → SNAPSHOT tombstone; soft delete → `Updated` on `deleted_at`; `trashingVersions()` scope) in `Modules/Core/app/Models/Concerns/HasVersions.php`.

Next: an implementation plan identifying the concrete slices, then TDD delivery.

## Review and approval gate

Done so far:

1. the draft has been challenged and the findings are preserved in the 2026-08-04 review log;
2. its description of the existing code has been replaced with verified behaviour;
3. four defects found while checking those premises have been fixed and covered by tests;
4. four earlier decisions are recorded above; decision 1 remains directionally accepted and decisions 2–4 are under review.

Before implementation resumes:

5. the candidate resolution path must be selected or amended by the user;
6. all seven Critical findings and the accepted Important findings must be resolved in this document and closed in the review log;
7. the user must approve the revised semantics;
8. the previous Core and CMS plans must be replaced or explicitly amended — note that the Core plan's checkboxes no longer match the repository, which is further ahead;
9. a new implementation plan must identify which existing commits remain, change, or revert;
10. no CMS pilot work may start before the new Core plan passes review.

Outside this document, two gaps block the locking work the decisions rely on: a Filament page guard that carries the expected version into the update, and a renderable for `StaleModelLockingException` so a conflict is not served as a 500.
