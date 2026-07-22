# Aggregate Version Sets and Versioned Relations

## Status

- Date: 2026-07-21
- Status: approved for incremental implementation
- Scope: generic versioning infrastructure in Core, with CMS categories as the first relation pilot
- Database baseline: migrations may be edited in place because the development database has already been rebuilt with `migrate:fresh`

## Context

`HasVersions` currently records changes to one Eloquent model at a time. A business operation can instead update a root model and several pivot rows. Those writes do not share a durable version identity, so the history cannot answer which rows formed one operation and a revert cannot restore the aggregate consistently.

The current implementation also has more than one history writer: the vendor trait can write directly, Core can write synchronously through `VersioningService`, and the default Core path dispatches `CreateVersionJob` after commit. A queued write cannot be part of the transaction that changed the business data. Per-model retention can also delete a version row while leaving related history without a complete operation boundary.

The solution is a generic Core concept named a **version set**. A version set represents one logical business change to one aggregate root and contains every scalar-model or relation-row version produced by that change.

## Goals

- Give every version produced by one logical operation the same globally unique correlation UUID.
- Keep business writes and essential history writes atomic on one database connection.
- Version pivot creation, update, and deletion without relying on an auto-increment pivot key.
- Restore a complete aggregate to the state after a selected version set.
- Preserve history when restoring by recording the restore as a new version set.
- Keep the engine generic in Core while modules declare relation identity and restore semantics.
- Make interfaces connection-aware now and introduce multi-connection coordination as the mandatory next architecture milestone.

## Non-goals for the first implementation

- Distributed atomic commit across multiple database connections.
- Queued creation of essential version rows.
- Automatic discovery of every relationship from Eloquent metadata.
- Initial rollout to every CMS, ERP, and ACL pivot.
- Replaying arbitrary observer side effects during restore.
- Treating an unadapted standalone `save()` followed by later pivot queries as an atomic aggregate operation.

## Invariants

1. One Core writer is the only component allowed to persist `vend_versions` rows.
2. Every new version row belongs to exactly one `vend_versions_sets` row.
3. One version set has one aggregate root and one database connection in the first milestone.
4. Essential version rows are written synchronously inside the same database transaction as business data.
5. Sequence numbers are unique and strictly increasing inside a version set.
6. Pivot identity is described by stable business key columns, never by a generated pivot ID alone.
7. A restore never rewrites or deletes old history; it creates a new set with `kind = revert`.
8. Retention removes complete sets only, never individual rows from a retained set.
9. Jobs may compact or archive committed sets, but may not create history required for a correct restore.
10. A second enlisted connection fails before its first write until the multi-connection coordinator exists.
11. One set owns exactly one aggregate root; a batch creates one set per root.
12. An empty/no-op or approval-proposal scope leaves no persisted version set.

## Ownership boundary

| Concern | Owner |
|---|---|
| Version-set lifecycle, transaction boundary, writer, ordering, restore orchestration, retention | Core |
| Relation descriptors and stable identity columns | Owning module |
| Aggregate entry points that open a set | Owning module/application |
| Relation-specific restoration and derived-data reconciliation | Owning module |
| Cross-connection recovery protocol | Core, next milestone |

Core must not contain CMS table names, category rules, or ERP document semantics. A module opts a relation into aggregate versioning through an explicit descriptor registered with Core.

Each module also registers an aggregate definition that lists the covered scalar model and relation paths and marks coverage as `partial` or `complete`. Core refuses to advertise or execute a complete aggregate restore for a partial definition. This prevents a successful categories pilot from being mistaken for coverage of every `Content` child.

## Data model

### `vend_versions_sets`

Create this table before `vend_versions`. Its local primary key and global correlation identity have different jobs:

- `id`: unsigned bigint primary key used for local ordering and foreign keys;
- `uuid`: UUID with a unique index, exposed as the logical transaction/correlation ID and reusable across connection-local records in the future multi-connection design.

The remaining columns are:

| Column | Type | Meaning |
|---|---|---|
| `root_type` | nullable string | Morph class of the aggregate root; temporarily null only while a newly created root has no key |
| `root_id` | nullable string | Normalized root key; set before the transaction commits |
| `root_connection_ref` | nullable string | Root connection name for dynamic or non-default entities |
| `root_table_ref` | nullable string | Root table name for dynamic entities |
| configured user foreign key | nullable unsigned bigint | Actor whose action commits the operation; for an approved change this is the approver |
| `kind` | enum `change`, `revert` | Why the set exists |
| `reason` | nullable string | Optional bounded application-supplied reason, not arbitrary payload |
| `reverted_from_set_id` | nullable unsigned bigint FK | Target set when `kind = revert`; delete is restricted |
| timestamps | created/updated | Audit times |

The root tuple is required before commit. The manager may insert the set lazily after a newly created root receives its key, but it must reject commit if the root tuple is incomplete.

Every database connection that can host a versioned root must run the Core versioning migrations so its business rows, set row, and version rows can share local transactions and foreign keys.

For approval application, `reason` uses the bounded canonical form `approval:<approval-key>` so the original proposer and approval decision remain traceable through the approval record. Direct changes use the authenticated actor; system imports may use a null actor and a canonical import reason supplied by the import entry point.

### Changes to `vend_versions`

Modify the existing migration and model with:

| Column | Type | Meaning |
|---|---|---|
| `version_set_id` | unsigned bigint FK | Owning local set; delete cascades only when retention removes the complete set |
| `sequence` | unsigned integer | Deterministic order within the set |
| `change_type` | enum `created`, `updated`, `deleted` | Lifecycle transition represented by the row |
| `relation_path` | nullable string | Registered path such as `categories`; null for the root/scalar model |
| `subject_key` | nullable JSON | Canonically ordered stable identity for relation subjects |

Add a unique index on `version_set_id, sequence` and query indexes on `version_set_id` and the existing versionable columns. `subject_key` is data, not a database lookup expression; the registered descriptor determines which columns are queried during restore.

For a scalar/root row, the legacy `versionable_type, versionable_id` tuple identifies the affected model. For a relation row, that tuple deliberately identifies the aggregate root, while `relation_path` and `subject_key` identify the pivot subject. A composite key is therefore never coerced into `versionable_id`, and the root's existing `versions()` relation continues to return its complete aggregate history.

## Core contracts

### Version-set manager

The application-facing boundary is connection-aware from the start:

```php
interface VersionSetManagerInterface
{
    public function run(
        VersionSetRoot $root,
        Closure $operation,
        ?VersionSetOptions $options = null,
    ): mixed;

    public function enlist(string $connection): void;

    public function current(): ?ActiveVersionSet;
}
```

`run()` opens the database transaction, locks an existing root row for update, creates or resumes the active set, runs the operation, validates the root and enlisted connection, then commits. Exceptions roll back both business data and version rows.

Nested calls on the same connection join the active set and use an in-process stack depth. They must reuse the exact active Eloquent root instance: a separately loaded instance of the same database row is rejected because its in-process attributes, originals, and loaded relations can diverge. A nested call cannot change the aggregate root. A request for a second connection throws `MultipleVersionConnectionsNotSupportedException` before the second connection writes.

A batch or import that mutates multiple roots opens and closes one set per root. An outer ordinary database transaction may contain those sets, but an active root set never silently absorbs another root. A nested different-root call fails; it may proceed only after the outer root scope closes.

### Scoped state, not Laravel Context

`ActiveVersionSet` is a container-scoped, in-process service. It must be reset in `finally`, including after exceptions and in long-running workers. Laravel Context is not the source of truth because contextual data can be propagated to queued jobs. A separate correlation value may be copied into logs, but a job must never inherit an active writable version set.

### Sole version writer

`VersionWriterInterface` accepts a typed change record and the current active set. It allocates the next sequence and creates the version row synchronously. Vendor callbacks, `Version::createForModel()`, `VersioningService`, restores, and pivot capture all delegate to this writer. No other production path calls `save()` or `create()` on the version model.

During migration, compatibility saves may open an **implicit singleton set** for one model write. This is a best-effort, non-atomic compatibility record because the business query may already have committed in autocommit mode before an Eloquent `created` or `updated` callback opens the set. It does not retroactively absorb the business query or a later pivot query. Aggregate atomicity is guaranteed only for entry points explicitly wrapped in `VersionSetManagerInterface::run()`; critical entry points must not rely on the fallback.

## Relation descriptors

A module registers a descriptor for each versioned relation:

```php
interface VersionedRelationDescriptorInterface
{
    public function rootClass(): string;

    public function relationPath(): string;

    public function pivotClass(): string;

    /** @return non-empty-list<string> */
    public function identityColumns(): array;

    public function restore(VersionedRelationState $state): void;
}
```

The owning `VersionedAggregateDefinitionInterface` declares the root class, its registered relation paths, and coverage. Relation registration fails if its path is absent from the aggregate definition. Changing coverage to `complete` requires a module test proving every declared authoritative child mutation and restore path.

The descriptor is explicit and deterministic. For `Content::categories()`, the first pilot uses:

- root class: `Modules\CMS\Models\Content`;
- relation path: `categories`;
- pivot class: `Modules\CMS\Models\Pivot\Categorizable`;
- identity columns: `content_id`, `taxonomy_id`.

The writer serializes `subject_key` with keys in descriptor order. Restore resolves or recreates the row from those columns and never attempts to reuse a historical surrogate ID. Descriptors with ambiguous identity, nullable identity columns, or mutable identity semantics are rejected during registration.

## Capture semantics

### Scalar models

- `created`: `original_contents = []`; `contents` is the full after-image.
- `updated`: with DIFF, `original_contents` and `contents` contain the changed before/after attributes; with SNAPSHOT, both contain the full before/after versionable state. Restore never reconstructs a before-image by refreshing the already-updated row.
- `deleted`: `original_contents` is the full before-image and `contents = []`.
- restoring or clearing `deleted_at` is an update, not a new lifecycle type.

The pending before-image and dirty-key set are captured in `updating`/`deleting` before the business query. The writer consumes that in-process snapshot in `updated`/`deleted`. Managed transactions make both writes atomic; the implicit fallback remains explicitly non-atomic.

The Core integration must neutralize the vendor package's direct persistence callbacks so one Eloquent lifecycle event cannot produce duplicate rows.

`forceDelete()` records a deletion and never deletes historical sets. This intentionally replaces the vendor listener that currently purges history on force delete.

### Pivot models

Every versioned pivot is an explicit Pivot/MorphPivot class using the Core writer. Capture must cover attach/create, pivot-attribute update, detach/delete, and sync-induced insertions/deletions. Deletion state is captured from the `deleting` event, because `deleted` is too late for hard-deleted pivot data.

Database cascades and raw query-builder deletes bypass model events. Versioned relation code therefore performs explicit pivot model deletion inside the version-set transaction. A relation is not declared fully versioned while any supported mutation path still bypasses capture.

An operation that changes only pivots does not create a synthetic root version row. The version set's root columns establish ownership without polluting scalar history.

The aggregate root's resolved versioning strategy/switch governs its registered relations. A relation pivot does not independently disable history through a per-pivot setting. Mutating a registered versioned pivot without an active root scope is unsupported unless the relation adapter can resolve and explicitly open that root before its first query.

### Approval-mediated changes

Capturing an approval proposal is not a persisted business mutation and must not create a set. `CrudService::doApproveOperation()` opens the managed set only when the final approval applies the model change. The set actor is the approver, while `reason = approval:<approval-key>` links the set to the approval record containing the original proposer and decision trail. A rejected proposal and an approval that produces no data change leave no set.

## Restore semantics

Selecting set `S` means restore the aggregate to its state **after `S` completed**. The operation is a forward restore:

1. authorize the restore before opening the transaction;
2. start a new set `R` with `kind = revert` and `reverted_from_set_id = S.id`;
3. lock the root row and verify the caller's expected current set/revision;
4. calculate state from complete sets ordered by local `id`, then versions ordered by `sequence`;
5. restore scalar models and relation subjects using registered descriptors;
6. let the normal writer record every actual change in `R`;
7. validate invariants and commit;
8. dispatch one after-commit aggregate-restored event for cache, search, and derived-data reconciliation.

Before step 1, Core verifies that the aggregate definition is `complete`. A partial pilot may exercise a relation-scoped restore only through an internal test seam, but the general complete-aggregate restore API remains unavailable for that root.

Repeated restores are supported when state changed between them, and a later restore can target either an original set or a prior revert set. Restoring to the already-effective state is a no-op and leaves no empty set.

If a restore results in no actual version rows, the attempted no-op is not persisted as an empty set. Audit of denied or ineffective requests belongs to the application audit channel, not aggregate state history.

Deleting a subject after `S` causes restore to recreate it. Creating a subject after `S` causes restore to remove it. Updating pivot attributes causes restore to recover the values effective at `S`.

## Side effects during restore

Global `Model::withoutEvents()` is forbidden because it would also suppress version capture and unrelated safety behavior. Core exposes a scoped `RestorationMode` that observers and module services can inspect to suppress only non-idempotent business effects such as notifications or external API calls. Version capture remains enabled.

Modules reconcile caches, search indexes, projections, and other derived state from the committed aggregate through the after-commit event. External effects that cannot be safely replayed or reconciled must be explicitly excluded by the module descriptor and documented before that relation is enabled.

## Concurrency

An aggregate write locks the existing root row before changing the root or its versioned relations. The API accepts an expected current set/revision for interactive restore and update flows. A mismatch raises a concurrency exception instead of applying a restore over newer unseen work.

For a newly created root, there is no row to lock. Its containing transaction owns the uncommitted row; the version set is persisted after the key exists and before commit.

For a hard-deleted root, the restorer locks the target/root history set instead, checks that the historical root key is free, and recreates the row in the managed transaction. A key collision fails the restore. Soft-deleted roots are queried without global scopes and locked normally.

## Retention and compaction

`keep_versions` changes from a count of model rows to a policy evaluated over complete root-owned sets. Existing per-model deletion in `VersioningService`, snapshot purge behavior, and `CompactVersions` must not run against set-managed history until rewritten.

Retention rules:

- delete or archive a complete set with its version rows;
- retain the earliest required checkpoint for reconstructability;
- retain sets referenced by `reverted_from_set_id`;
- never leave a partial set;
- perform maintenance only after commit and under a root-scoped lock;
- validate reconstructability before deletion.

Until the set-level retention implementation passes reconstruction tests, set-managed history is not automatically pruned.

## Transaction and queue policy

The first milestone supports exactly one Eloquent connection per version set. It uses a real database transaction, not merely an application correlation scope. The manager calls the selected connection's `transaction()` API; all models, pivot rows, the version set, and version rows must use that connection.

The manager and relation descriptors pre-enlist the selected connection before the first business query. Detecting a connection only when the writer observes a completed mutation is too late and must not be treated as protection.

Jobs are suitable only for post-commit compaction, archival, indexing, and notifications that can be retried from committed state. They are not suitable for creating essential history.

## Module rollout

1. Core sole writer, schema, scoped manager, scalar restore, and set-level retention safety.
2. CMS pilot: `Content::categories()` and `Categorizable` through `ContentUpserter`, the final approval application path, and a direct aggregate service.
3. Remaining CMS relation descriptors after the pilot passes lifecycle, restore, and bypass audits.
4. ERP aggregates after defining document-root and nested-child boundaries.
5. ACL relations in a separate security design and rollout; they are excluded from the first implementation.
6. Multi-connection coordinator as the next Core architecture milestone.

The post-pilot inventory starts from existing explicit pivot models rather than table-name guessing: Core `Fieldable`/preset relations; CMS `Categorizable`, `Contributable`, `Locatable`, and `Relatable`; ERP `Contactable`, invoice-line delivery, and analytic-dimension pivots. CMS tags use the real `cms_taggables` table and still need an explicit custom MorphPivot before they can enter the versioning contract. ERP association models such as payment allocations require aggregate-child semantics even when they are normal Eloquent models rather than Pivot subclasses. `ModelHasRole` and other ACL relations remain outside this rollout until the security review.

## Multi-connection milestone

Interfaces record every requested connection now, but the first implementation rejects more than one. Opening one transaction per connection and committing them sequentially is not atomic: a late commit failure leaves earlier connections committed.

The next design must choose and test a recovery protocol before enabling multiple connections. The minimum acceptable design includes the shared set UUID, per-connection participant records and states, deterministic commit order, idempotent compensation or roll-forward recovery, crash recovery, retry ownership, and operator-visible incomplete-set diagnostics. Native two-phase commit may be evaluated per supported database but cannot be assumed across all Laraplate drivers.

## Test strategy

Core tests must prove:

- one writer and no duplicate history rows;
- same UUID and deterministic sequence for all rows in an operation;
- rollback removes both business and history writes;
- nested same-connection scopes join; a second connection fails before writing;
- active state is cleared after success and failure and is not inherited by a job;
- create, update, soft delete, hard delete, and scalar restore;
- canonical before/after images captured on the correct side of the business query;
- pivot create, update, delete, sync replacement, and composite identity;
- pivot-only sets do not fabricate root versions;
- restore to historical state, deletion/recreation, repeated restore, and conflict rejection;
- retention never leaves partial or unreconstructable sets;
- force delete preserves append-only history and empty/no-op scopes do not persist;
- approval proposal/rejection creates no set and final approved application creates one attributed set;
- multi-root batches create one set per root and nested different-root scopes fail;
- at least one lock/concurrency test runs on a real database driver because SQLite cannot prove row-lock behavior.

The CMS pilot must prove the same guarantees through the real `Content::categories()` relation, final approval application, and import entry point, including explicit detection of mutation paths that bypass pivot events. It proves the pivot engine and category slice only. It does not claim a complete `Content` aggregate restore: translations/dynamic contents, media, origin, references, contributors, tags, locations, related content, and other owned children require later descriptors and tests.

## Documentation contract

Implementation updates must keep these operational sources aligned with the shipped behavior:

- `Modules/Core/docs/rag/MODULE.md`;
- Core versioning configuration and public PHPDoc;
- CMS RAG/module documentation when the pilot is enabled;
- the versioning setting descriptions where asynchronous creation and per-row retention are currently implied.

## Readiness gates

Core implementation may start when all of the following remain true:

- the sole-writer rule is accepted;
- essential history becomes synchronous;
- the first release is explicitly single-connection;
- modules accept explicit relation descriptors and entry-point adaptation;
- ACL rollout remains separate;
- multi-connection recovery is treated as the next design milestone, not simulated with sequential commits.

The design is intentionally incremental. Passing the Core plan does not claim that every existing application write is aggregate-atomic; each module earns that guarantee when its mutation paths are registered, wrapped, and tested.
