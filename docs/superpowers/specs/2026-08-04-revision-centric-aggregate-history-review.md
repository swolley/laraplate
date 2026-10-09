# Restoring a Record With Its Relations: Open Review Findings

## Status

- Date: 2026-08-04
- Target: `2026-07-29-revision-centric-aggregate-history-design.md`
- Target revision reviewed: `30b75b0`
- Status: open; implementation remains blocked
- Scope: generic Core architecture, not the CMS pilot

This log is the durable hand-off between reviewers. It records findings separately from design decisions so another agent can challenge the proposal without losing earlier reasoning.

Reviewers must not silently close a finding. A finding closes only when the design spec contains an unambiguous resolution, the evidence named here has been rechecked, and the resolution is recorded in this log.

## Discussion agenda (agreed 2026-10-09)

This ledger stays open and is to be re-examined with the user, not closed by a reviewer alone. The findings are discussed grouped by the decision they depend on, in this order, rather than one by one:

1. **The problem in plain words.** What a user must be able to do (for example "bring this content back to how it was yesterday, with its tags and categories") and what does not work today.
2. **The three underlying decisions.** Most findings descend from these:
   1. Is the restore coordinate the *observed state* (aggregate revision) or the *executed operation* (version set)?
   2. Must capture be atomic, or best-effort with an honest quality label?
   3. What happens to writes that do not go through Core: raw SQL, database cascades, jobs?
3. **The findings, grouped by decision.** For example C01 and C07 depend on decision 2, C03 and C06 on decision 1. Each group is settled before moving to the next.

After the discussion this log is rewritten in plain language, with every finding explained by an example, and the design spec is updated with whatever was decided.

## Severity and states

- `Critical`: the design can publish a false restore guarantee, corrupt revision ordering, or mutate the wrong aggregate.
- `Important`: the design remains unsafe, incomplete, or operationally ambiguous but does not immediately invalidate every restore coordinate.
- States: `open`, `proposed`, `resolved`, `rejected`.

## Finding summary

| ID | Severity | State | Finding |
|---|---|---|---|
| C01 | Critical | resolved (D6; spec to align) | Best-effort capture cannot satisfy the same-transaction invariant |
| C02 | Critical | resolved (D23; spec to align) | The concurrency section contradicts the separation of lock and aggregate revisions |
| C03 | Critical | resolved (m1) | A root-local revision counter disappears on hard delete |
| C04 | Critical | resolved (m1) | The revision allocation boundary and canonical checkpoint are undefined |
| C05 | Critical | resolved (D13, D22; spec to align) | Disabling versioning or changing descriptors can leave a falsely complete history |
| C06 | Critical | resolved (D24; spec to align) | Multi-root relations do not fit the one-root revision model |
| C07 | Critical | resolved (D6, D17; spec to align) | The force-delete design assumes central mutation paths that do not exist |
| C08 | Critical | resolved (D14, D18; spec to align) | A `DIFF` restore does not reconstruct the state as of a revision |
| C09 | Critical | resolved (D18, D22; spec to align) | The creation snapshot is not a complete image of the record |
| I01 | Important | deferred (D25) | Stable version, subject, root, and history-store identities are missing |
| I02 | Important | resolved (D2, D25; spec to align) | Skipping a missing subject contradicts complete restore |
| I03 | Important | resolved (m1) | JSON-only relation vectors cannot support integrity and retention safely |
| I04 | Important | resolved (C08, C09, D18; spec to align) | Aggregate checkpoints need a scalar snapshot independent of DIFF audit rows |
| I05 | Important | resolved (D26, D27; spec to align) | Authorization, approvals, side-effect control, and history visibility are absent |
| I06 | Important | resolved (D13, D22, this log; spec to align) | Review evidence and historical coverage epochs are not represented durably |

## Milestone 1 dispositions (2026-08-05)

The user confirmed a CMS-grade milestone-1 bundle (see the design spec, "Milestone 1 — confirmed scope"). Findings are resolved for the milestone by narrowing scope, which this log's closure gate explicitly permits.

- **C03 → resolved (scope).** The revision coordinate is the version set; no durable head. Hard-deleted roots are non-restorable; the SNAPSHOT tombstone is audit-only.
- **C04 → resolved (scope).** One supported operation = one version set = one revision. Membership is normalized version rows keyed by `relation_path` + `subject_key`.
- **I03 → resolved.** Normalized relation items via existing `relation_path`/`subject_key` + a new nullable `subject_version_id` self-FK (owned only). No JSON blob.
- **I01 → proposed (partial).** Local ids for the single-connection milestone; a stable `uuid` is added to shared reference subjects; version-row uuid and cross-connection identity are deferred.
- **C06 → out of scope (m1).** Single-authoritative-root relations only.
- **C01, C05, C07** remain deferred under the CMS-grade posture: honest best-effort/degradation and non-continuable hard delete instead of fail-closed atomic guarantees. **C02, I02, I04, I05, I06** are unchanged by this bundle.

## Decisions taken in the discussion (2026-10-09)

Recorded as agreed with the user; the design spec is to be aligned with them, and no finding is closed by this section alone.

1. **Customer expectation.** Restore follows the Drupal / enterprise-CMS model: the document and its owned parts come back, shared reference data does not. What must be avoided above all is a restore control that looks complete and is not.
2. **Shared references (tags, categories, contributors, locations, related contents).** Restore the *membership* only (which records were linked, and the pivot state), never the values of the linked records. A linked record that no longer exists is skipped and reported (matches the existing `restoreToRevision()` behaviour with `$force`).
3. **Owned children are part of milestone 1.** They are restored with the root, through a pointer: the revision stores, per owned child, the child's own version id (`subject_version_id`), and restore rolls each child back to that version instead of keeping a full copy. This reverses the earlier "owned relations are out of scope for this milestone". **Superseded for translations by D10** (no pointer is needed for them); the pointer remains the mechanism only for an owned child with an independent history, none of which is known today.
4. **Consequences to carry into the design (Claude's proposal, not discussed; superseded in part by D10: translations have no history of their own).** Translations (`ContentTranslation`) are the first owned children, since the text lives there; each owned child needs its own history; a child version must be reconstructable on its own (full snapshot or a checkpoint per aggregate revision, finding I04 therefore enters milestone 1); versions referenced by a revision must not be pruned; a child added after the target revision is removed on restore and one deleted after it is recreated.
5. **Not restored although technically children (consistent with D8, where the user decided that comments and ratings are never versioned):** user contributions such as comments and ratings.

6. **Capture quality (decision 2 of the agenda): option C.** Writes that go through Core's supported mutation paths are captured atomically, inside an aggregate revision scope that opens a local transaction when the caller has none. Every revision carries a quality label, `atomic` or `best-effort`, and "restore everything" is offered only on `atomic`. Writes that bypass Core (query-builder deletes, foreign-key cascades, raw SQL, `ClearExpiredModels`, lock-column updates, `updateOrInsert`) are `best-effort` by definition.
7. **Versioning of owned children follows the root.** If a root is versioned, its owned children (translations first) are versioned automatically; a child cannot be versioned without its root, nor the root without its owned children. There is no independent setting for the child. **Superseded by D10** for translations: they are not versioned at all.
8. **Comments and ratings are never versioned.** They are third-party contributions, not part of the document. Their models must force versioning off, so the per-table setting cannot turn it on. Today the seeder creates `versioning.strategy.*` with `DIFF` for every model that extends `Core\Overrides\Model` unless the model declares `$versionStrategy`, which is why `cms_contents_ratings` is versioned by accident.

9. **Translation models have no versioning setting of their own.** They override `getVersionStrategy()` and look up the setting key of their parent entity (for `ContentTranslation`, the key of `cms_contents`), so the child can never disagree with its root. This is the mechanism behind decision 7. The seeder must stop emitting `versioning.strategy.*` for these tables, otherwise a dead setting stays visible in the settings UI. `parentRelation()` (already declared on the translation models through `IsPartOfParent`) is the natural way to find the parent; whether the same override is shared with other owned children is to be decided in the design. **Superseded by D10:** translation models need no `getVersionStrategy()` override because they are not versioned.

10. **Translations have no history of their own (user decision, 2026-10-09).** A change to a translation produces a version of the *owner* (for `ContentTranslation`, the `Content`), and that version carries the state of the owner's translations. The owner is the only thing that can be rolled back. Translation models do not use `HasVersions`; like comments and ratings their versioning is forced off. Restore recreates or removes translation rows by locale (the natural key), never reusing a historical id. **Mandatory, not optional (user, 2026-10-09; designed in D28):** the design must define (a) the hook that creates the owner's version when only a translation changes, and (b) how many writes of one logical operation (for example a machine translation of several locales) are grouped into one revision. Neither may be left to a later phase: without them D10 is not complete.
11. **Weight follows the owner's strategy (user decision).** If the owner uses `DIFF`, the translation state in its version is stored as a difference; if it uses `SNAPSHOT`, in full. There is no separate strategy for translations.
12. **Existing history is discarded (user decision).** The product is pre-stable and will change a lot: no data migration of existing version rows, including the `cms_contents_translations` ones; the database is rebuilt with `migrate:fresh`.

13. **Schema changes: schema epoch, lazy baseline, history never deleted (user decision, 2026-10-09).** Each version row carries the schema epoch of its table; a new epoch opens when the table's versionable columns change, detected at the end of a migration (not computed on every save). The first write of a record after an epoch change is stored as a full `SNAPSHOT`, so no `DIFF` chain crosses two epochs and no record is rewritten during the migration. History is never discarded because of a schema change. A restore towards an older epoch classifies each column: apply those still compatible, skip and report those that no longer exist, refuse when recreating a row would miss a required value; any such discrepancy labels the revision `schema-mismatch` and withholds "restore everything". A migration that drops, renames or retypes a versioned column must declare it, enforced by an architecture test; a change of shape inside a JSON column stays a process rule. Related to finding I06 (coverage epochs).

14. **Laraplate detaches from `overtrue/laravel-versionable` (user decision, 2026-10-09).** Too much of what is being decided changes the library's behaviour and format (point-in-time restore, schema epochs, membership versions, owner-held translation state), so Core absorbs the small part it still uses and drops the dependency, following the precedent of the approvals subsystem (MIT attribution, dependency removed). No fork. An upstream contribution becomes optional and separate, for the generic mechanisms once the design is stable. Surface used today: the `VersionStrategy` enum, the base `Version` model (extended and partly overridden by `Core\Models\Version`) and the config keys `versionable.version_model` and `versionable.user_foreign_key`. To do when implemented, not now: attribution notice for the copied code, move the config keys to a Core configuration, remove the requirement from `Modules/Core/composer.json` (needs user approval, as for any dependency change), check whether `Diff` or other base-class members are used elsewhere before copying them. Consequence for C08: the point-in-time restore is written in Core's own `Version`, with no compatibility constraint towards the library.

15. **Absorbed code gets the `core_` table prefix (user decision, 2026-10-09).** A table whose mechanism Laraplate takes over from an external source stops carrying the `vend_` prefix. The approvals tables already did (`core_modifications`, `core_approvals`, `core_disapprovals`); with D14 the versioning tables follow: `vend_versions` becomes `core_versions` and `vend_versions_sets` becomes `core_versions_sets`. Scope is limited to what is detached from an external source; the other `vend_*` tables (roles, permissions, `model_has_*`, `role_has_permissions`, media) are not touched until their own mechanism is detached. To do when implemented, not now: the `CoreTables` enum values, the create migrations (no alter migration, `migrate:fresh` per D12), literal table names in tests and in the current documentation. Dated plans and specs that mention `vend_versions` stay as written; the live design spec is aligned when the decisions are carried into it.

16. **The restore coordinate is the observed state (user decision, 2026-10-09; agenda decision 1).** The user restores "the document as it was at revision R", not "undo operation N". The aggregate revision is the primary coordinate; a version set stays an optional envelope that groups the writes of one logical operation (it is the unit D10 uses to fold several translation writes into one revision). This confirms the direction of the design spec `2026-07-29-revision-centric-aggregate-history-design.md` against the operation-centric `2026-07-21-aggregate-version-sets-design.md`. The operation-centric design is not discarded: its version-set and transaction machinery remains the carrier for atomic capture (D6).

17. **Hard delete keeps one full snapshot and drops the edit history (user decision, 2026-10-09).** Context: `ClearExpiredModels` (`clear-expired`) does not concern validity periods; it hard-deletes records that have been soft-deleted for more than `core.soft_deletes.expiration_days`. When a versioned root is hard-deleted, Core writes one complete `SNAPSHOT` of the entity (the safety net for "I did not mean to delete it"), then removes the entity's edit history. Those hard-delete snapshots are themselves purged periodically; after that the data cannot be recovered, and the user accepts the loss of the history. The soft delete stage is unchanged: while a record is in the bin its full history is kept.
    - **Supersedes** the milestone-1 disposition of C03 ("hard-deleted roots are non-restorable; the SNAPSHOT tombstone is audit-only"): a hard-deleted root is now restorable from its snapshot until the snapshot is purged.
    - **Atomicity:** snapshot written, history removed and root deleted in one transaction, in that order of effect; otherwise a failure can lose both.
    - **The snapshot must be complete for the aggregate** (D10, D2): root columns, the state of the owned translations, and the relation membership at that moment, all taken before the foreign-key cascades remove them.
    - **Known limits of a recovery, to state to the user:** comments and ratings are not versioned (D8) and cascade away, files of attached media are deleted with the record; neither comes back.
    - **Capture:** it only works if every hard-delete path goes through a coordinator that snapshots first (C07). `ClearExpiredModels` must therefore stop deleting at builder level without a snapshot (bulk, in chunks, is acceptable). Deletes that bypass Core, including database cascades from an `Entity` or `Preset` delete, leave no snapshot and are `best-effort` (D6).
    - **To define:** the second retention period (name and default of the config key), the command that purges expired hard-delete snapshots and its schedule, the metadata kept in the snapshot (who deleted, when), and what happens to the existing `versions:compact` command, which already snapshots and purges older rows and contradicts D13 for ordinary records.

18. **Compaction: merged diff at a cut-off revision, snapshot rewrite as an option (user decision, 2026-10-09).** The intent of `versions:compact` is: take the history, discard the versions older than a limit and replace them with a snapshot that compacts them. Today the command does none of it: no age or count limit, the snapshot is of the *live* state (`refresh()`), and the purge flag is passed down and never read. It is redefined as follows. Choose a cut-off revision R (by age or by number of revisions). Find the anchor: the nearest `SNAPSHOT` at or before R, found with the walk of C08.
    - **Default, merged diff (user's variant, agreed 2026-10-09).** The `DIFF` rows between the anchor and R are merged into one `DIFF` row, written in place at R, with the columns changed over that stretch (latest values in `contents`, earliest values in `original_contents`). The anchor is kept; whatever precedes the anchor is deleted. No row is labelled `SNAPSHOT` unless it really is one, and no state has to be reconstructed to write it. Example: `S0 d1 d2 d3 d4 d5` compacted at `d3` becomes `S0 D(d1..d3) d4 d5`. The merge never crosses a schema epoch (D13): it only spans the stretch between the anchor and R, and an anchor from a more recent epoch discards the older rows.
    - **Option, snapshot rewrite.** For whoever wants to forget the original values as well, the row at R is rewritten in place as a full `SNAPSHOT` of the state as of R (reconstructed with C08; for an aggregate: columns, owned translations per D10, relation membership), and everything before R is deleted. It carries the schema epoch of R.
    - **In both cases** the row at R keeps its revision coordinate (D16) and its unique `(version_set_id, sequence)`; the rows before the cut are removed with a real `DELETE`. Rewriting a row is the only sanctioned mutation of a version row, so it may be marked (for example `compacted_at`). A hard delete (D17) is not a compaction: it always writes a full snapshot. Manual, off by default, not scheduled; the history of live records is never aged on its own.
    - **Both variants need an anchor.** Compaction at R is allowed only when the C08 walk finds a `SNAPSHOT` at or before R (normally the creation snapshot). Otherwise it refuses and reports the record; nothing is deleted. Never write a "snapshot" made by merging diffs without a base.
    - **Invariant to enforce:** the earliest retained row of every versioned record is a full snapshot. It must hold at creation (the `Created` row is a snapshot), when versioning is enabled on a table that already holds records (a baseline for each record, otherwise the first version is a diff with no base; relates to C05), at every schema epoch change (D13 lazy baseline), and after a compaction. This was not guaranteed so far: versioning was effectively off on most tables until 2026-07-30, and builder-level inserts, `upsert` and imports that skip model events write no `Created` row.
    - **For the owner, the creation snapshot must already contain the initial translations and membership.** Translations are currently saved after the root (`savePendingTranslations` on `saved`), so the `Created` row of a `Content` would miss them unless the writes of the creation are grouped in one revision (the mandatory point of D10).
    - **Verification:** a check that reports every record whose earliest retained row is not a snapshot, and a test asserting that the state reconstructed at every retained revision at or after R is identical before and after a compaction, for both variants. States before R are no longer reachable by design.

19. **Columns of the version tables (user decision, 2026-10-09).** The versions table is append-only, so it loses what an editable table carries.
    - **`deleted_at`, `is_deleted` and `SoftDeletes` are removed from `core_versions`.** Verified: the soft delete of a *versioned model* is not stored in the version row's own `deleted_at`; it is written as an `updated` row whose JSON `contents` holds the model's `deleted_at` value, and the row's own column is always `NULL` (0 rows in the development database). Removing a version is a real `DELETE`, only through the purges of D17 and D18. Readers to adjust: `CompactVersions` (`whereNull('deleted_at')`) and the sequence-gap check in `ActiveVersionSet` (`withTrashed()`).
    - **`updated_at` is removed, `created_at` stays** (it orders previous and next versions and can be forced by the writer). Route chosen: `MigrateUtils::timestamps()` gets an optional parameter to omit `updated_at` (default unchanged, so no other table moves), and `Version` declares `public const UPDATED_AT = null`; otherwise Eloquent would still write `updated_at` on every insert and fail. Precedent for the model side: `MES\OperationQuantityAudit`. `core_versions_sets` is checked below.
    - **`compacted_at` is added** for the rewrite of D18.
    - **`core_versions_sets`, checked (2026-10-09), extension confirmed by the user (2026-10-09).** It has no soft delete already. It has `created_at` and `updated_at`, but no code updates a set after creation (the only write besides the insert is `deleteOrFail()` of an empty set in `ActiveVersionSet`), so `updated_at` goes away there too, with `UPDATED_AT = null` on `VersionSet`. Constraint to remember for D17 and D18: `reverted_from_set_id` is a self foreign key with `RESTRICT` on delete, so a purge or compaction that deletes a set which a retained `Revert` set points to would fail; the retained `Revert` sets must be repointed (for example to the cut-off set) or the key changed to `SET NULL`, to be decided when implementing. `core_versions.version_set_id` cascades on delete, so removing a set removes its version rows.
    - The vocabulary of `change_type` is settled in D20.

20. **Vocabulary of `change_type`: Eloquent lifecycle verbs (user decision, 2026-10-09).** `change_type` takes the names of the Eloquent lifecycle events instead of the permission verbs (`ActionEnum`: `insert`, `forceDelete`, ...) or the approvals verbs (`Operation`: `create`, `force_delete`), because it records what happened to the row, not what a user is allowed to do, and because the membership rows already reuse `created` / `deleted`, which are also the events of a custom pivot model. Values: `created`, `updated`, `trashed`, `restored`, `deleted`.
    - `trashed`: the root goes to the bin (replaces today's `updated` row carrying `deleted_at` in its JSON; `trashingVersions()` stops searching the JSON).
    - `restored`: the root leaves the bin. **"Restore" is reserved for the bin.**
    - `deleted`: the row is physically gone. For a root it is the tombstone of D17, whether it came from `forceDelete()` or from `delete()` on a model without a bin; for membership it is a detach. `forceDeleted` is not a separate value.
    - **A revert is not a `change_type`.** Rolling a document back to an earlier revision is a composite operation recorded at set level (`VersionSetKind::Revert` with `reverted_from_set_id`); the rows inside stay plain `updated`. **"Revert" is reserved for revisions:** `restoreToRevision()` is to be renamed `revertToRevision()` when implemented (the code already has `revertToVersion()` and `VersionSetKind::Revert`).
    - **A compaction is not a `change_type` either.** It is housekeeping on the history itself (D18): it changes no record, adds no revision and is marked by `compacted_at`.
    - A permission verb maps to a value with a pure function (`delete` gives `trashed` on a model with a bin, `deleted` on one without), so no second column is needed.
    - To do when implemented, not now: the enum, the two writes in `HasVersions` that currently emit `updated` for a soft delete and a restore, `trashingVersions()`, the create migration (enum column), a test per value, and the rename above. The `create`/`insert` and `force_delete`/`forceDelete` mismatch between the approvals and permission vocabularies is outside this work and is only noted.

21. **What is outside the document and is neither versioned nor restored (agenda decision 3, partly).**
    - **`RecordOrigin` is import bookkeeping (user decision, 2026-10-09).** It is not versioned and a revert does not touch it. Verified: it is listed in `CorePermissions::excludedModels()`, so it has no CRUD permissions at all ("the origin row behind an imported record", a side effect of an action authorized elsewhere); no Filament page or HTTP route of Core or CMS writes it; its writers are the importers through `RecordOriginRegistry` (`updateOrInsert`). The model foresees a "manual attribution" (validation rules for manual writes, `external_id` null for manual origins) but no entry point for it was found (the search was not exhaustive). It is also the import identity registry: `unique(referable_type, source_key, external_id)` maps an external id to a local record, which is one more reason a revert must never rewind it (re-import idempotency). The census had treated `origin` as probably owned because of the shape of the relation (`morphOne`), which was the wrong criterion: the question is whether the data is part of the document, not whether it hangs from it.
    - **No orphan rows, ever (user decision, 2026-10-09).** Its rows are morph rows without a foreign key, so a plain hard delete would leave them behind. They are removed together with the record at the hard delete. Proposed mechanism, to confirm: the origin data is carried inside the D17 snapshot and recreated if the record is recovered, so that a recovered record keeps its identity mapping and a later import does not duplicate it. The same check applies to every other morph or foreign-key-less child of a versioned root; it has not been done.
    - **Lock columns (user decision, 2026-10-09).** `locked_at`, `locked_user_id` and `locked_until` (written by `HasLocks::writeLockColumns`, without events and without bumping `lock_version`) are transient coordination state. For every model with `HasLocks` they are excluded from versioning, as `lock_version` already is (commit `1c3ecd18`), and declared outside the guarantee. Reason: a snapshot taken after a `refresh()` (D17 tombstone, D18 snapshot option) would otherwise carry them, and a revert or a recovery would bring back a stale lock or drop a live one. Left to verify: whether a revert on a record locked by another user is refused like an ordinary write.
    - **Media (user decision, 2026-10-09).** For milestone 1 media is outside the restore guarantee, declared explicitly. `Core\Models\Media` already has its own history of metadata (`HasVersions`) and a bin with expiry; the files are not versioned, and whether the observer keeps them while a media is in the bin is not verified. To resume later as an ordered membership per collection (Drupal-style: the revert restores which media are attached, not the file), skipping media that no longer exist as already agreed for references.

22. **Switching versioning off discards the history; switching it on forces a snapshot at the first save (user decision, 2026-10-09).** When versioning is disabled for a table, its history is deleted: nothing survives a period in which writes were not captured, so no revision can claim a state that the live row has left. When it is enabled again, each record gets a forced full `SNAPSHOT` at its first save, which is the anchor required by D18 (and needs the complete image of C09). A record not saved since the re-enable has no versions at all, so `currentRevision()` is `null` rather than a stale number.
    - **Confirmed by the user (2026-10-09): the destructive toggle needs an explicit user confirmation.** The settings UI (or a dedicated command) must name what is lost before disabling. A queued, chunked purge is still to be designed for large tables. Applies per table, so it must cover every model sharing it.
    - **DO NOT FORGET (user decision, 2026-10-09): the hard-delete snapshots of D17 survive the disabling.** When versioning is turned off for a table, only the edit history is discarded. The snapshots of records that were hard-deleted are kept, purely as a safety net, until their own expiry (the retention period still to be defined in D17); nothing else in the switch-off may touch them. The purge command of D17 must keep running for them even while versioning is off for that table.
    - **Confirmed by the user (2026-10-09): the forced initial snapshot is the image *after* the first save.** One row; the history of the record starts there and the state before the first edit is not kept.
    - D13 still holds for schema changes and descriptor changes: epoch, lazy baseline, history not deleted. The epoch no longer has to close on disable or enable, since disabling erases the history.

23. **Two concurrency counters, with separate jobs (user decision, 2026-10-09; resolves C02).**
    - **`lock_version`** protects the form: it covers what the form edits, the root and its translations (D10). A translation write increments the root's `lock_version`, at the same point where D10 creates the owner's version. Relation changes do not increment it, as long as the form does not show them.
    - **The aggregate revision** protects the restore and covers everything, relations included.
    - They never replace each other: `lock_version` rejects a stale save, the revision rejects a restore towards a superseded state.
    - **Open:** whether the Vue UI and the generic CRUD API show relations in their forms; if they do, a concurrent change to a relation must be a conflict for that form. Not verified: whether `CrudService::update` with `lock_version` reloads the root together with its pending translations in one step.

24. **Multi-root relations are outside milestone 1 (user decision, 2026-10-09; resolves C06).** Only relations with exactly one authoritative root are versioned and restored. A symmetric relation such as `Content::related()` (`Content A <-> Content B`, table `relatables`), an ownership transfer or any other mutation touching more than one root is declared outside the guarantee: a revert does not touch it, and registering it as a versioned relation is rejected. The related contents are an editorial association, not the text of the document. A separate ordered multi-root protocol is a later work item.

25. **Closing the Important findings (user decision, 2026-10-09).** I03 stays resolved by milestone 1. I04 and I06 are closed by the decisions listed in their sections. I02 is closed as stated there: complete restore refuses on a missing reference, `$force` skips, reports and labels the result partial. **I01 (stable identities) is deferred**: milestone 1 uses local ids on a single connection (D12 discards existing history), so the `uuid` for roots, reference subjects and versions, and the persisted identifier of a history store, are taken up when a restore across connections or stores is actually needed. I05 is discussed separately.

26. **Restore security and side effects (user decision, 2026-10-09; part of I05, decisions 2 and 3).**
    - **A new permission verb `revert`.** It is generated for versioned models, distinct from `update` (a revert overwrites with old data and is more powerful), consistent with the word reserved by D20. Recovering a hard-deleted root needs `restore`. A revert is an ordinary write: it follows the approvals of the model like an `update` (with the actor attributed) and is refused if the record is locked by another user.
    - **Side effects.** Model events fire during a revert, so search indexing and re-embedding realign with the restored content after commit. Automatic translation does **not** run: verified in `HasTranslations`, the `updated` handler emits `TranslatedModelSaved($model, [], true)` when the default-locale translation changes, and `TranslateModelJob` would then retranslate the other locales with `force`, overwriting the ones just restored (conditional on `features.translation.enabled`, seeded off, and the model's own setting). A scoped revert context suppresses the translation dispatch; it is not a global `withoutEvents()`. The approvals subsystem does not create a new modification for the writes the revert performs internally.
    - **Still open (decision 1 of I05):** who may read the history. Today `Version` and `VersionSet` are in `CorePermissions::excludedModels()`, so they have no permissions of their own. Concern: reading the versions table directly would show the history of rows the user's ACL hides.

27. **History visibility: a `history` permission (user decision, 2026-10-09; closes I05 decision 1).** Reading the history of a versioned record needs the new verb `history`, declared for versioned models like `revert` (D26), in addition to being able to read the record (`select`). `history` shows the revisions, who changed what and when, and the past values; it modifies nothing. `revert` implies `history`. The history is always reached through the root and its ACL, never by reading `core_versions` on its own. The snapshots of hard-deleted records (D17) are read and recovered only with `restore`. The SAO ticket timeline (`TicketTimelineService`) stays on the permission of the ticket for now; it reads `change_type`, `contents`, `original_contents` and `created_at`, so D18 (a compacted stretch becomes one entry) and D20 (new `change_type` values) require updating it when implemented.

28. **The hook for translation changes and the grouping of writes (user decision, 2026-10-09; designs the mandatory point of D10).**
    - **Facts.** Every translation write ends in events of the translation model: `setTranslation()` writes at once with `update()` or `create()` (used by `TranslateModelJob` and the importers), and the attribute setters (`$content->title = ...`) go to `pending_translations` and are written by `savePendingTranslations()` on the root's `saved` event with the same calls. Without a scope, `VersionWriter::write()` opens a set of its own for each row, so a save of a root with its translations would produce two revisions; a translation-only change leaves the root clean, so the root's `updated` does not fire.
    - **Representation.** One version row per changed locale, on the owner, with `relation_path = 'translations'` and `subject_key = {locale}`, in the same set as the owner's own row. `change_type` is the event of the translation (`created`, `updated`, `deleted`); `contents` is the difference of the fields, or the complete image for a snapshot. It reuses the normalized membership storage; reconstruction and compaction run the same function per `(relation_path, subject_key)`. A locale whose last row up to R is `deleted` does not exist in R. Not a JSON blob inside the owner's `contents`.
    - **The hook.** An observer on the translation models (`ITranslated`, owner found with `parentRelation()`) writes the row on the owner when a translation is created, updated or deleted. The owner's `lock_version` rises once per revision (D23). Translation models are not versioned on their own: `getVersionStrategy()` returns `false` for them and the seeder creates no setting for them.
    - **The scope.** `save()` and `delete()` of the base model (`Core\Overrides\Model`) open the revision scope before `saving` and close it after all `saved` handlers, only for versioned roots that have a translation or a registered relation. The save of the root, of its pending translations and of their version rows is one set and one transaction (this also closes the non-atomic save of C01). To verify with a targeted test before adopting: performance and regressions of wrapping `save()`.
    - **Explicit grouping.** A method such as `$owner->withinRevision(fn)` groups several writes in one revision, for the importers and for any loop over locales.
    - **Async translation.** `TranslateModelJob` runs as a revision of its own, with a system actor and `reason = "ai-translation"`: it is asynchronous and has another author, so it does not merge with the user's revision.
    - **Cases.** The root and its initial translations share the creation set, so the anchor of creation is complete. A hard delete (D17) snapshots every locale from the live table before the cascade. A translation-only change gives a set made only of translation rows and does not touch the root's `updated_at`; whether the root must be touched for caches and search is not decided.

## Evidence verified in code (2026-10-09)

Verified by running a throwaway test (deleted afterwards) against the `HasVersions` + `Core\Models\Version::revert()` path, `DIFF` strategy, SQLite:

- **Column added after the version:** the restore keeps the current value of the new column. Compatible.
- **Column dropped after the version:** `revertToVersion()` fails with a raw `QueryException` (`no such column`). It neither skips nor reports the column, so a check of our own is required before the restore (basis of D13).
- **Multi-step `DIFF` is not a point-in-time restore.** With versions `{title:T1,subtitle:S1}` (snapshot), `{title:T2}`, `{subtitle:S2}`, `{title:T3}`, reverting to the third one yields `title=T3, subtitle=S2`, not `T2 / S2`: the restore applies only the columns the target version itself changed on top of the *live* row, and every other column keeps its current value. The replay loop merges each step over the live row instead of accumulating. This is a defect in the restore mechanism itself, independent of schema changes, and it invalidates "restore the state as of revision R" for `DIFF`. Registered as C08.
- **The `Created` row is not a complete image.** `createInitialVersion()` stores `filterVersionableImage($model->getAttributes())`, that is only the attributes assigned at insert. Real data confirms it: the creation snapshot of a `Content` holds `id, valid_to, entity_id, valid_from, order_column, presettable_id, shared_components` and lacks every column that took its value from a database default (for example `extended_type`, the lock columns). A `SNAPSHOT` written after a `refresh()` (as `createSnapshotVersion()` does) does hold them. A restore built on this anchor cannot know the original value of a defaulted column that changed after the target revision. The anchor invariant of D18 therefore also requires the creation row to be read back after the insert. Registered as C09.

### Write-path census of CMS `Content` (read-only code survey, partly unverified)

Filament, the generic `CrudService`, imports, the AI translation job and observers all go through Eloquent; relations are never versioned (`Content` declares no `versionedRelations()`); a translation edit versions the translation row but not the `Content`; saving a content with its translations is not atomic. Known bypasses: `ClearExpiredModels` (builder-level hard delete, no tombstone, silent foreign-key cascades), lock columns, `RecordOrigin`, media. Separate defect found on the way: a soft delete of a `Content` detaches all its tags (`HasTags`, `deleted` handler) and restore does not reattach them.

### State of the code when the discussion started

`subject_version_id` does not exist yet in migrations or code, no production model uses `HasVersionedRelations`, and `RelationOwnership::Owned` is skipped by `restoreToRevision()`.

## Critical findings

### C01 — Best-effort capture cannot satisfy the same-transaction invariant

**Affected sections:** `Non-goals`, `Capture flows / Unwrapped best-effort flow`, and decision 2.

**Evidence:** model version writes currently run from `updated` and `deleted` callbacks. Without an already active version-set transaction, `VersionWriter` opens a transaction only after the business query has completed.

**Failure scenario:** a pivot insert commits in autocommit mode and the process terminates before revision advancement and checkpoint persistence. Leaving the revision unchanged makes the existing revision describe a state that is no longer live. Advancing it in the business query instead creates a detectable revision gap but violates the current rule that no revision may exist without a history row.

**Violated invariants:** 2, 3, and 4.

**Proposed resolution:** a registered aggregate mutation must enter a Core-owned revision scope before its first SQL statement. Core opens a local transaction automatically when no outer transaction exists. A path that cannot be intercepted before SQL is untracked, invalidates complete coverage, and cannot be repaired retrospectively by an observer. Best-effort history may remain an audit facility but cannot qualify for complete aggregate restore.

**Resolution (decision, 2026-10-09):** closed by D6. Writes that go through Core's supported mutation paths run inside an aggregate revision scope that opens a local transaction when the caller has none, so business rows and history commit together; every other write is labelled `best-effort` and never offers "restore everything". Precondition found by the census: the scope must exist on the paths that lack it today, namely Filament saves, `CrudService::insert`, and the save of a root with its translations (the same point as D10). Closure still needs the design spec aligned and this evidence rechecked once implemented.

### C02 — The two concurrency tokens have contradictory consumers

**Affected sections:** `Goals`, `Concurrency`, and decision 1.

**Failure scenario:** Anna opens a scalar form at aggregate revision 10. Marco attaches a category, advancing only the aggregate revision to 11. If Anna's scalar update supplies expected aggregate revision 10 as required by `Concurrency`, it is rejected even though decision 1 introduced a separate `lock_version` specifically to avoid that rejection.

**Violated invariant:** the stated separation between editing conflicts and aggregate restore coordinates.

**Proposed resolution:** scalar forms use `lock_version`; aggregate restore and commands that replace aggregate membership use `aggregate_revision`; commands that modify both scalar state and authoritative relations explicitly carry both tokens. The API must not describe either token as a substitute for the other.

**Resolution (decision, 2026-10-09):** closed by D23. Three rules. (1) `lock_version` covers what the form edits: the root and, since D10, its translations. A translation write increments the root's `lock_version` at the same point where D10 creates the owner's version; relation changes (categories, tags) do not, as long as the form does not show them. (2) The aggregate revision stays separate and covers everything, relations included. (3) The two counters never replace each other: `lock_version` rejects a stale save, the revision rejects a restore towards a superseded state. Evidence of the gap closed by rule 1, deduced from the code and not reproduced with a test: `ContentTranslation` and `HasTranslations` do not touch `lock_version` and a translation-only change leaves the `cms_contents` row clean, so a form opened before a title edit would overwrite it without a conflict. Closure still needs the design spec aligned and this evidence rechecked once implemented.

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

**Resolution (decision, 2026-10-09):** closed by D13 and D22. Disabling versioning deletes the history of the table and enabling it forces a snapshot at each record's first save, so a stale revision can no longer be advertised after a period without capture. A change of columns or of the relation descriptors opens a new epoch with a lazy baseline (D13); a restore towards an older epoch is labelled `schema-mismatch` or `definition-mismatch` and never offers "restore everything". `complete` is relative to the epoch's definition, not a timeless boolean. A bypassing write (case d of the discussion) remains undetectable in general and is labelled `best-effort` (D6). Closure still needs the design spec aligned and the evidence rechecked once implemented.

### C06 — Some relations affect more than one root

**Affected sections:** `Relation descriptors`, `Managed atomic flow`, and `Multi-connection policy`.

**Failure scenario:** removing a symmetric `Content A ↔ Content B` relation changes the authoritative vector of both contents. Advancing only A leaves B's revision stale; advancing both conflicts with the current one-root set and locking model. Moving an owned child between roots has the same shape.

**Violated invariants:** 2, 3, and 7.

**Proposed resolution:** the first milestone accepts only descriptors with exactly one authoritative root. Inverse views are explicitly non-authoritative. Symmetric relations, ownership transfers, and other multi-root mutations fail descriptor registration until a separate ordered multi-root protocol exists.

**Resolution (decision, 2026-10-09):** closed by D24, which takes the resolution proposed above. Milestone 1 accepts only descriptors with exactly one authoritative root; symmetric relations, ownership transfers and other multi-root mutations are outside the guarantee and fail descriptor registration. Closure still needs the design spec aligned and the evidence rechecked once implemented.

### C07 — Force delete is not centralized

**Affected sections:** decisions 3–4.

**Evidence:** production paths include Core CRUD deletion, the generic expired-model command, CMS media helpers, and module services. Builder-level force deletes can bypass per-model events entirely.

**Failure scenario:** a bulk force delete cascades registered pivot rows before Core drains and checkpoints them. No callback can reconstruct the destroyed memberships afterward.

**Violated invariants:** 3, 4, and 13.

**Proposed resolution:** complete aggregates use a mandatory deletion coordinator. Bulk and raw deletes on registered roots or subjects are rejected or explicitly invalidate coverage. Database cascades remain the final referential-integrity guard, not the normal capture mechanism.

**Resolution (decision, 2026-10-09):** closed by D6 and D17. Hard deletes that pass through Core go through one coordinator that writes the full snapshot first (D17) and removes history and root in one transaction. Builder-level deletes and database cascades that bypass Core stay `best-effort` and are declared so. `ClearExpiredModels` stops deleting at builder level without a snapshot (chunked is acceptable). Closure still needs the design spec aligned and this evidence rechecked once implemented.

### C08 — A `DIFF` restore does not reconstruct the state as of a revision

**Affected sections:** `Goals` ("restore the aggregate to the state represented by root revision `R`") and the scalar part of every restore.

**Evidence:** both restore entry points, `HasVersions::revertToVersion()` and `HasVersionedRelations::restoreScalarState()` (the scalar part of `restoreToRevision()`), call `Version::revert()`. In `Core\Models\Version::revertWithoutSaving()` the `DIFF` loop merges each earlier version over the *live* row instead of accumulating, so the result is the live row plus only the columns the target version itself changed. Verified with a throwaway test on 2026-10-09 (see "Evidence verified in code"): versions `{title:T1,subtitle:S1}`, `{title:T2}`, `{subtitle:S2}`, `{title:T3}`; reverting to the third gives `title=T3, subtitle=S2` instead of `T2 / S2`. The relation membership part of the restore is replayed correctly; only the scalar part is wrong.

**Failure scenario:** a user restores a content to "yesterday". Memberships come back as they were, scalar fields come back only if they were touched in that exact version, the rest stay as today. The restore control promises a state that never existed.

**Violated invariants:** the restore coordinate must denote a reconstructable state.

**Proposed resolution (requirement agreed in discussion, 2026-10-09):** the state as of revision R is reconstructed by walking the version rows back from R and stopping at the nearest `SNAPSHOT` (a `Created` row is one), then applying the following rows forward in revision order, last write wins per column, and writing the result once. Nothing before that snapshot is read or merged. The walk is driven by each row's own stored strategy, not by the model's current one, since a history mixes `DIFF` and `SNAPSHOT` rows. Both entry points use this one function. With D13 (lazy baseline after a schema change) the walk never crosses a schema epoch. Written in Core's own `Version` (D14), with no compatibility constraint towards the library.

**Resolution (decision, 2026-10-09):** the requirement stated above is agreed and sits in Core's own `Version` (D14): the state as of revision R is rebuilt from the nearest complete `SNAPSHOT` at or before R, driven by each row's stored strategy, and written once; `revertToVersion()` and `restoreToRevision()` (to be renamed `revertToRevision()`, D20) share the one function. Closure still needs the design spec aligned and a test reproducing the failing case of the evidence once implemented.

### C09 — The creation snapshot is not a complete image of the record

**Affected sections:** C08, D18 (anchor invariant).

**Evidence:** `HasVersions::createInitialVersion()` stores `filterVersionableImage($model->getAttributes())`, that is only the attributes assigned at insert. Verified on development data: the creation snapshot of a `Content` holds `id, valid_to, entity_id, valid_from, order_column, presettable_id, shared_components`, and lacks every column that took its value from a database default (for example `extended_type`, the lock columns). A `SNAPSHOT` written after a `refresh()` (`createSnapshotVersion()`) does contain them.

**Failure scenario:** a column with a database default is never assigned at insert, then changes after revision R. A restore to R starts from the creation anchor, which does not hold the column, and no later row can supply its original value in a forward replay; the restored document keeps today's value while claiming to be R.

**Violated invariants:** the anchor of C08 and D18 must be a complete image of the versionable columns.

**Proposed resolution:** read the row back after the insert (or fill the defaults) before writing the `Created` row; add the check "the earliest retained row is a complete image" to the verification of D18, comparing it with the column list of the table at that epoch (D13).

**Resolution (decision, 2026-10-09):** the creation row is read back after the insert so that it is a complete image (D18 anchor invariant); the forced snapshot at the first save after re-enabling (D22) is likewise complete. Closure still needs the design spec aligned and the check of D18 once implemented.

## Important findings

### I01 — Stable identities are incomplete

The example uses local numeric IDs, while decisions rely on immutable natural identity and cross-connection version references. CMS categories do not currently expose a suitable immutable natural key.

**Proposed resolution:** add immutable stable IDs for aggregate roots and reference subjects that participate in aggregate history; add a stable UUID to each version independently from its database primary key; identify a history store with a persisted UUID rather than a Laravel connection name. A cross-store locator is `(history_store_uuid, version_uuid)`.

**Deferred (decision, 2026-10-09):** see D25. Not part of milestone 1.
### I02 — Missing references cannot produce a complete restore

Decision 3 says missing shared subjects are skipped and reported, while goals and tests require reconstructability.

**Proposed resolution:** preflight a complete restore and fail before mutation when a required reference is missing. A separately requested partial link restore may skip and report. An `owned` child may be recreated only when its descriptor, authorization, and retained snapshot explicitly allow it.

**Resolution (decision, 2026-10-09):** closed by D2 and D25. A complete restore is refused when a required reference no longer exists; a restore run with `$force` skips and reports the missing subjects, and the result is labelled partial, never "restore everything". This is the behaviour `restoreToRevision()` already has (`MissingRestoreSubjectException` unless `$force`). Closure still needs the design spec aligned and the evidence rechecked once implemented.
### I03 — Normalize relation-vector items

JSON-only vectors cannot enforce foreign keys, indexed reverse lookup, or retention reachability. Full scans are particularly unsuitable for shared-subject deletion and cross-version retention.

**Proposed resolution:** use a canonical checkpoint header and normalized relation-item rows. JSON remains acceptable for descriptor-defined stable key payloads and pivot state, accompanied by deterministic hashes where indexed lookup is required.

### I04 — Checkpoints need full scalar state

DIFF rows are useful for audit but create complex and fragile checkpoint reconstruction and retention dependencies.

**Proposed resolution:** every canonical aggregate checkpoint stores the full scalar root state represented by the revision. Existing DIFF or SNAPSHOT record versions remain audit rows and can use their configured strategy independently.

**Resolution (decision, 2026-10-09):** closed by C08, C09 and D18. The restore reconstructs the state from the nearest complete `SNAPSHOT` at or before the target and applies the following rows forward; every retained history begins with a complete image. Audit rows keep their own strategy. Closure still needs the design spec aligned and the evidence rechecked once implemented.
### I05 — Restore security and side effects are unspecified

The predecessor spec required authorization, approval-aware application, scoped restoration mode, and after-commit reconciliation. The successor draft does not preserve those contracts or define who may read potentially sensitive history.

**Proposed resolution:** carry those contracts forward explicitly. Authorize history visibility and each restore mode; preserve approval attribution; never use global `withoutEvents()`; reconcile derived data after commit; preview shared-record impact before any exceptional deep restore.

**Resolution (decision, 2026-10-09):** closed by D26 and D27. Authorization: new verbs `history` (read the history, through the root and its ACL) and `revert` (implies `history`); recovery of a hard-deleted root needs `restore`. Approvals: a revert follows the model's approvals like an update, with the actor attributed. Side effects: model events fire so search and embeddings realign after commit; automatic translation is suppressed during a revert by a scoped context, never by a global `withoutEvents()`. Closure still needs the design spec aligned and the evidence rechecked once implemented.

### I06 — Review and epoch evidence are not durable

The target spec references finding `C2` without preserving the original finding list. Coverage evidence is also described but has no durable descriptor version or proof record.

**Proposed resolution:** keep this log as the finding ledger and store aggregate-definition identity with every checkpoint. Each review pass updates finding state and cites the spec revision that resolved it.

**Resolution (decision, 2026-10-09):** closed by D13, D22 and this ledger. The schema epoch carries a fingerprint of the definition (columns and versioned relations) and every version row stores its epoch; each review pass updates the state here. Closure still needs the design spec aligned and the evidence rechecked once implemented.
## Changes to carry into the design spec (list prepared 2026-10-09; sections A, B and C applied the same day, section D is still to design)

Target: `2026-07-29-revision-centric-aggregate-history-design.md`. Grouped by what the spec said versus what the decisions say. Sections A, B and C were applied to the spec on 2026-10-09; the finding texts above keep the section names the spec had at the time of the review (for example "Root checkpoints", now "Anchors and reconstruction").

### A. Where the spec contradicts a decision (must change)

| Spec section | Says today | Decision | Change |
|---|---|---|---|
| Milestone 1, C03 | Hard-deleted roots are non-restorable; the SNAPSHOT tombstone is audit-only | D17 | A hard-deleted root is restorable from its full snapshot until the snapshot expires; history is dropped, snapshot kept |
| Milestone 1, storage; invariant 10; Owned-state restore | `subject_version_id` points to the child's own version for `owned` relations | D10 | Translations have no history of their own; their state lives in the owner's revision. The pointer applies only to an owned child with an independent history (none known) |
| Root revision provider; History representation; Root checkpoints | A durable aggregate head, a canonical checkpoint per revision with a full relation vector | Milestone 1, D16, D18 | State that the revision is the version set (no head in milestone 1) and that a "checkpoint" is the anchor of D18/C09, not a per-revision full copy |
| Invariant 11 "a restore never rewrites historical rows" | Absolute | D18 | Add the one sanctioned exception: compaction rewrites the row at the cut-off (`compacted_at`) |
| Terminology, Restore modes, Goals | "Restore" is used for both the bin and the revision | D20 | "Restore" is the bin only; "revert" is the operation on revisions (`revertToRevision()`) |
| Unwrapped legacy audit flow; invariant 13 | An unwrapped write closes the epoch; re-enable needs an atomic baseline | D6, D13, D22 | Best-effort revisions are labelled and never offer "restore everything"; disabling versioning drops the history; re-enabling forces a snapshot at the first save |
| Decisions still open (1 to 10) | Open | D6, D17, D18, D23, D24, D25 | Answer each (see section C below) |
| Prior decisions under review (2 to 4) | Reopened by C01 to C07 | D6, D17, D24 | Replace by the resolved text |
| Concurrency | `lock_version` for scalar forms | D23 | `lock_version` covers the root and its translations; a translation write increments it; relations do not |

### B. What the spec lacks (to add)

- **Capture quality and labels:** add `partial`, `schema-mismatch`, `definition-mismatch` next to `atomic`, `best_effort`, `incomplete` (D13, I02).
- **Schema epoch** per table with a definition fingerprint, a column on every version row, and the lazy baseline (D13); the walk back never crosses an epoch.
- **Reconstruction rule** of C08 and the complete-anchor rule of C09 (nearest complete `SNAPSHOT` at or before R, applied forward, written once; the anchor is read back after the insert).
- **Compaction** (D18): merged diff as default, snapshot rewrite as an option, anchor required, cut-off by age or count, manual and off by default.
- **Hard delete** (D17): coordinator, one transaction, snapshot first, atomic with history removal, second retention period, purge command, origin data carried in the snapshot, limits of a recovery (comments, ratings, media files).
- **Excluded from the guarantee**, declared: `RecordOrigin`, lock columns, media (milestone 1), comments and ratings, symmetric `related()` (D8, D21, D24).
- **Versioning setting**: translation models are not versioned; the child follows the root; switching off needs a user confirmation and keeps the hard-delete snapshots (D10, D22).
- **Permissions and side effects** (D26, D27): `history`, `revert`, approvals, locks, translation suppression during a revert.
- **Tables and columns:** `core_versions`, `core_versions_sets` (D15), no `deleted_at` / `is_deleted` / `updated_at`, `compacted_at` (D19), `change_type` values (D20), `reverted_from_set_id` foreign key versus purge and compaction.
- **Overtrue detached** (D14): what Core absorbs, attribution, config keys, requirement removed.
- **Required tests:** reproduction of the multi-step `DIFF` failure; an anchor with a defaulted column; each schema-drift case; compaction leaves every retained revision identical (both variants); the anchor invariant check; `lock_version` increments on a translation write; a revert does not trigger automatic translation; hard delete is atomic and leaves no orphan origin; `history` and `revert` permissions; the sequence-gap check without `withTrashed()`.
- **Review and approval gate:** nine Critical findings now, not seven; the two gaps outside the document (Filament page guard, renderable for `StaleModelLockingException`) still stand and now matter for D23.

### C. Answers to the "Decisions still open" of the spec

1. Candidate A, atomic scope with fail-closed unsupported writes: adopted as option C (atomic where Core writes, labelled `best-effort` elsewhere), D6.
2. Durable head across hard delete: no head in milestone 1; a hard-deleted root is restorable from its snapshot (D17).
3. Normalized relation items: confirmed (I03, milestone 1).
4. Globally stable identities: deferred (D25, I01).
5. Full scalar state on every canonical checkpoint, with DIFF/SNAPSHOT kept as audit strategies: D18, C08, C09.
6. Best-effort as audit-only: yes, permanently without "restore everything" (D6).
7. Bypasses: rejected or labelled `best-effort` as in D6, D17; disabling versioning is a purge (D22).
8. Revision provider for models without optimistic locking: the two counters stay independent (D23).
9. Retention reachability: anchors and the compaction rule (D18), hard-delete snapshots (D17).
10. CMS category semantics: categories, tags, locations and contributors are `reference` (D2); `related()` is outside the guarantee (D24); which pivot attributes are authoritative is **still open**.

### D. Design work that does not exist yet and the spec must still produce

1. **The hook that creates the owner's version when only a translation changes, and the grouping of the writes of one operation into one revision** (D10, mandatory).
2. The aggregate revision scope on the paths that lack it today (Filament, `CrudService::insert`, root plus translations).
3. The hard-delete coordinator and the reworked `ClearExpiredModels`.
4. The epoch fingerprint and its detection at the end of a migration.
5. The scoped revert context that suppresses automatic translation.
6. The pivot attributes that are authoritative for each CMS relation.

### E. Not part of the spec but surfaced and still open

The tag detach on soft delete (separate defect); whether forms show relations (D23); whether a revert on a record locked by another user is refused; what `versions:compact` does until it is redefined; the config key and schedule of the hard-delete purge; the metadata kept in a snapshot.

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
