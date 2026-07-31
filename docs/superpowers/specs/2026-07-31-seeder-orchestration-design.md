# Seeder Orchestration and Reconciliation — Design

**Status:** Approved design, not implemented

**Date:** 2026-07-31

**Modules:** Core (mechanism) + AI, CMS, ERP, MES (consumers)

**Scope:** the production seeding path only. `Dev*` seeders and their bulk fixture
generation via `BatchSeeder` are untouched by this design.

---

## Problem

Seeders are meant to run on every update and release, against a **populated production
database**. They align configuration — settings, permissions, roles, entities, presets,
cron jobs — and must never disturb customer data. The current implementation cannot make
that guarantee.

`database/seeders/DatabaseSeeder.php` globs `Modules/*/database/seeders/*.php`, orders the
modules by `module.json` priority, skips anything prefixed `Dev`, and calls the rest in
sequence. Four defects follow from that design.

**Execution order is wrong.** `MES` declares `"requires": ["ERP"]` but carries
`"priority": 2` against ERP's `99`, so MES seeds **before** its own dependency. `AI`, `CMS`
and `ERP` all carry `99`, so their relative order is whatever `Module::allEnabled()` happens
to return — not a decision anyone made. Within a module the order is alphabetical by
filename: `ItalianTaxCodesSeeder` runs after `ERPDatabaseSeeder` only because `E` precedes
`I`.

**Idempotency is reinvented per seeder.** Three unrelated patterns coexist:
`firstOrCreate()` in `ERPDatabaseSeeder::ensureDomainPermissions()`, a `pluck()`-then-filter
in `DevERPDatabaseSeeder::seedOpportunityStages()`, and `Seeder::seedSettingDefinitions()`.
None of them expresses which fields belong to the code and which belong to the operator.

**The same work is done twice.** `CoreDatabaseSeeder::defaultSettings()` walks the 107
models returned by `models()` and calls `class_uses_trait()` seven times per model — 749
hierarchy traversals where 107 would do. `getModelsWithApprovals()` then re-walks the
filesystem with `File::files()` + `class_exists()` + reflection, bypassing `HelpersCache`
entirely, to compute a fact the first pass already held. `defaultSettings()` also issues a
batched existence check at line 407 and then repeats `->where('name', ...)->exists()` per
row inside the transaction.

**Cleanup can destroy operator data.** `deleteRefuses()` issues `forceDelete()` — bypassing
the soft deletes the table already has — on every derived setting whose model no longer
carries the corresponding trait. The model list comes from `models()`, which sees **enabled
modules only**. Disabling a module therefore causes the next release to permanently delete
all of its settings, with no warning and no recovery.

## Decisions

| # | Decision | Rationale |
|---|----------|-----------|
| 1 | No process-level parallelism | Four of five modules write `core_settings`; concurrent writers on a `UNIQUE` index during a release trade correctness for a sub-second gain |
| 2 | Explicit dependency graph replaces `priority` | `priority` cannot express `requires`, and ties leave order undefined |
| 3 | Field-level drift semantics | Structural fields follow the code; the operator's chosen value is never overwritten |
| 4 | Uniform bulk upsert + targeted cache flush | No version history exists on `Setting` to preserve; a single code path is cheaper and safer than two |
| 5 | `seeded_value` baseline, not a boolean flag | Drift is *computed*, so it cannot desynchronize from reality |
| 6 | Per-node atomicity, loud stop, in-run resume | A partially applied release must be recoverable without replaying completed work |
| 7 | Cleanup keyed on module state | Distinguishes "disabled" from "removed"; `seeded_value IS NULL` protects rows the seeder never wrote |

### Why not parallelism

The production seeders write roughly 95 hand-written definitions (47 Core, 23 AI, 17 CMS,
6 ERP, 2 MES) plus the settings derived from model capabilities — a few hundred rows, well
under a second once batched. The cost today is duplicated reflection and per-row queries,
not throughput.

Parallelism is additionally blocked by two hard constraints. Four modules declare
`runtimeSettingDefinitions()` and write the same table, and `Permission` is written by both
Core (`permission:refresh`) and ERP (`ensureDomainPermissions()`), so module-level fan-out
puts concurrent writers on uniquely-indexed tables. Separately, `ParallelTaskRunner` is
built on `spatie/fork`, and **a transaction cannot cross a fork** — coarse-grained
parallelism and decision 6 are mutually exclusive.

The dependency graph is required for correctness regardless. Once it exists, running
disjoint levels concurrently becomes an opt-in flag over the existing `ParallelTaskRunner`,
so nothing in this design forecloses it.

### Why not versioning on `Setting`

`Setting` uses `HasApprovals` and `HasCache`, not `HasVersions`. Adding versioning was
considered and rejected on four grounds, of which the third is decisive and independent of
which trait is chosen:

1. **Circularity** — `version_strategy_{table}` rows governing model versioning live *in*
   `core_settings`.
2. **Noise** — the derived settings produce hundreds of authorless version records on first
   install, against a `versions` table already active on ~88 tables since 2026-07-30.
3. **Incompatibility** — `upsert()` bypasses Eloquent events, so any event-based versioning
   is silently skipped exactly where the seeder writes. Closing that hole means abandoning
   the optimization.
4. **Wrong semantics** — versioning offers *revert*, which on a structural realignment
   would restore a shape the code no longer supports.

Using the upstream `Overtrue\LaravelVersionable\Versionable` trait instead of Core's
`HasVersions` resolves only (1). It also makes `Setting` the one model whose versioning
cannot be switched off from the backoffice — it would not consult
`PerModelSettingResolver` — and excludes its history from the tooling built on
`VersionWriterInterface`, `VersionChangeType`, and selective attribute encryption. Settings
are where credentials live, so losing encryption there is the wrong trade.

Auditing *operator* edits to settings remains a legitimate want. It is a separate product
change, decided on its own merits, and Core's own `HasVersions` is the correct trait for it.

## Execution order

Each seeder declares its dependencies:

```php
public static function dependsOn(): array
{
    return [ERPDatabaseSeeder::class];
}
```

The orchestrator collects the non-`Dev` seeders of enabled modules, builds the graph, and
performs a topological sort with a deterministic tie-break (module name, then class name)
so that two runs against the same code always produce the same order.

- `module.json` `requires` become **implicit edges**: every MES seeder depends on every ERP
  seeder without restating it. `dependsOn()` refines ordering *within* a module.
- A **cycle** raises before any write, printing the cycle path.
- A dependency **owned by a disabled or absent module** is an error, not a silent skip.

`priority` retains its other nwidart roles and stops governing seeding.

A consequence is intended: the `warn(...)` + `return` pattern in
`DevERPDatabaseSeeder::seedOpportunityStages()` becomes a hard failure. With the graph the
precondition is guaranteed, so a missing `Entity` is a genuine defect that must stop the
release rather than a tolerated condition.

## Reconciliation

A single `SeedReconciler` replaces the three ad-hoc patterns. Definitions declare the
semantics of each field:

```php
SeedDefinition::for(Setting::class)
    ->identity(['name'])
    ->structural(['type', 'group_name', 'description', 'choices'])
    ->initial(['value'])
    ->ownedBy('CMS');
```

`structural` fields are realigned on every release; `initial` fields are written at
creation and never touched again. This is decision 3 expressed as data.

The algorithm is **three queries per definition set**, independent of set size:

1. one `whereIn(identity)` select **with `withTrashed()`**, reading current state;
2. an in-memory diff producing four sets — *create*, *realign*, *restore*, *unchanged*;
3. one `upsert()` plus one `restore()` for revived rows, inside a single transaction on the
   model's connection.

Each set writes a different column list, and the distinction is the whole point:

| Set | Columns written |
|---|---|
| *create* | identity + structural + initial + `module` + `seeded_value` |
| *realign* | structural only — `value` and `seeded_value` are left alone |
| *restore* | `deleted_at` only — the operator's `value` survives the round trip |
| *unchanged* | none |

`seeded_value` is written **only when the seeder supplies the initial value**, that is on
*create* and during backfill. A structural realignment must never move the baseline:
doing so would silently reclassify a drifted row as untouched and expose it to deletion.

The *restore* branch is load-bearing. `core_settings.name` carries a `UNIQUE` index and
MySQL does **not** exempt soft-deleted rows from it, so a soft-deleted setting whose module
is re-enabled must be restored rather than re-inserted. Without this branch the release
after a module is re-enabled fails on a duplicate key.

### Consequences of bypassing observers

`upsert()` does not fire Eloquent events, which is where the speed comes from. Two
behaviours currently provided by `SettingObserver` move to explicit steps:

- `saving()` normalizes `'' → null`. This moves into definition construction, where it
  belongs — it is a rule about the data, not a side effect of saving.
- `saved()` and `deleted()` call `SettingsCacheCoordinator::flushSetting()`. These are
  replaced by **one targeted flush at the end of the run**, which also removes the blanket
  `Artisan::call('cache:clear')` that currently empties the entire production cache on
  every release.

## Removing duplicated work

Mostly subtraction:

- **One pass over `models()`.** Compute each model's trait set once; the seven capability
  checks become `in_array` lookups against it. 749 hierarchy traversals become 107.
- **`getModelsWithApprovals()` is deleted.** `HasApprovals` membership comes from the
  single pass. One full filesystem scan disappears.
- **`defaultSettings()` and `defaultApprovalSettings()` merge** into one step that emits
  definitions and hands them to the reconciler once.
- **`permission:refresh` becomes a declared graph node** rather than an Artisan call in the
  middle of a method, so dependent seeders can order against it.

## Ledger and resume

Table `core_seed_runs`: `run_id`, `node`, `status`, `content_hash`, `started_at`,
`finished_at`, `error`.

Each node is one transaction on its connection. On failure: that node rolls back, execution
stops, the error is recorded, and the command exits non-zero.

**Resume is scoped to the interrupted run.** `--resume` skips nodes that completed
successfully under the same `run_id`. Skipping across releases on an unchanged
`content_hash` is deliberately *not* the default: the database may have changed by means the
seeder cannot observe, and since reconciliation now costs three queries, the saving does not
justify trusting an unverified assumption.

`content_hash` is recorded for the release report. An opt-in `--skip-unchanged` over it is
**deferred**: a hash that faithfully represents what a node would write requires extracting
its definitions without executing the seeder, which the first implementation does not
provide. Shipping the flag over a weaker hash would skip nodes whose content did change —
worse than not having the flag.

## Cleanup by module state

Two columns are added to `core_settings`:

```php
$table->string('module')->nullable()->comment('Owning module, null when hand-created');
$table->json('seeded_value')->nullable()->comment('Last value written by the seeder');
```

Module state is derived from what nwidart already exposes: `Module::allEnabled()` for
enabled, `Module::all()` for present-on-disk; an owner in neither has been removed.

Drift is **computed**, never asserted: `touched = (value !== seeded_value)`. A stored
boolean would depend on every write path remembering to raise it, and a flag that lies about
"touched" is precisely the input to a **delete** decision. The computed comparison also
yields "reset to default" for free and correctly reports a value the operator manually
restored to its default as untouched.

| Module state | Drift | Action |
|---|---|---|
| Enabled | — | Normal reconciliation |
| Present, disabled | No | Hard delete — identical to the definition, recreated on re-enable |
| Present, disabled | Yes | Soft delete — customization stays recoverable |
| Absent from disk | — | Force delete |
| — | `seeded_value IS NULL` | Never touched — not seeder-managed |

Comparison runs on **decoded values**, not raw JSON strings; key order or `1` vs `1.0` must
not read as drift.

### Backfill

On the first run of the new reconciler, every row claimed by a definition receives
`seeded_value = the definition's initial value`, **without modifying `value`**. Drift is
then correct on existing installations from that point forward.

One accepted edge case: if a bugfix changed a default *after* a row was created, an
untouched row reads as touched. The error favours preservation over deletion.

## Testing

| Area | Coverage |
|---|---|
| Graph | Cycle detection, missing dependency, deterministic ordering — pure unit tests, no database |
| Reconciler | Field-level semantics, the `restore` branch against the `UNIQUE` index, drift detection |
| Cleanup | Four module states × touched/untouched, plus `seeded_value IS NULL` |
| Order regression | MES runs after ERP — the originating defect, pinned by test |
| Resume | Run interrupted mid-way; on relaunch completed nodes do not re-execute |

Tests live in `Modules/Core/tests/`. Per `AGENTS.md`, any stub classes belong in
`Modules/Core/tests/Stubs/` with registered PSR-4 namespaces, never inline in test files.

## Out of scope

- Process-level parallelism (decision 1; the graph leaves it available later)
- `Dev*` seeders and `BatchSeeder` fixture generation
- Versioning of operator edits to `Setting` — a separate product decision
- Any change to `Modules/*/module.json` `priority` values, which retain their other roles
