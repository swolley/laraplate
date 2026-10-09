# Revision-Centric Aggregate History

## Status

- Date: 2026-07-29, revised 2026-07-30, 2026-08-04 and 2026-10-09
- Status: draft aligned with the decisions of 2026-10-09 (D1 to D27 in the review log); the Critical findings are resolved by decision, closure pending approval of this text
- Scope: generic Core versioning architecture; no module pilot is authorized by this draft
- Relationship to the current design: proposed successor to `2026-07-21-aggregate-version-sets-design.md`; the version-set and transaction machinery of that design stays as the carrier of atomic capture (D16)
- Implementation gate: see "Review and approval gate"

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

On 2026-10-09 the findings were discussed with the user and decided. The decisions (D1 to D27), with the evidence behind them, are in the review log `2026-08-04-revision-centric-aggregate-history-review.md`. All nine Critical findings (C01 to C09) are resolved by decision or by the milestone 1 scope, and the Important findings are resolved or deferred (I01). A finding closes only when this text carries the resolution and the evidence is rechecked once implemented, so the log still lists them as "spec to align".

This revision applies those decisions to the text. A section that still needs design work that has not been done says so, and the open items are gathered under "Design still to produce". A reference to "D<n>" is a decision of the review log.

## Why the previous design is being reconsidered

The operation-centric design groups scalar and relation changes in `core_versions_sets`. It provides strong semantics when all relevant writes run inside `VersionSetManagerInterface::run()`, on one connection and in one database transaction.

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

- Revert an aggregate to the state represented by root revision `R`: its columns, its owned parts (translations first) and the membership of its shared references.
- Record which related records and pivot states belonged to that revision.
- Offer scalar-only, link-only and complete revert modes. A revert never touches shared reference data.
- Preserve a version set when a managed atomic operation exists, without requiring one for every history row.
- State honestly whether a history entry is an atomic aggregate revision or best-effort audit only, and never offer "restore everything" on anything else (D6).
- Refuse a complete revert when coverage is partial, capture is inconsistent, or the schema or the aggregate definition changed since the revision (D13).
- Keep the root revision distinct from the optimistic locking token, so a relation change never invalidates an open editing form (D23).
- Restrict who can read the history and who can revert it (D26, D27).
- Keep Core generic while modules explicitly declare relation ownership, identity and capture coverage.

## Non-goals

- Pretending that an application callback can provide database atomicity after the business write committed.
- Recursively traversing every Eloquent relation.
- Automatically deep-restoring shared reference data.
- Providing distributed atomic commit across connections.
- Treating database cascades or raw relation writes as captured when they bypass registered mutation paths.
- Versioning or reverting what is declared outside the guarantee (see "Excluded from the guarantee"): translations on their own, comments, ratings, `RecordOrigin`, lock columns, media files, relations with more than one authoritative root.
- Ageing the history of live records on its own: compaction is manual (D18).
- Starting the CMS pilot before the revised Core design and plan are approved.

## Terminology

### Record version

An immutable history row describing the state transition or the starting image of one versioned record. Its only sanctioned mutation is a compaction, which marks the rewritten row with `compacted_at` (D18).

### Aggregate root revision

A monotonically increasing logical revision of one aggregate root. Any authoritative scalar, owned-part or registered reference-membership change advances it. In milestone 1 the revision is the version set (see "Root revision provider").

### Aggregate revision scope

A Core-owned local transaction boundary entered before the first supported business query. It allocates at most one revision for one root and commits business state and required history together. If no outer transaction exists, Core opens one automatically. The writes of one logical operation (for example a root and its translations) join the same scope (D10).

### Relation membership

The registered one-hop relation entries of a root at a revision. Each entry is a version row keyed by `relation_path` and `subject_key`, whose contents are the pivot attributes. Only relations with exactly one authoritative root are registered (D24).

### Owned part

Data that has no meaning without its root and is part of the document: the translations of a content. An owned part has no history of its own; its state is part of the owner's revision (D10). A child with an independent history could instead be referenced by its own version id; none is known today.

### Reference

A shared record linked to the root (category, tag, location, contributor). A revert restores the membership and the pivot state, never the record itself (D2).

### Version set

An optional envelope identifying the logical operation that produced one or more record versions. When created by the managed transaction boundary it identifies the atomic operation for the rows on that connection. A revert is recorded as a set of kind `Revert` that points to the set it restores (`reverted_from_set_id`).

### Anchor

A complete image of the versionable columns of a record, stored as a `SNAPSHOT` row: the creation row, the forced snapshot at the first save after versioning is switched on again, the first write after a schema epoch change, and the row rewritten by a snapshot compaction. A state is reconstructed from the nearest anchor at or before the target (D18, C08, C09).

### Epoch

An interval in which the versionable columns and the registered relations of a table did not change. Every version row stores the epoch it was written in; an epoch carries a fingerprint of that definition (D13).

### Coverage

The set of registered authoritative relations whose mutation and revert paths have been proven. Coverage is `partial` or `complete`, always relative to the definition fingerprint of the epoch, never a timeless flag.

### Capture quality

- `atomic`: business rows, revision advance, version rows and membership committed in one local transaction;
- `best_effort`: audit history captured outside a shared business transaction, or a write that bypassed Core; it is never a complete restore point;
- `incomplete`: capture detected a race, a bypass, a missing version reference or an unsupported relation.

A revision can carry further labels that withhold "restore everything": `partial` (missing references, D25), `schema-mismatch` and `definition-mismatch` (the columns or the relations changed since, D13).

### Restore and revert

"Restore" is reserved for the bin: the root leaves it (`restored`). "Revert" is the operation on revisions: the document returns to an earlier state, recorded as a new revision in a `Revert` set (D20). The recovery of a hard-deleted root from its snapshot (D17) needs the `restore` permission.

## Invariants

1. One Core writer is the only production component allowed to persist record-version rows.
2. Every aggregate revision has exactly one root type, root key, connection and table reference.
3. Any authoritative scalar, owned-part or registered reference-membership mutation advances the root revision.
4. A complete revert is allowed only for `coverage = complete`, capture quality `atomic`, and an epoch whose definition equals the current one.
5. A version set is required for operation-level grouping, but not for one atomic standalone revision scope.
6. A history row without a version set must never be presented as operation-atomic.
7. Membership contains only explicitly registered one-hop relations with exactly one authoritative root.
8. Relation identity uses stable subject and pivot keys, never only a generated pivot id.
9. Shared `reference` relations restore membership and pivot state, but not the related record's own state.
10. Owned parts have no history of their own: their state is part of the owner's revision, and a revert recreates or removes them by natural key (the locale for a translation).
11. A revert never rewrites historical rows; it creates a new revision in a `Revert` set. The only sanctioned mutation of a version row is compaction.
12. Removing history is always one of the sanctioned, explicit operations: hard delete, compaction, or switching versioning off with a user confirmation. Retention never removes data that an advertised revision needs.
13. A raw write, cascade or bulk operation that bypasses the revision scope is `best_effort` by definition and never counts as covered.
14. Multi-connection references use globally stable version identities. Deferred with I01: milestone 1 runs on one connection with local ids.
15. Anchor invariant: the earliest retained row of every versioned record is a complete image of its versionable columns, including the columns that took a database default.
16. A revision is reconstructed from the nearest anchor at or before it, applying the following rows forward in revision order and driven by each row's own stored strategy, never by the model's current one.
17. What lies outside the guarantee is declared, not silently missing.

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

**Confirmed by the user on 2026-10-09 (D16):** the restore coordinate is the observed state ("the document as it was at revision R"), not the executed operation. The version set remains the optional envelope that groups the writes of one logical operation and carries atomic capture.

## Root revision provider

Core depends on an `AggregateRevisionProviderInterface`, not on a hard-coded column.

**Milestone 1.** The revision of an aggregate is its version set: each supported operation opens one set for the root, the set is root-keyed and monotonic, and `reverted_from_set_id` already makes it revert-aware. No durable aggregate head exists. `currentRevision()` is the highest set of the root, and it is `null` for a record without versions, for example after versioning was switched on again and before the record's first save (D22).

The provider is the only component allowed to advance the revision. This is a deliberate move away from the arrangement where `HasOptimisticLocking::performUpdate()` increments its column as a side effect of any dirty save (a `touch()`, a non-versionable column, a `saveQuietly()`): that coupling produces revisions with no history behind them. The revision is not `lock_version` (D23).

A relation-only mutation advances the revision inside the same transaction that writes the pivot rows.

**After milestone 1.** A durable aggregate head that survives the live root, optionally mirrored by a column, stays the candidate for continuity across deletion and recreation. It is not needed while a hard-deleted root is recovered from its snapshot (D17).

The CMS pilot may use `Content`, but Core contracts do not mention CMS classes or tables.

## History representation

The tables are `core_versions_sets` and `core_versions` (D15). Both are append-only: no `deleted_at`, `is_deleted` or `updated_at` (D19); `created_at` stays, because it orders previous and next versions and can be forced by the writer. `Version` and `VersionSet` declare `UPDATED_AT = null`, otherwise Eloquent would still write the column on every insert. `MigrateUtils::timestamps()` gets an optional parameter to omit `updated_at`; its default does not change. `Version` no longer uses `SoftDeletes`: removing a row is a real `DELETE`, performed only by the sanctioned operations of invariant 12.

Each version row carries:

| Field | Meaning |
|---|---|
| `version_set_id`, `sequence` | The operation envelope and the position inside it |
| `change_type` | `created`, `updated`, `trashed`, `restored`, `deleted` (D20) |
| `relation_path`, `subject_key` | Present on membership rows, where `created` is an attach and `deleted` a detach |
| `versionable_type`, `versionable_id`, `connection_ref`, `table_ref` | The root |
| the user | The actor |
| `original_contents`, `contents` | The change, or the whole image for an anchor |
| `version_strategy` | `DIFF` or `SNAPSHOT`, per row |
| the schema epoch | The epoch of the table at write time (D13) |
| `compacted_at` | Set when compaction rewrote the row (D18) |

`change_type` takes the names of the Eloquent lifecycle events. `trashed` is a root going to the bin and `restored` leaving it; `deleted` is a row that is physically gone: for a root the snapshot tombstone of a hard delete (D17), for membership a detach. A revert is not a `change_type`: it is recorded at set level and the rows inside stay `updated`. A compaction is not one either.

Membership entries are version rows, normalized, so reverse lookup and retention reachability use indexed columns. Descriptor-defined stable keys and pivot state stay in the JSON of `subject_key` and `contents`.

Translations are not stored as rows of their own history. The state of an owner's translations is carried by the owner's revision, with the same weight as the owner: a difference when the owner uses `DIFF`, in full when it uses `SNAPSHOT` (D10, D11). The exact representation is design still to produce.

The hard-delete snapshot, the compaction rewrite and the forced snapshot at the first save are `SNAPSHOT` rows; a creation row is one too.

## Anchors and reconstruction

**Complete image.** An anchor holds every versionable column of the record. The creation row used to hold only the attributes assigned at insert (`createInitialVersion()` stores `getAttributes()`), so a column that took a database default was missing; the row is read back after the insert instead (C09). The same completeness applies to the forced snapshot after switching versioning on again.

**Reconstruction (C08).** To rebuild the state as of revision R, walk back from R to the nearest anchor, then apply the following rows forward in revision order, last write wins per column, and write the result once. Nothing older than that anchor is read or merged. Example, newest first: `diff, diff, SNAPSHOT, diff, diff, diff, diff, SNAPSHOT, diff`. Reverting to the farthest row starts from the second `SNAPSHOT` and applies one row; reverting to the newest starts from the first `SNAPSHOT` and applies two. The walk is driven by each row's own `version_strategy`, because a history mixes `DIFF` and `SNAPSHOT` rows. If no anchor exists at or before R, the revision cannot be rebuilt and the revert is refused. `revertToVersion()` and `revertToRevision()` use this one function; the `DIFF` replay of the library, which merged each earlier row over the live row, is not used (D14).

**Anchor invariant.** The earliest retained row of every versioned record is an anchor. It holds at creation, when versioning is switched on again (a forced snapshot at the record's first save, taken after that save, D22), at the first write after a schema epoch change (lazy baseline, D13), and after compaction (D18). A check reports every record whose earliest retained row is not an anchor.

**Relation-only changes.** They advance the revision and write membership rows. When no scalar column changed, no fabricated scalar change is written.

A restore targets the revision, never the internal id of a row.

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

Ownership is one of `reference` or `owned`. In milestone 1 the only owned parts are translations, which have no history of their own (D10); a descriptor that registers more than one authoritative root is rejected (D24).

## Excluded from the guarantee

Declared, so that a revert never silently misses them (D21, D8, D24):

| Data | Why | Effect of a revert |
|---|---|---|
| `RecordOrigin` | Import bookkeeping and identity registry (external id to local record); no CRUD permissions, written by the importers only | Untouched. Removed with the record at a hard delete, and carried in the hard-delete snapshot so a recovered record keeps its mapping |
| Lock columns (`locked_at`, `locked_user_id`, `locked_until`) | Transient coordination state, written without events | Untouched; excluded from the versionable columns of every model with `HasLocks`, as `lock_version` already is |
| Media (Spatie) | Own lifecycle and files; its rows keep their own metadata history | Untouched in milestone 1; to resume later as an ordered membership per collection |
| Comments and ratings | Contributions of third parties, not part of the document | Untouched, never versioned; their models force versioning off |
| Symmetric or multi-root relations (`Content::related()`) | More than one authoritative root | Untouched; registering them is rejected |

A recovery of a hard-deleted record does not bring back comments, ratings or media files.

## Capture flows

### Atomic aggregate revision flow

1. Enter an aggregate revision scope before the first supported business query.
2. Open or join the local database transaction.
3. Validate the concurrency token relevant to the operation (D23).
4. Apply scalar, owned-part and relation mutations; the writes of one logical operation join the same scope (design to produce, D10).
5. Exit without a revision when no authoritative state changed.
6. Otherwise allocate one revision and persist the version rows and the membership rows.
7. Associate them with the active version set when an explicit logical operation exists.
8. Commit.

The resulting revision is `atomic`. A change to a translation alone also creates a version of the owner, and increments the owner's `lock_version` (D10, D23).

### Writes outside the scope

An observer may still record scalar audit history after an unwrapped Eloquent write for compatibility. That revision is labelled `best_effort`: it does not allocate an advertised aggregate revision and never offers "restore everything". A process crash can still leave a business mutation without its audit row; this limitation is visible and is not described as solved by optimistic validation. A write that bypasses Core entirely (query builder, raw SQL, database cascade) cannot be detected in general and is `best_effort` by definition (D6).

The paths that lack the scope today are the Filament saves, `CrudService::insert`, and the save of a root together with its translations (`savePendingTranslations` runs on `saved`, after the root).

### Switching versioning off and on

Switching versioning off for a table deletes its history, after an explicit confirmation that names what is lost. The hard-delete snapshots of that table are kept until their own expiry. Switching it on forces a complete snapshot at each record's first save, taken after that save; until then `currentRevision()` is `null` (D22).

## Eloquent integration boundary

The recommended approach does not require every application caller to open a transaction manually:

- Core's base model persistence boundary opens or joins a revision scope before SQL for registered aggregate roots;
- version-aware `BelongsToMany` and `MorphToMany` adapters wrap complete public mutations such as `attach()`, `detach()`, `sync()` and `updateExistingPivot()`;
- Core Pivot and MorphPivot bases join the descriptor-owned active scope;
- translation writes join the scope of their owner (design to produce);
- a hard-delete coordinator owns every force delete: it writes the full snapshot first, removes the history and the root in one transaction, and handles the draining of links (D17). `ClearExpiredModels` stops deleting at builder level without a snapshot; deleting in chunks is acceptable;
- query-builder bulk mutations, `saveQuietly()` and uncoordinated cascades are `best_effort` by definition for registered aggregates;
- Core does not claim to detect arbitrary SQL issued by an operator or an external process with direct database access.

The entire public relation mutation is the boundary. A `sync()` creates at most one revision for its root; its internal pivot saves do not allocate separate revisions.

## Revert modes

For target revision `R`, Core may offer:

### Scalar revert

Revert only the root's columns, rebuilt as described in "Anchors and reconstruction".

### Link revert

Revert the registered membership and pivot attributes. Shared related records are not mutated. A reference that no longer exists is skipped and reported, and the result is labelled `partial` (D25).

### Owned-part revert

Revert the owner's translations: the rows of the revision are recreated, updated or removed by locale. A translation added after R is removed; one deleted after R is recreated.

### Complete revert

Available only when:

- the aggregate definition is complete and equal to the current one (no `schema-mismatch`, no `definition-mismatch`);
- the capture quality is `atomic`;
- the target belongs to an intact epoch;
- the state of R is reconstructable from an anchor;
- no required reference is missing (a run with `$force` skips and reports them, and is `partial`);
- every registered relation supports the revert.

A revert towards an older epoch applies the columns still compatible, skips and reports the ones that no longer exist, and refuses when recreating a row would miss a required value; a column that disappeared makes the library's `revert()` fail with a raw query error, so the check is ours and runs before the write.

### Shared-reference deep restore

Never automatic. It requires an explicit impact preview because changing a shared category, tag, user or taxonomy can affect other aggregates.

### Recovery of a hard-deleted root

A hard-deleted root is recreated from its snapshot until the snapshot expires (D17). It needs the `restore` permission and does not bring back comments, ratings or media files.

### Authorization and side effects (D26)

A revert needs the new `revert` permission, which implies `history`, and follows the approvals of the model like an update, with the actor attributed. It is refused if the record is locked by another user. Model events fire so search and embeddings realign after commit; automatic translation does not run during a revert (a scoped revert context, not a global `withoutEvents()`), and the approvals subsystem does not create a modification for the writes the revert performs internally.

## User-facing semantics

The timeline exposes:

- the root revision;
- the operation or version-set identity when present;
- `atomic`, `best effort` or `incomplete`, and the labels `partial`, `schema-mismatch`, `definition-mismatch`;
- `complete` or `partial` coverage;
- the available revert modes;
- the affected shared records before an optional deep restore.

The UI must not use the label "complete restore" when only registered links can be reverted.

Reading the history of a record needs the permission `history`, in addition to being able to read the record; the history is always reached through the root and its ACL, never by reading the versions table on its own. `revert` implies `history`. The snapshots of hard-deleted records are read and recovered only with `restore` (D27). The ticket timeline of SAO stays on the permission of the ticket for now.

## Concurrency

Two counters, with separate jobs (D23):

- `lock_version` protects the form. It covers what the form edits: the root and, since the text lives in them, its translations. A translation write increments the root's `lock_version`, at the same point where the owner's version is created. Relation changes do not increment it, as long as the form does not show them.
- The aggregate revision protects the revert and covers everything, relations included. A revert and a membership-replacement command accept an expected revision; a command that modifies both columns and authoritative relations carries both tokens. A mismatch in the token relevant to the operation fails before mutation.

The two never replace each other. Optimistic checks protect against stale writers; they do not make several independent SQL statements atomic. Pessimistic `lockForUpdate()` remains available inside managed transactions. Advisory `HasLocks` controls user-level editing and is independent from revision history; a revert on a record locked by another user is refused.

Two gaps outside this document block the locking work: a Filament page guard that carries the expected version into the update, and a renderable for `StaleModelLockingException`, so that a conflict is not served as a 500. Not yet verified: whether the generic CRUD update reloads the root together with its pending translations in one step.

## Multi-connection policy

The first revised milestone still performs atomic work on one connection only.

Relation vectors may reference versions on another connection only through globally stable references. Such a snapshot cannot claim cross-connection atomicity. Deep restore across connections remains disabled until a recovery protocol is designed and approved.

Deferred (D25, I01): milestone 1 uses local ids on one connection, and existing history is discarded (D12). A stable uuid on roots, reference subjects and versions, and a persisted identifier of a history store, are taken up when a revert across connections or stores is actually needed.

## Retention

The history of a live record is never aged on its own. History disappears only through three sanctioned operations (invariant 12).

**Hard delete (D17).** A full snapshot of the aggregate is written first (columns, the state of the translations, the membership, the data of `RecordOrigin`), then the edit history and the root are removed, all in one transaction. The snapshot is the safety net for an accidental deletion and is itself purged after a second retention period; after that the data cannot be recovered. The foreign-key cascades remove their rows afterwards, so no orphan rows remain.

**Compaction (D18).** Manual, off by default, not scheduled. Choose a cut-off revision R by age or by number of revisions and find the anchor at or before it. By default the `DIFF` rows between the anchor and R are merged into one `DIFF` row written in place at R, the anchor is kept, and everything before the anchor is deleted. As an option, the row at R is rewritten in place as a full `SNAPSHOT` of the state as of R and everything before R is deleted, for whoever wants to forget the original values. In both cases the row at R keeps its revision coordinate and its unique `(version_set_id, sequence)`, the removal is a real `DELETE`, and the merge never crosses an epoch. It is refused when no anchor exists at or before R. After a compaction the states before the cut-off are no longer reachable, by design. `versions:compact` is redefined this way; today it adds a snapshot of the live state and its purge flag is never read.

**Switching versioning off (D22).** Deletes the history of the table after a user confirmation; the hard-delete snapshots of that table are kept until their own expiry and the purge command keeps running for them.

Retention must preserve every revision still exposed, the anchor of each, the version sets used as revert targets, and, for compaction and purge, the `reverted_from_set_id` self foreign key, which is `RESTRICT` today: a `Revert` set that is retained must be repointed (for example to the cut-off set) or the key changed to `SET NULL`. `core_versions.version_set_id` cascades on delete, so removing a set removes its rows.

To define: the name and default of the config key of the second retention period, the purge command and its schedule, and the metadata kept in a snapshot (who deleted, when).

## Effect on the current implementation

Potentially reusable:

- one synchronous Core writer;
- canonical pre-query before-image capture;
- the version-set manager for explicitly atomic operations;
- stable relation descriptors;
- connection-aware contracts;
- append-only revert history (`Revert` sets, `reverted_from_set_id`).

To change, from the decisions (not done; the code is untouched):

- detach from `overtrue/laravel-versionable` (D14): absorb the `VersionStrategy` enum and the base `Version` model with the MIT attribution, move the config keys `versionable.version_model` and `versionable.user_foreign_key` to a Core configuration, remove the requirement (with the user's approval), check whether `Diff` is used;
- rename the tables to `core_versions` and `core_versions_sets` (D15) and drop `deleted_at`, `is_deleted`, `updated_at`, add `compacted_at` (D19); adjust the readers `CompactVersions` and the sequence-gap check of `ActiveVersionSet`;
- `change_type` values (D20): the two writes of `HasVersions` that emit `updated` for a soft delete and a restore, and `trashingVersions()`;
- the reconstruction of C08 in Core's own `Version`, replacing the `DIFF` loop of `revertWithoutSaving()`, shared by `revertToVersion()` and `restoreScalarState()`, and the rename of `restoreToRevision()` to `revertToRevision()`;
- `createInitialVersion()` reads the row back after the insert (C09);
- exclude the lock columns from the versionable columns of models with `HasLocks`; force versioning off on the comment and rating models; translation models are not versioned (D8, D10, D21);
- the epoch (D13), the forced snapshot (D22), the compaction (D18) and the hard-delete coordinator with the reworked `ClearExpiredModels` (D17);
- the `history` and `revert` permissions (D26, D27);
- the SAO ticket timeline reads `change_type`, `contents`, `original_contents` and `created_at`, so it needs updating for D18 and D20.

Separate defect found on the way, not part of this design: a soft delete of a `Content` detaches all its tags (`HasTags`, `deleted` handler) and a restore does not reattach them.

No existing migration or commit is reverted until this draft is approved and a replacement implementation plan identifies the exact compatibility path.

## Design still to produce

1. **The hook that creates the owner's version when only a translation changes, and the grouping of the writes of one logical operation into one revision** (for example a machine translation of several locales, or a root saved with its translations). Mandatory (D10): without it, translations without a history of their own are not complete.
2. The aggregate revision scope on the paths that lack it: Filament saves, `CrudService::insert`, the root together with its translations.
3. The hard-delete coordinator and the reworked `ClearExpiredModels`.
4. The epoch fingerprint and its detection at the end of a migration.
5. The scoped revert context that suppresses automatic translation.
6. How the state of the translations is represented inside the owner's version rows.
7. Which pivot attributes are authoritative for each CMS relation.

## Required tests for an approved design

- a scalar update advances the root revision exactly once;
- relation-only attach, update, detach and sync advance the root revision exactly once;
- a stale expected revision rejects before mutation;
- atomic capture rolls back business and history together;
- unwrapped audit capture never allocates a complete aggregate revision;
- an unsupported authoritative write is labelled `best_effort` and never offers a complete revert;
- reference relations revert links without mutating shared subjects;
- translations are reverted by locale, a translation added later is removed and one deleted later is recreated;
- partial coverage disables a complete revert;
- raw and cascade bypass tests prevent a false complete-coverage declaration;
- a missing shared subject makes the complete-revert preflight fail before mutation, and `$force` yields a `partial` result;
- repeated reverts create a new revision without rewriting history;
- reconstruction: with versions `{title:T1,subtitle:S1}` (snapshot), `{title:T2}`, `{subtitle:S2}`, `{title:T3}`, reverting to the third gives `T2 / S2` (the failing case of C08);
- an anchor holds the columns that took a database default (C09);
- schema drift: a column added later is kept, a column dropped later is skipped and reported, a required column added later refuses a recreation, never a raw query error;
- compaction leaves the state of every retained revision at or after the cut-off identical, for both variants, and is refused without an anchor;
- the anchor check reports a record whose earliest retained row is not an anchor;
- switching versioning off deletes the history and keeps the hard-delete snapshots; the first save afterwards writes a complete snapshot and `currentRevision()` is `null` before it;
- a hard delete writes the snapshot, removes the history and the root atomically, leaves no orphan `RecordOrigin` row, and a recovery recreates the aggregate;
- `lock_version` increments on a translation write and not on a relation change;
- a revert does not trigger automatic translation and does fire indexing and re-embedding;
- `history` and `revert` are checked, and the history is not reachable without the root's ACL;
- the sequence-gap check works without `withTrashed()`;
- retention cannot delete a version set a retained `Revert` set points to without repointing it;
- local ids from different connections cannot collide (while multi-connection is deferred, this guards against enabling it by accident);
- cycles remain bounded to registered one-hop paths.

## Decisions

The decisions recorded on 2026-07-29/30 and reopened by the findings are now settled; the full list (D1 to D27) is in the review log.

**1. `lock_version` is not the aggregate revision.** Two separate logical values. `lock_version` keeps its current job, the user-facing conflict token of what a form edits (the root and its translations, D23); the aggregate revision is the restore coordinate. Reusing one column would make any relation change invalidate an open editing form: attaching a tag would fail Anna's save on a field Marco never touched. It would also leave the restore coordinate reachable from `request('lock_version')`.

**2. Atomic capture (C01, C04, C05).** Resolved by D6: writes that go through Core's supported mutation paths run inside a revision scope that opens a local transaction before the first query, and every other write is labelled `best_effort`.

**3. Hard delete and missing subjects (C03, C07, I02).** Resolved by D17 and D25: a coordinator owns every hard delete and writes a full snapshot first; a missing reference refuses a complete revert, or is skipped and reported with `$force` and labelled `partial`.

**4. Multi-root relations (C06).** Resolved by D24: only descriptors with exactly one authoritative root are registered.

## Former open questions, answered

1. Candidate A of the review log, atomic scopes with fail-closed unsupported writes: adopted as an atomic scope where Core writes and `best_effort` elsewhere (D6).
2. A durable aggregate head across hard delete: not in milestone 1; a hard-deleted root is recovered from its snapshot (D17).
3. Normalized relation items: confirmed (I03).
4. Globally stable identities: deferred (D25, I01).
5. Full scalar state on every anchor, with `DIFF` and `SNAPSHOT` kept as audit strategies: D18, C08, C09.
6. Best-effort capture as audit-only history, with "restore everything" permanently disabled for that path: yes (D6).
7. Which bypasses are rejected and which are labelled: labelled `best_effort` (D6, D17); switching versioning off is a purge (D22).
8. A revision provider for models without optimistic locking: the two counters stay independent (D23).
9. Retention reachability: the anchor rule and compaction (D18), hard-delete snapshots (D17).
10. CMS category semantics: categories, tags, locations and contributors are `reference` (D2); `related()` is outside the guarantee (D24). **Still open:** which pivot attributes are authoritative and which stable subject identity each relation uses.

## Milestone 1 scope (confirmed 2026-08-05, amended 2026-10-09)

The user confirmed a CMS-grade, minimal, forward-compatible bundle. It resolves the blocking Critical findings for milestone 1 by narrowing scope rather than by building the full machinery, which the closure gate of the review log permits. The amendments of 2026-10-09 are marked.

- **Restore coordinate (C03).** The aggregate revision is the **version set** itself (each supported operation opens one set for the root; `core_versions_sets` is already root-keyed, monotonic and revert-aware via `reverted_from_set_id`). No durable aggregate head. *Amended:* a hard-deleted root is no longer non-restorable: it is recovered from its full snapshot until the snapshot expires (D17).
- **Storage (C04, I03).** Normalized, reusing `core_versions.relation_path` and `subject_key`: each membership entry is a version row (`versionable` is the root, `relation_path`, `subject_key`, `contents` the pivot attributes). No JSON membership blob. *Amended:* the nullable `subject_version_id` for owned relations is not needed for translations, which have no history of their own (D10); it stays available for an owned child with an independent history.
- **Owned parts.** *Added:* the translations are part of the owner's revision (D10, D11).
- **Identity (I01).** Local ids on a single connection. *Amended:* the stable `uuid` planned for shared reference subjects is deferred together with I01 (D25), so a re-link after a recreation relies on local ids in milestone 1.
- **Multi-root (C06).** Out of scope (D24).
- **Capture (C01).** Atomic where Core writes, labelled `best_effort` elsewhere (D6).

Preceding delivery (implemented and tested on 2026-08-05): the revert marker (`revertToVersion()` opens a `Revert` set with `reverted_from_set_id`) and the delete semantics in `Modules/Core/app/Models/Concerns/HasVersions.php`. The soft delete is to be recorded as `trashed` and the hard delete as the full snapshot of D17 instead of the `Updated` / tombstone of that delivery (D20).

Next: an implementation plan identifying the concrete slices, then TDD delivery.

## Review and approval gate

Done so far:

1. the draft has been challenged and the findings are preserved in the 2026-08-04 review log;
2. its description of the existing code has been replaced with verified behaviour;
3. four defects found while checking those premises have been fixed and covered by tests;
4. on 2026-10-09 the findings were discussed with the user and decided (D1 to D27), and this text was aligned with them.

Before implementation resumes:

5. the user approves the revised semantics in this text, and the items under "Design still to produce" are designed;
6. the nine Critical findings and the accepted Important findings are closed in the review log, which needs this text aligned and the evidence rechecked once implemented;
7. the previous Core and CMS plans are replaced or explicitly amended (the Core plan's checkboxes no longer match the repository, which is further ahead);
8. a new implementation plan identifies which existing commits remain, change or are reverted;
9. no CMS pilot work starts before the new Core plan passes review.

Outside this document, two gaps block the locking work (see "Concurrency"): a Filament page guard that carries the expected version into the update, and a renderable for `StaleModelLockingException`.

