# Query memory and Filament performance implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close remaining P1/P2 items from `2026-07-09-large-dataset-query-patterns-design.md` for Core queries and Filament widgets.

**Architecture:** Apply Laravel iteration primitives (`lazy`, `chunk`, `cursor`) where full `get()` is unsafe; add eager loading and short cache where Filament lists/widgets repeat work. No new dependencies.

**Tech Stack:** Laravel 12, Eloquent, Filament 5, Pest

**Spec:** `docs/superpowers/specs/2026-07-09-large-dataset-query-patterns-design.md`

---

### Task 1: DatabaseEngine SQLite vector search — lazy iteration

**Files:**
- Modify: `Modules/Core/app/Search/Engines/DatabaseEngine.php` (`performSQLiteVectorSearch`)
- Test: `Modules/Core/tests/Feature/Search/DatabaseEngineSQLiteVectorSearchTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Models\ModelEmbedding;
use Modules\Core\Search\Engines\DatabaseEngine;
use Modules\Core\Tests\TestCase;

it('limits sqlite vector search without loading all embeddings into memory', function (): void {
    // Seed > limit embeddings; assert result count <= limit and method completes.
})->skip(fn (): bool => config('database.default') !== 'sqlite', 'SQLite only');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Feature/Search/DatabaseEngineSQLiteVectorSearchTest.php`
Expected: FAIL (full `get()` or missing early termination)

- [ ] **Step 3: Replace `get()` with `lazy()` in `performSQLiteVectorSearch`**

Iterate with `lazy(100)`, compute similarity per row, collect top matches up to `$builder->limit ?? 10`, stop early when enough results above threshold.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Feature/Search/DatabaseEngineSQLiteVectorSearchTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Modules/Core/app/Search/Engines/DatabaseEngine.php Modules/Core/tests/Feature/Search/DatabaseEngineSQLiteVectorSearchTest.php
git commit -m "perf(core): lazy sqlite vector search in DatabaseEngine"
```

---

### Task 2: HandleLicensesCommand listLicenses — lazy with eager load

**Files:**
- Modify: `Modules/Core/app/Console/HandleLicensesCommand.php` (`listLicenses`, ~line 118)
- Test: `Modules/Core/tests/Feature/Console/HandleLicensesCommandTest.php` (extend or create)

- [ ] **Step 1: Write the failing test**

Assert `list` action completes with many licenses without loading all at once (mock or seed factory count + assert command exit 0).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=HandleLicenses`
Expected: FAIL if still using `get()`

- [ ] **Step 3: Change `listLicenses` to use `lazy(100)`**

```php
$licenses = License::with('user')->lazy(100);
```

Iterate for table output instead of mapping a full Collection.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=HandleLicenses`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git commit -m "perf(core): lazy listLicenses in HandleLicensesCommand"
```

---

### Task 3: HasClosureTable rebuild — chunk processing

**Files:**
- Modify: `Modules/Core/app/Helpers/HasClosureTable.php` (`rebuild`)
- Test: `Modules/Core/tests/Feature/Helpers/HasClosureTableRebuildTest.php` (create)

- [ ] **Step 1: Confirm trait exists in Core checkout**

If absent, skip task and note in spec as not yet landed.

- [ ] **Step 2: Write failing test** with hierarchical fixture > chunk size; assert rebuild completes.

- [ ] **Step 3: Replace `get()->keyBy()` with `chunk(100)` or `lazy(100)`**

Process `insertClosures` per model without holding full tree in memory. Remove `with('children')` if not required per row.

- [ ] **Step 4: Run test and commit**

```bash
git commit -m "perf(core): chunk HasClosureTable rebuild"
```

---

### Task 4: User permissions — cache hot path

**Files:**
- Modify: `Modules/Core/app/Models/User.php` (~line 276)
- Test: `Modules/Core/tests/Feature/Models/UserPermissionsTest.php` (extend or create)

- [ ] **Step 1: Write test** asserting permissions resolved without repeated full-table queries when called twice.

- [ ] **Step 2: Wrap `Permission::query()->get()->sort()` in `Cache::remember`**

Key e.g. `core.permissions.all`; TTL from config or 3600s; invalidate on permission CRUD if observers exist.

- [ ] **Step 3: Run test and commit**

```bash
git commit -m "perf(core): cache all permissions for User accessor"
```

---

### Task 5: DynamicContentsService cache warm-up

**Files:**
- Modify: `Modules/Cms/app/Services/DynamicContentsService.php`
- Test: `Modules/Cms/tests/Feature/Services/DynamicContentsServiceTest.php` (extend or create)

- [ ] **Step 1: Identify `rememberForever` blocks using `get()` on large queries**

- [ ] **Step 2: Use `lazy()` inside cache closure** for first warm-up when result set can exceed ~5k rows.

- [ ] **Step 3: Evaluate TTL** — replace `rememberForever` with bounded TTL if data changes often.

- [ ] **Step 4: Test warm-up + cache hit; commit**

```bash
git commit -m "perf(cms): lazy load on DynamicContentsService cache warm-up"
```

---

### Task 6: CoreStatsWidget — lazy load and cache

**Files:**
- Modify: `Modules/Core/app/Filament/Widgets/CoreStatsWidget.php`
- Test: `Modules/Core/tests/Feature/Filament/CoreStatsWidgetTest.php` (create)

- [ ] **Step 1: Write test** for widget stats array structure and cached second call.

- [ ] **Step 2: Add lazy widget if supported**

```php
protected static bool $isLazy = true;
```

- [ ] **Step 3: Cache `getStats()` body** — key `filament_dashboard_core_stats`, TTL 60s.

- [ ] **Step 4: Run test and commit**

```bash
git commit -m "perf(core): lazy and cache CoreStatsWidget stats"
```

---

### Task 7: Verify Contents Resource eager loading

**Files:**
- Modify: `Modules/Cms/app/Filament/Resources/Contents/ContentResource.php` (if missing `with`)
- Test: existing Contents list test or new Filament feature test

- [ ] **Step 1: Confirm `modifyQueryUsing` includes `with(['entity', 'preset', 'media'])`**

- [ ] **Step 2: Add if missing; assert query count on list page ≤ 5 for 25 rows**

- [ ] **Step 3: Commit if changed**

```bash
git commit -m "perf(cms): eager load Contents Filament table relations"
```

---

### Task 8: Optional — ListModifications tab count cache

**Files:**
- Modify: `Modules/Core/app/Filament/Resources/Modifications/Pages/ListModifications.php`

- [ ] **Step 1: Uncomment or add `Cache::remember` around grouped count query**

Use `config('core.filament.tabs_counts_ttl_seconds', 300)`.

- [ ] **Step 2: Test tab badges still correct after cache**

- [ ] **Step 3: Commit**

```bash
git commit -m "perf(core): cache modification tab badge counts"
```

---

## Already completed (no task)

- `CrudService` bulk update/delete/lock: `lazy(100)`
- `AfterLoginListener`: `first()` for available license
- `HandleLicensesCommand` license groups: `lazy(100)`
- `ListModifications::getTabs()`: single `groupBy` query
- `SearchEngineHealthTableWidget`: 300s cache on `getViewData()`
- Multiple Filament Resources: eager loads per `docs/filament-performance-recommendations.md`

## Final verification

- [ ] Run `vendor/bin/pint --dirty`
- [ ] Run `php artisan test --compact` on touched module tests
- [ ] Manual: load Dashboard, Modifications, Contents — check query count / TTFB
