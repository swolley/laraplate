# CMS Versioned Categories Pilot Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prove the generic Core version-set engine against the real `Content::categories()` composite pivot, grouping Content scalar changes and category sync rows under one atomic set without claiming that every Content-owned relation is already restorable.

**Architecture:** CMS registers a partial `Content` aggregate definition and a `categories` relation descriptor using the natural key `content_id, taxonomy_id`. A CMS aggregate mutation service opens one Core-managed set per Content root, and `ContentUpserter` uses it inside the existing import transaction. Full Content restore stays disabled until translations/dynamic contents and every authoritative relation have descriptors and tests.

**Tech Stack:** Laravel 12, PHP 8.4, Eloquent custom Pivot and belongsToMany relations, Core version-set contracts, Pest 4.

**Spec:** `docs/superpowers/specs/2026-07-21-aggregate-version-sets-design.md`

**Prerequisite:** Complete every task and completion gate in `docs/superpowers/plans/2026-07-21-core-version-set-foundation.md`.

---

**Workspace rule:** Run Artisan, Pint, PHPStan, and tests from the Laraplate application root. `Modules/CMS` is a nested Git repository; stage and commit only exact CMS paths. Do not modify CMS pivot schema to add a surrogate ID: historical identity is the existing composite key.

**Coverage rule:** This pilot registers `Content` as `partial` with only `categories` covered. It may exercise relation-scoped restoration in tests, but it must not expose complete Content restore through a controller, Filament action, or public service contract.

### Task 1: Register the partial Content aggregate and categories identity

**Files:**

- Create: `Modules/CMS/app/Versioning/ContentVersionedAggregate.php`
- Create: `Modules/CMS/app/Versioning/ContentCategoriesVersionedRelation.php`
- Modify: `Modules/CMS/app/Providers/CMSServiceProvider.php`
- Modify: `Modules/CMS/app/Models/Pivot/Categorizable.php`
- Modify: `Modules/CMS/tests/Integration/Models/ContentCategoriesPivotKeysTest.php`
- Create: `Modules/CMS/tests/Integration/Versioning/ContentCategoriesDescriptorTest.php`

- [ ] **Step 1: Write failing descriptor and model tests**

Assert the aggregate root is `Content`, coverage is partial, the only covered path is `categories`, the pivot class is `Categorizable`, identity columns are exactly `content_id, taxonomy_id` in that order, and registration occurs only while CMS is enabled.

```php
it('uses the existing natural key as historical category identity', function (): void {
    $descriptor = app(VersionedRelationRegistryInterface::class)
        ->for(Content::class, 'categories');

    expect($descriptor->pivotClass())->toBe(Categorizable::class)
        ->and($descriptor->identityColumns())->toBe(['content_id', 'taxonomy_id'])
        ->and($descriptor->aggregate()->coverage())->toBe(AggregateCoverage::Partial);
});
```

Also assert the database table and Eloquent relation do not require an `id`, and `Categorizable` remains a Core Pivot with composite identity.

- [ ] **Step 2: Run and confirm failure**

```bash
rtk php artisan test --compact Modules/CMS/tests/Integration/Versioning/ContentCategoriesDescriptorTest.php Modules/CMS/tests/Integration/Models/ContentCategoriesPivotKeysTest.php
```

Expected: FAIL because CMS has not registered versioning descriptors or added pivot capture.

- [ ] **Step 3: Implement the CMS descriptors**

Implement immutable descriptor classes with no Core dependency on CMS types. Add Core's versioned-pivot concern to `Categorizable`. Register the aggregate definition before its relation descriptor in `CMSServiceProvider::boot()`. The relation restore implementation locates rows by both natural-key columns and never inserts a historical surrogate ID.

The descriptor resolves the root from `content_id` before a mutation and pre-enlists the Content connection. It rejects a category pivot whose root uses a different connection from the active set.

- [ ] **Step 4: Run descriptor tests**

```bash
rtk php artisan test --compact Modules/CMS/tests/Integration/Versioning/ContentCategoriesDescriptorTest.php Modules/CMS/tests/Integration/Models/ContentCategoriesPivotKeysTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit CMS registration**

```bash
rtk git -C Modules/CMS add app/Versioning/ContentVersionedAggregate.php app/Versioning/ContentCategoriesVersionedRelation.php app/Providers/CMSServiceProvider.php app/Models/Pivot/Categorizable.php tests/Integration/Versioning/ContentCategoriesDescriptorTest.php tests/Integration/Models/ContentCategoriesPivotKeysTest.php
rtk git -C Modules/CMS commit -m "feat(cms): describe versioned content categories"
```

### Task 2: Add the managed Content aggregate mutation boundary

**Files:**

- Create: `Modules/CMS/app/Versioning/ContentAggregateMutationService.php`
- Create: `Modules/CMS/tests/Integration/Versioning/ContentAggregateMutationServiceTest.php`

- [ ] **Step 1: Write failing atomic lifecycle tests**

Cover existing and new Content roots, scalar-only update, category-only sync, combined scalar/category update, attach, explicit pivot update, detach, sync replacement, rollback, no-op, non-default Content connection, and second connection rejection before mutation.

```php
it('groups scalar and category changes in one set', function (): void {
    $service->run($content, function (Content $content) use ($category): void {
        $content->updateOrFail(['valid_to' => now()->addDay()]);
        $content->categories()->sync([$category->getKey()]);
    });

    $set = VersionSet::query()->sole();

    expect($set->versions)->toHaveCount(2)
        ->and($set->versions->pluck('sequence')->all())->toBe([1, 2])
        ->and($set->versions->pluck('version_set_id')->unique())->toHaveCount(1);
});
```

Add an explicit assertion that category `sync()` uses the Core relation mutation adapter and does not issue a raw delete that bypasses `Categorizable::deleting`.

- [ ] **Step 2: Run and confirm failure**

```bash
rtk php artisan test --compact Modules/CMS/tests/Integration/Versioning/ContentAggregateMutationServiceTest.php
```

Expected: FAIL because the CMS mutation boundary does not exist and standard sync deletion may bypass pivot events.

- [ ] **Step 3: Implement the service and supported relation mutation path**

`run(Content $content, Closure $operation)` creates `VersionSetRoot::forModel($content)`, pre-enlists the resolved connection, and delegates to `VersionSetManagerInterface`. For a new Content, persist the root before the manager finalizes the set identity. All supported category synchronization goes through the Core adapter that loads and saves/deletes custom pivot instances.

Do not silently fall back to `BelongsToMany::detach()` for a registered versioned relation. Reject a different active root and let exceptions roll back root, pivot, set, and version rows.

- [ ] **Step 4: Run lifecycle and existing category regressions**

```bash
rtk php artisan test --compact Modules/CMS/tests/Integration/Versioning/ContentAggregateMutationServiceTest.php Modules/CMS/tests/Integration/Models/ContentCategoriesPivotKeysTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit the CMS mutation boundary**

```bash
rtk git -C Modules/CMS add app/Versioning/ContentAggregateMutationService.php tests/Integration/Versioning/ContentAggregateMutationServiceTest.php
rtk git -C Modules/CMS commit -m "feat(cms): version content category mutations atomically"
```

### Task 3: Integrate one set per imported Content root

**Files:**

- Modify: `Modules/CMS/app/Import/Upserters/ContentUpserter.php`
- Modify: `Modules/CMS/tests/Feature/Import/ImportPipelineTest.php`
- Create: `Modules/CMS/tests/Feature/Import/ContentImportVersionSetTest.php`

- [ ] **Step 1: Write failing import integration tests**

Cover a single imported Content with scalar and category changes, a two-Content batch producing two root sets, failure rolling back the outer import and every contained set, an import no-op producing no new set, and an import that also supplies contributors/tags/locations being marked partial rather than advertised as completely restorable.

```php
it('creates one version set per content in a batch', function (): void {
    $this->importTwoContentsWithCategories();

    $sets = VersionSet::query()->with('versions')->orderBy('id')->get();

    expect($sets)->toHaveCount(2);
    $sets->each(fn (VersionSet $set) => expect($set->versions)->not->toBeEmpty());
});
```

- [ ] **Step 2: Run and confirm failure**

```bash
rtk php artisan test --compact Modules/CMS/tests/Feature/Import/ContentImportVersionSetTest.php Modules/CMS/tests/Feature/Import/ImportPipelineTest.php
```

Expected: FAIL because `ContentUpserter` does not open one managed set per root.

- [ ] **Step 3: Wrap each upsert, not the whole batch, in the CMS service**

Inject `ContentAggregateMutationService` into `ContentUpserter`. Put both Content saves and all relation operations inside one root scope, while only registered categories produce pivot versions in this pilot. The existing outer import transaction remains responsible for all-or-nothing batch import; nested database transactions use the same connection, while active version-set state opens and closes separately for each Content.

Do not claim the unregistered contributor, tag, location, related-content, translation/dynamic-content, media, origin, or reference changes are restorable. Preserve current import behavior and progress logging after successful persistence.

- [ ] **Step 4: Run import and versioning tests**

```bash
rtk php artisan test --compact Modules/CMS/tests/Feature/Import/ContentImportVersionSetTest.php Modules/CMS/tests/Feature/Import/ImportPipelineTest.php Modules/CMS/tests/Integration/Versioning/ContentAggregateMutationServiceTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit import integration**

```bash
rtk git -C Modules/CMS add app/Import/Upserters/ContentUpserter.php tests/Feature/Import/ContentImportVersionSetTest.php tests/Feature/Import/ImportPipelineTest.php
rtk git -C Modules/CMS commit -m "feat(cms): group imported content version changes"
```

### Task 4: Prove category restoration without exposing full Content restore

**Files:**

- Create: `Modules/CMS/tests/Integration/Versioning/ContentCategoriesRestoreTest.php`
- Modify: `Modules/CMS/app/Versioning/ContentCategoriesVersionedRelation.php`

- [ ] **Step 1: Write failing relation restore tests**

Cover state after a target set, category recreation after detach, removal of a later attach, restoration of pivot timestamps/attributes that are explicitly versionable, repeated restore, restore of a restore, stale expected revision, and failure rollback. Assert the public complete aggregate restorer rejects the partial Content definition.

```php
it('refuses to advertise a complete Content restore', function (): void {
    expect(fn () => $restorer->restore($target, $current->getKey()))
        ->toThrow(IncompleteAggregateCoverageException::class);
});
```

Use Core's internal relation-scoped test seam to exercise the descriptor restore algorithm; do not add a CMS route or action.

- [ ] **Step 2: Run and confirm failure**

```bash
rtk php artisan test --compact Modules/CMS/tests/Integration/Versioning/ContentCategoriesRestoreTest.php
```

Expected: FAIL until the CMS descriptor implements the complete category lifecycle.

- [ ] **Step 3: Complete category restore behavior**

Resolve historical subjects by `content_id, taxonomy_id`, use the supported pivot mutation adapter, keep Core version capture active, and let the new revert set record actual relation changes. Never recreate an old generated key. Derived CMS reconciliation listens to the Core after-commit event only for covered category state.

- [ ] **Step 4: Run restore and lifecycle tests**

```bash
rtk php artisan test --compact Modules/CMS/tests/Integration/Versioning/ContentCategoriesRestoreTest.php Modules/CMS/tests/Integration/Versioning/ContentAggregateMutationServiceTest.php
```

Expected: PASS, including explicit complete-restore refusal.

- [ ] **Step 5: Commit restore coverage**

```bash
rtk git -C Modules/CMS add app/Versioning/ContentCategoriesVersionedRelation.php tests/Integration/Versioning/ContentCategoriesRestoreTest.php
rtk git -C Modules/CMS commit -m "test(cms): prove versioned category restoration"
```

### Task 5: CMS documentation and pilot release gate

**Files:**

- Modify: `Modules/CMS/docs/rag/MODULE.md`
- Modify: `Modules/CMS/README.md`

- [ ] **Step 1: Document exact pilot coverage**

Document Core ownership, the CMS descriptor and mutation boundary, composite natural identity, import grouping, category restore behavior, and the `partial` coverage status. List the unregistered Content children and state that complete Content restore stays disabled until they are implemented.

- [ ] **Step 2: Run the CMS pilot suite**

```bash
rtk php artisan test --compact Modules/CMS/tests/Integration/Versioning Modules/CMS/tests/Integration/Models/ContentCategoriesPivotKeysTest.php Modules/CMS/tests/Feature/Import/ContentImportVersionSetTest.php Modules/CMS/tests/Feature/Import/ImportPipelineTest.php
rtk vendor/bin/pint --dirty
rtk composer phpstan -- --memory-limit=2G Modules/CMS/app/Versioning Modules/CMS/app/Import/Upserters/ContentUpserter.php Modules/CMS/app/Models/Pivot/Categorizable.php
```

Expected: all tests pass, Pint reports no remaining changes after its final run, and PHPStan reports no errors in the changed CMS surface.

- [ ] **Step 3: Audit bypass paths and coverage claims**

```bash
rg -n "categories\(\).*->(attach|detach|sync|syncWithoutDetaching|updateExistingPivot)|categorizables" Modules/CMS/app
rg -n "complete.*restore|aggregate.*restore" Modules/CMS/app Modules/CMS/docs
```

Inspect every match. Expected: supported category mutations enter through the CMS/Core adapter, raw table operations are absent from the supported path, and no route or action advertises complete Content restore.

- [ ] **Step 4: Commit CMS documentation**

```bash
rtk git -C Modules/CMS add docs/rag/MODULE.md README.md
rtk git -C Modules/CMS commit -m "docs(cms): document versioned categories pilot"
```

## Completion gate and next work

The pilot is complete only after a code review verifies natural identity, exact one-writer behavior, root/pivot rollback, one set per imported Content, partial-coverage refusal, repeated category restore, and mutation-path audit.

After that gate, prepare separate plans in this order:

1. remaining CMS Content-owned relations and translations/dynamic contents, promoting coverage to `complete` only after the full matrix passes;
2. Core multi-connection participant and recovery design, preserving the pre-enlist contract;
3. ERP aggregate definitions and nested children;
4. ACL relations under a separate security review.
