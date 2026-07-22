# Core Version Set Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace independent and queued Core history writes with one synchronous, single-connection version-set pipeline that can atomically record and restore scalar models and explicitly described pivot rows.

**Architecture:** Core owns a scoped version-set manager, one history writer, relation descriptors, restore orchestration, and set-level retention. Each set uses a local numeric primary key plus a globally unique UUID and contains ordered change rows. The first release enforces one real database connection per set and fails before a second connection writes; a recovery-capable multi-connection coordinator is the next mandatory design milestone.

**Tech Stack:** Laravel 12, PHP 8.4, Eloquent model events and transactions, Pest 4, supported Laraplate database drivers.

**Spec:** `docs/superpowers/specs/2026-07-21-aggregate-version-sets-design.md`

---

**Workspace rule:** Run Artisan, Pint, PHPStan, and tests from the Laraplate application root. `Modules/Core` is a nested Git repository; stage and commit only the exact Core paths listed in each task. The parent documentation files are committed separately by the parent repository owner.

**Migration rule:** The development database was rebuilt with `migrate:fresh`, so this plan intentionally creates `2019_05_31_042933_create_version_sets_table.php` and modifies the immediately following existing versions migration. It does not add a production backfill migration.

**Atomicity rule:** Only entry points wrapped by `VersionSetManagerInterface::run()` are aggregate-atomic. Compatibility model saves receive an implicit one-model set but do not absorb later unrelated pivot queries.

**Schema transition rule:** Task 2 introduces `version_set_id`, `sequence`, and `change_type` as nullable compatibility columns so the existing writers remain executable while the sole writer is still RED. Task 4 backfills no production data (the database baseline is fresh), makes all three columns required in the edited base migration, and turns on the final invariant that every new version row belongs to one ordered set. No release or module pilot may occur between those two tasks.

**Sequencing rule:** The stack-level `../docs/superpowers/plans/2026-07-19-model-connection-affinity.md` overlaps `HasVersions`, `CrudService`, `CompactVersions`, and their tests. Complete its affected Core work before this plan, or designate this plan as the owner of those exact overlaps during one coordinated implementation. Do not execute both plans concurrently against those files.

### Task 1: Characterize the existing writer paths

**Files:**

- Create: `Modules/Core/tests/Integration/Versioning/VersionWriterCharacterizationTest.php`
- Create: `Modules/Core/tests/Stubs/Versioning/VersionedArticle.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasVersionsTest.php`

- [x] **Step 1: Write failing characterization tests**

Create the stub as a real test class outside the Pest file. Prove that one create and one update currently can reach different persistence paths, and replace the async-job expectation with the desired invariant:

```php
it('writes one synchronous history row for one model transition', function (): void {
    Bus::fake();

    $article = VersionedArticle::query()->create(['title' => 'First']);

    expect($article->versions()->count())->toBe(1);
    Bus::assertNotDispatched(CreateVersionJob::class);
});
```

Also spy on the configured version model and assert that an update emits exactly one row rather than one from Core plus one from `Overtrue\LaravelVersionable\Versionable::bootVersionable()`.

- [x] **Step 2: Run the focused tests and confirm the failure**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionWriterCharacterizationTest.php Modules/Core/tests/Integration/Helpers/HasVersionsTest.php
```

Expected: FAIL because versioning is asynchronous and the vendor lifecycle still writes directly.

- [x] **Step 3: Record all production persistence callers in the test PHPDoc**

The characterization must cover `HasVersions::createVersion()`, `HasVersions::createInitialVersion()`, `VersioningService::createVersion()`, `Version::createForModel()`, and the vendor lifecycle callbacks. This list becomes the deletion/delegation checklist for Task 4.

- [x] **Step 4: Commit the red tests**

```bash
rtk git -C Modules/Core add tests/Integration/Versioning/VersionWriterCharacterizationTest.php tests/Stubs/Versioning/VersionedArticle.php tests/Integration/Helpers/HasVersionsTest.php
rtk git -C Modules/Core commit -m "test(core): characterize version history writers"
```

### Task 2: Add version-set persistence and schema invariants

**Files:**

- Create: `Modules/Core/database/migrations/2019_05_31_042933_create_version_sets_table.php`
- Modify: `Modules/Core/database/migrations/2019_05_31_042934_create_versions_table.php`
- Modify: `Modules/Core/app/Enums/CoreTables.php`
- Create: `Modules/Core/app/Enums/VersionSetKind.php`
- Create: `Modules/Core/app/Enums/VersionChangeType.php`
- Create: `Modules/Core/app/Models/VersionSet.php`
- Modify: `Modules/Core/app/Models/Version.php`
- Create: `Modules/Core/tests/Integration/Versioning/VersionSetSchemaTest.php`

- [x] **Step 1: Write the failing schema and relationship test**

Assert table ordering, unique `uuid`, nullable pre-persist root columns, self-reference restriction, version-set foreign key, unique set sequence, enum casts, both model relationships, and the temporary nullable compatibility state for `version_set_id`, `sequence`, and `change_type`.

```php
it('orders version rows deterministically inside one set', function (): void {
    $set = VersionSet::factory()->create();

    Version::factory()->for($set)->create(['sequence' => 1]);

    expect(fn () => Version::factory()->for($set)->create(['sequence' => 1]))
        ->toThrow(QueryException::class);
});
```

- [x] **Step 2: Run the schema test and confirm failure**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionSetSchemaTest.php
```

Expected: FAIL because `vend_versions_sets` and the new columns do not exist.

- [x] **Step 3: Implement the migrations and models**

Add `CoreTables::VersionSets = 'vend_versions_sets'`. Use `MigrateUtils` for portable timestamps and explicit index names within supported identifier limits. Store `root_id` as a string so integer, UUID, and dynamic keys share one representation. Add `VersionSet::versions()` ordered by `sequence`, `VersionSet::revertedFrom()`, and `Version::versionSet()`. Add `version_set_id`, `sequence`, and `change_type` as nullable only for the Task 2 compatibility window; `relation_path` and `subject_key` remain nullable by design. Confirm the Core migrations run on every configured connection that can host a versioned root.

Use a foreign key compatible with the configured user model only where the current Core migration convention already supports it; otherwise keep the current nullable actor-column convention and cover it with the existing user configuration tests.

- [x] **Step 4: Rebuild and run the focused test**

```bash
rtk php artisan migrate:fresh --seed
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionSetSchemaTest.php
```

Expected: PASS.

- [x] **Step 5: Commit schema and model work**

```bash
rtk git -C Modules/Core add database/migrations/2019_05_31_042933_create_version_sets_table.php database/migrations/2019_05_31_042934_create_versions_table.php app/Enums/CoreTables.php app/Enums/VersionSetKind.php app/Enums/VersionChangeType.php app/Models/VersionSet.php app/Models/Version.php tests/Integration/Versioning/VersionSetSchemaTest.php
rtk git -C Modules/Core commit -m "feat(core): add aggregate version sets"
```

### Task 3: Implement scoped single-connection transaction state

**Files:**

- Create: `Modules/Core/app/Versioning/Contracts/VersionSetManagerInterface.php`
- Create: `Modules/Core/app/Versioning/Data/VersionSetRoot.php`
- Create: `Modules/Core/app/Versioning/Data/VersionSetOptions.php`
- Create: `Modules/Core/app/Versioning/ActiveVersionSet.php`
- Create: `Modules/Core/app/Versioning/VersionSetManager.php`
- Create: `Modules/Core/app/Versioning/Exceptions/MultipleVersionConnectionsNotSupportedException.php`
- Create: `Modules/Core/app/Versioning/Exceptions/VersionSetRootMismatchException.php`
- Modify: `Modules/Core/app/Providers/CoreServiceProvider.php`
- Create: `Modules/Core/tests/Integration/Versioning/VersionSetManagerTest.php`

- [x] **Step 1: Write failing transaction-state tests**

Cover successful commit, exception rollback, nested same-root joining, nested root mismatch, one set per root in a multi-root batch, an empty/no-op scope leaving no row, a second connection pre-enlisted and rejected before its callback's first query, state reset after success/failure, and no active state visible in a separately dispatched job.

```php
it('rolls back business data and its version set together', function (): void {
    expect(fn () => app(VersionSetManagerInterface::class)->run(
        VersionSetRoot::forModel($article),
        function () use ($article): void {
            $article->updateOrFail(['title' => 'Changed']);
            throw new RuntimeException('abort');
        },
    ))->toThrow(RuntimeException::class);

    expect($article->fresh()->title)->not->toBe('Changed')
        ->and(VersionSet::query()->count())->toBe(0);
});
```

- [x] **Step 2: Run the manager test and confirm failure**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionSetManagerTest.php
```

Expected: FAIL because the manager and scoped state do not exist.

- [x] **Step 3: Implement state and transaction management**

Bind both the manager and active state with `$this->app->scoped(...)`. `run()` resolves the root connection, calls that connection's `transaction()`, locks an existing root with `lockForUpdate()`, and resets state in `finally`. Nested calls increment depth and join only when root and connection match.

`enlist()` stores the first normalized connection name and is called before the first business query. If another name is requested, throw `MultipleVersionConnectionsNotSupportedException` before executing the enlisted work. Nested joins must reuse the exact active Eloquent root instance; reject a separately loaded instance of the same row so stale attributes, originals, or loaded relations cannot re-enter the scope. Delete or avoid persisting a set whose scope produced no version rows. Do not store writable state in Laravel Context or serialize it onto jobs.

- [x] **Step 4: Run the manager tests**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionSetManagerTest.php
```

Expected: PASS.

- [x] **Step 5: Commit the manager**

```bash
rtk git -C Modules/Core add app/Versioning/Contracts/VersionSetManagerInterface.php app/Versioning/Data app/Versioning/ActiveVersionSet.php app/Versioning/VersionSetManager.php app/Versioning/Exceptions app/Providers/CoreServiceProvider.php tests/Integration/Versioning/VersionSetManagerTest.php
rtk git -C Modules/Core commit -m "feat(core): manage single connection version sets"
```

### Task 4: Establish one synchronous history writer

**Files:**

- Create: `Modules/Core/app/Versioning/Contracts/VersionWriterInterface.php`
- Create: `Modules/Core/app/Versioning/Data/VersionChange.php`
- Create: `Modules/Core/app/Versioning/VersionWriter.php`
- Modify: `Modules/Core/app/Models/Concerns/HasVersions.php`
- Modify: `Modules/Core/app/Models/Version.php`
- Modify: `Modules/Core/app/Services/VersioningService.php`
- Modify: `Modules/Core/database/migrations/2019_05_31_042934_create_versions_table.php`
- Modify: `Modules/Core/app/Providers/CoreServiceProvider.php`
- Delete: `Modules/Core/app/Jobs/CreateVersionJob.php`
- Delete: `Modules/Core/app/Events/ModelVersioningRequested.php`
- Modify: `Modules/Core/tests/Integration/Versioning/VersionWriterCharacterizationTest.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasVersionsTest.php`
- Modify: tests that reference `CreateVersionJob` or `ModelVersioningRequested`, discovered with `rg -n "CreateVersionJob|ModelVersioningRequested" Modules/Core/tests`

- [ ] **Step 1: Extend the red tests for sequence and implicit sets**

Assert that two changes inside an explicit scope share `version_set_id`, receive sequences `1, 2`, and contain the same set UUID. Assert an unwrapped model save creates one implicit singleton set synchronously but is reported as a non-atomic compatibility path. Assert canonical payloads: create uses an empty original and full after-image; update uses changed before/after images captured around the query; delete uses the full before-image and empty contents; force delete retains prior history.

- [ ] **Step 2: Run focused tests and confirm failure**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionWriterCharacterizationTest.php Modules/Core/tests/Integration/Helpers/HasVersionsTest.php
```

Expected: FAIL because there is no sole writer or sequence allocation.

- [ ] **Step 3: Implement the writer and neutralize duplicate callbacks**

`VersionWriter::write(VersionChange $change)` must require or create an active set, verify the pre-enlisted model connection, allocate the next sequence from scoped state, and create `Version` synchronously. `HasVersions` must capture pending dirty keys and before-images in `updating`/`deleting`, then give those snapshots to the writer in `updated`/`deleted`. It must own the lifecycle callbacks and reuse only the vendor helper methods and relationships; it must prevent `Versionable::bootVersionable()` from registering its direct writer and force-delete purge callbacks.

Once every production persistence path delegates to the writer, edit the base versions migration so `version_set_id`, `sequence`, and `change_type` are non-nullable. Extend the writer/schema regression to prove a raw version row without those values is rejected. This closes the Task 2 compatibility window before the task can pass.

Delegate `Version::createForModel()` and `VersioningService::createVersion()` to `VersionWriterInterface` as compatibility boundaries. Remove after-commit history dispatch and the event once `rg` shows no production or test caller. Keep version-strategy resolution and encryption behavior in the typed change builder.

- [ ] **Step 4: Run writer and existing model-version tests**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionWriterCharacterizationTest.php Modules/Core/tests/Integration/Helpers/HasVersionsTest.php Modules/Core/tests/Integration/Models/VersionModelTest.php Modules/Core/tests/Integration/Services/VersioningServiceTest.php
```

Expected: PASS with no queued essential history.

- [ ] **Step 5: Commit the sole-writer change**

```bash
rtk git -C Modules/Core add app/Versioning app/Models/Concerns/HasVersions.php app/Models/Version.php app/Services/VersioningService.php app/Providers/CoreServiceProvider.php database/migrations/2019_05_31_042934_create_versions_table.php tests/Integration/Versioning tests/Integration/Helpers/HasVersionsTest.php tests/Integration/Models/VersionModelTest.php tests/Integration/Services/VersioningServiceTest.php
rtk git -C Modules/Core add -u app/Jobs/CreateVersionJob.php app/Events/ModelVersioningRequested.php
rtk git -C Modules/Core commit -m "refactor(core): centralize synchronous version writes"
```

### Task 5: Integrate final approval application

**Files:**

- Modify: `Modules/Core/app/Services/Crud/CrudService.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php`
- Modify: `Modules/Core/tests/Integration/Services/CrudServiceRequestScenariosTest.php`
- Create: `Modules/Core/tests/Integration/Versioning/ApprovedVersionSetTest.php`

- [ ] **Step 1: Write failing approval lifecycle tests**

Use the existing approval factories and service entry point. Assert that proposal capture and rejection create no version set, final approval applies the business change and its version rows in one transaction, the set actor is the approver, `reason` is `approval:<approval-key>`, and an approved no-op creates no set.

```php
it('opens the set only when final approval applies the change', function (): void {
    $approval = $this->proposeArticleTitleChange();

    expect(VersionSet::query()->count())->toBe(0);

    $this->actingAs($approver);
    $this->crud->doApproveOperation($approval);

    expect(VersionSet::query()->sole()->reason)
        ->toBe('approval:' . $approval->getKey());
});
```

- [ ] **Step 2: Run and confirm failure**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/ApprovedVersionSetTest.php Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php Modules/Core/tests/Integration/Services/CrudServiceRequestScenariosTest.php
```

Expected: FAIL because approval application is not version-set-aware.

- [ ] **Step 3: Wrap only the persisted approval application**

Resolve the approved model/root and connection before its save, then call `VersionSetManagerInterface::run()` around the final application. Do not open a set while `saving` merely captures a proposal. Use the authenticated approver as actor and the canonical approval reason; the approval record remains the source for proposer and decision history. Roll back the applied model change if version writing fails.

- [ ] **Step 4: Run approval and transaction regressions**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/ApprovedVersionSetTest.php Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php Modules/Core/tests/Integration/Services/CrudServiceRequestScenariosTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit approval integration**

```bash
rtk git -C Modules/Core add app/Services/Crud/CrudService.php tests/Integration/Versioning/ApprovedVersionSetTest.php tests/Integration/Helpers/HasApprovalsTest.php tests/Integration/Services/CrudServiceRequestScenariosTest.php
rtk git -C Modules/Core commit -m "feat(core): version final approved changes atomically"
```

### Task 6: Add explicit versioned-relation descriptors and pivot capture

**Files:**

- Create: `Modules/Core/app/Versioning/Contracts/VersionedRelationDescriptorInterface.php`
- Create: `Modules/Core/app/Versioning/Contracts/VersionedAggregateDefinitionInterface.php`
- Create: `Modules/Core/app/Versioning/Contracts/VersionedRelationRegistryInterface.php`
- Create: `Modules/Core/app/Versioning/VersionedRelationRegistry.php`
- Create: `Modules/Core/app/Versioning/Data/VersionedRelationState.php`
- Create: `Modules/Core/app/Versioning/Concerns/HasVersionedPivot.php`
- Create: `Modules/Core/app/Versioning/Exceptions/InvalidVersionedRelationDescriptorException.php`
- Modify: `Modules/Core/app/Providers/CoreServiceProvider.php`
- Create: `Modules/Core/tests/Stubs/Versioning/ArticleLabel.php`
- Create: `Modules/Core/tests/Stubs/Versioning/ArticleLabelDescriptor.php`
- Create: `Modules/Core/tests/Integration/Versioning/VersionedPivotLifecycleTest.php`

- [ ] **Step 1: Write failing descriptor and pivot lifecycle tests**

Cover duplicate descriptors, relation paths absent from the aggregate definition, partial/complete coverage, empty/nullable/mutable identity declarations, canonical key order, attach, pivot update, explicit detach, sync replacement, pre-delete snapshot, rollback, and a pivot-only set with no synthetic root version.

```php
it('captures a hard deleted composite pivot before it disappears', function (): void {
    $manager->run(VersionSetRoot::forModel($article), function () use ($article): void {
        $article->labels()->detach($this->label->getKey());
    });

    $version = Version::query()->where('change_type', VersionChangeType::Deleted)->sole();

    expect($version->subject_key)->toBe([
        'article_id' => $article->getKey(),
        'label_id' => $this->label->getKey(),
    ]);
});
```

- [ ] **Step 2: Run and confirm failure**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionedPivotLifecycleTest.php
```

Expected: FAIL because descriptors and pivot capture do not exist.

- [ ] **Step 3: Implement explicit registration and model-event capture**

Bind the registry as a singleton and reject collisions by normalized `root morph class + relation path`. Require a module-owned aggregate definition that enumerates registered paths and declares `partial` or `complete` coverage. `HasVersionedPivot` resolves its descriptor, writes creates/updates normally, and writes deletions from `deleting` before a hard delete. It serializes only descriptor identity columns in declared order.

Do not add an `id` to composite test pivots. Do not use raw query deletion in the supported relation adapter. Where standard `detach()` bypasses custom pivot events, provide a Core mutation adapter that loads and deletes pivot models explicitly, then require registered relations to use that adapter.

- [ ] **Step 4: Run pivot and base Pivot regressions**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionedPivotLifecycleTest.php Modules/Core/tests/Feature/Models/FieldPivotBehaviorTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit relation infrastructure**

```bash
rtk git -C Modules/Core add app/Versioning app/Providers/CoreServiceProvider.php tests/Stubs/Versioning tests/Integration/Versioning/VersionedPivotLifecycleTest.php
rtk git -C Modules/Core commit -m "feat(core): capture explicitly described pivot changes"
```

### Task 7: Implement forward aggregate restore and concurrency checks

**Files:**

- Create: `Modules/Core/app/Versioning/Contracts/AggregateVersionRestorerInterface.php`
- Create: `Modules/Core/app/Versioning/AggregateVersionRestorer.php`
- Create: `Modules/Core/app/Versioning/RestorationMode.php`
- Create: `Modules/Core/app/Versioning/Events/AggregateVersionRestored.php`
- Create: `Modules/Core/app/Versioning/Exceptions/StaleAggregateVersionException.php`
- Create: `Modules/Core/app/Versioning/Exceptions/UnrestorableVersionSetException.php`
- Modify: `Modules/Core/app/Models/Version.php`
- Modify: `Modules/Core/app/Providers/CoreServiceProvider.php`
- Create: `Modules/Core/tests/Integration/Versioning/AggregateVersionRestorerTest.php`
- Create: `Modules/Core/tests/Integration/Versioning/VersionSetConcurrencyTest.php`

- [ ] **Step 1: Write failing restoration tests**

Cover refusal of complete restore for a partial aggregate definition, scalar rollback, subject recreation/removal/update, restoration to the state after a target set, repeated restore after intervening changes, no-op restore leaving no set, `reverted_from_set_id`, expected-revision conflict, existing-root lock, hard-deleted-root history lock and key collision, version capture remaining active, business observer suppression via `RestorationMode`, after-commit event dispatch, and rollback when one descriptor fails.

```php
it('records a restore as a new forward version set', function (): void {
    $restored = app(AggregateVersionRestorerInterface::class)->restore(
        target: $target,
        expectedCurrentSetId: $current->getKey(),
    );

    expect($restored->kind)->toBe(VersionSetKind::Revert)
        ->and($restored->reverted_from_set_id)->toBe($target->getKey())
        ->and($restored->getKey())->toBeGreaterThan($current->getKey());
});
```

- [ ] **Step 2: Run and confirm failure**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/AggregateVersionRestorerTest.php
```

Expected: FAIL because aggregate restore does not exist.

- [ ] **Step 3: Implement deterministic reconstruction and forward restore**

Reconstruct state from complete sets ordered by local set `id` and row `sequence`. Resolve relation rows only through the registered `relation_path` and canonical `subject_key`. Run restore inside a new managed transaction with `kind = revert`, lock the root, compare the expected current set, keep `VersionWriter` enabled, and scope only non-idempotent business observers through `RestorationMode`.

Dispatch `AggregateVersionRestored` after commit with root identity, new set UUID, and target set UUID. The event contains no historical contents.

Run `VersionSetConcurrencyTest` against the project's real MySQL/PostgreSQL CI service, not SQLite, and prove that a concurrent stale writer cannot pass the root lock/revision check. The test may be isolated from the default SQLite suite by the existing integration-test environment mechanism, but the release gate requires evidence from a database with real row locks.

- [ ] **Step 4: Run restore and existing version replay regressions**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/AggregateVersionRestorerTest.php Modules/Core/tests/Integration/Versioning/VersionSetConcurrencyTest.php Modules/Core/tests/Integration/Models/VersionModelTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit aggregate restore**

```bash
rtk git -C Modules/Core add app/Versioning app/Models/Version.php app/Providers/CoreServiceProvider.php tests/Integration/Versioning/AggregateVersionRestorerTest.php tests/Integration/Versioning/VersionSetConcurrencyTest.php tests/Integration/Models/VersionModelTest.php
rtk git -C Modules/Core commit -m "feat(core): restore aggregate version sets forward"
```

### Task 8: Replace per-row retention with set-level safety

**Files:**

- Create: `Modules/Core/app/Versioning/VersionSetRetentionService.php`
- Modify: `Modules/Core/app/Services/VersioningService.php`
- Modify: `Modules/Core/app/Console/CompactVersions.php`
- Modify: `Modules/Core/app/Models/Concerns/HasVersions.php`
- Create: `Modules/Core/tests/Integration/Versioning/VersionSetRetentionServiceTest.php`
- Modify: `Modules/Core/tests/Integration/Services/VersioningServiceTest.php`

- [ ] **Step 1: Write failing retention tests**

Assert that retention removes whole unreferenced sets, preserves the reconstruction checkpoint, preserves revert targets, refuses partial history, serializes maintenance with a root lock, and leaves set-managed rows untouched when reconstructability cannot be proven.

- [ ] **Step 2: Run and confirm failure**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionSetRetentionServiceTest.php Modules/Core/tests/Integration/Services/VersioningServiceTest.php
```

Expected: FAIL because retention still deletes individual model rows.

- [ ] **Step 3: Implement safe set-level retention**

Remove per-model `skip(...)->each->delete()` and snapshot purge for set-managed rows. Route compaction through `VersionSetRetentionService`, delete only complete sets in one root-scoped transaction, and leave history intact if the service cannot prove a retained reconstruction base. Keep legacy behavior only behind an explicit check for pre-set rows during the compatibility window.

- [ ] **Step 4: Run retention and command tests**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning/VersionSetRetentionServiceTest.php Modules/Core/tests/Integration/Services/VersioningServiceTest.php Modules/Core/tests/Feature/Console/CompactVersionsTest.php
```

If the command test has a different existing path, locate it with `rg -l "CompactVersions" Modules/Core/tests` and run that exact file.

- [ ] **Step 5: Commit retention safety**

```bash
rtk git -C Modules/Core add app/Versioning/VersionSetRetentionService.php app/Services/VersioningService.php app/Console/CompactVersions.php app/Models/Concerns/HasVersions.php tests/Integration/Versioning/VersionSetRetentionServiceTest.php tests/Integration/Services/VersioningServiceTest.php
rtk git -C Modules/Core commit -m "refactor(core): retain complete version sets"
```

### Task 9: Core documentation and release gate

**Files:**

- Modify: `Modules/Core/docs/rag/MODULE.md`
- Modify: `Modules/Core/README.md`
- Modify: `Modules/Core/docs/rag/GLOSSARY.md`
- Modify: `Modules/Core/docs/GLOSSARY.md`
- Modify: `Modules/Core/config/versionable.php`
- Modify: public PHPDoc in `Modules/Core/app/Models/Concerns/HasVersions.php`
- Modify: relevant setting descriptions located by `rg -n "keep_versions|async.*version|version strategy" Modules/Core`

- [ ] **Step 1: Update shipped-behavior documentation**

Document synchronous essential history, pre-query before-image capture, explicit aggregate transaction entry points, non-atomic implicit singleton limitations, set-level retention, force-delete history preservation, approval application, single-connection rejection, relation descriptor ownership, forward restore, and the pending multi-connection recovery milestone. Remove claims that normal history is created after commit by a job and qualify history as append-only until whole-set retention.

- [ ] **Step 2: Run the complete Core versioning suite**

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Versioning Modules/Core/tests/Integration/Helpers/HasVersionsTest.php Modules/Core/tests/Integration/Models/VersionModelTest.php Modules/Core/tests/Integration/Services/VersioningServiceTest.php Modules/Core/tests/Feature/Models/FieldPivotBehaviorTest.php
rtk vendor/bin/pint --dirty
rtk composer phpstan -- --memory-limit=2G Modules/Core/app/Versioning Modules/Core/app/Models/Concerns/HasVersions.php Modules/Core/app/Models/Version.php
```

Expected: all tests pass, Pint reports no remaining changes after its final run, and PHPStan reports no errors in the changed Core surface.

- [ ] **Step 3: Verify sole-writer and queue invariants**

```bash
rg -n "CreateVersionJob|ModelVersioningRequested" Modules/Core
rg -n "Version::createForModel|versionModel.*::create|new Version" Modules/Core/app
```

Expected: the removed job/event have no matches; every remaining compatibility caller delegates to `VersionWriterInterface`, and no independent production persistence path remains.

- [ ] **Step 4: Commit documentation and configuration**

```bash
rtk git -C Modules/Core add docs/rag/MODULE.md README.md docs/rag/GLOSSARY.md docs/GLOSSARY.md config/versionable.php app/Models/Concerns/HasVersions.php
rtk git -C Modules/Core commit -m "docs(core): document aggregate version sets"
```

## Completion gate

Do not begin the CMS pilot until Tasks 1–9 pass and a code review verifies the sole-writer search, canonical before/after images, transaction rollback evidence, state cleanup, approval application, composite identity behavior, force-delete preservation, and set-level retention. Do not enable a second database connection; instead start a separate multi-connection recovery design after the CMS pilot produces operational evidence.
