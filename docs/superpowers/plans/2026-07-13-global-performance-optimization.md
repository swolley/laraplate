# Global Performance Optimization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve Laraplate performance across Core, ERP, CMS, AI, and Filament without changing business behavior or weakening module boundaries.

**Architecture:** This plan is intentionally conservative: every optimization starts with a regression or guardrail test, changes one narrow behavior at a time, preserves public method return types, and commits each subsystem separately. The first execution pass closes known Core and Filament memory/query hotspots, then moves into ERP workflow/reporting and AI batch processing. Database changes are limited to portable Laravel migrations and only where a real query pattern requires them.

**Tech Stack:** PHP 8.5, Laravel 12, Eloquent, Filament 5, Livewire 4, Pest, SQLite test database, MySQL/MariaDB/PostgreSQL/Oracle-compatible query patterns.

**Related Baseline:**
- `docs/superpowers/specs/2026-07-09-large-dataset-query-patterns-design.md`
- `docs/superpowers/plans/2026-07-09-query-memory-filament-performance.md`
- `.cursor/rules/03-performance-optimization.mdc`
- `.cursor/rules/09-database-guidelines.mdc`

---

## Execution Rules

- Do not run broad refactors while executing a task.
- Do not introduce new dependencies.
- Do not change method return types unless the task explicitly says so.
- Do not use `cursor()` with eager-loaded relationships.
- Keep SQLite tests green.
- Commit after each task that changes code.
- If a task uncovers changed user work in the same files, stop that task and inspect the diff before editing.
- If a performance optimization changes observable business output, revert the implementation and keep the test as a failing investigation artifact.

## Scope Split

This is a master plan covering independent subsystems. Execute in this order:

1. Baseline and safety checks.
2. Core memory/query hotspots.
3. Filament dashboard/list overhead.
4. ERP workflow and reporting hotspots.
5. AI batch command and RAG indexing hotspots.
6. Final cross-module verification.

Do not start ERP or AI tasks until the Core and Filament tasks in this plan have passed locally.

---

### Task 1: Capture Pre-Optimization Safety Baseline

**Files:**
- Read: `docs/superpowers/specs/2026-07-09-large-dataset-query-patterns-design.md`
- Read: `docs/superpowers/plans/2026-07-09-query-memory-filament-performance.md`
- Read: `.cursor/rules/03-performance-optimization.mdc`
- Read: `.cursor/rules/09-database-guidelines.mdc`

- [ ] **Step 1: Confirm dirty workspace state**

Run:

```bash
rtk git status --short
rtk git submodule status --recursive
```

Expected: command succeeds. Record which files are already modified before implementation. Do not revert unrelated changes.

- [ ] **Step 2: Run targeted preflight tests**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Search/SearchEngineContractTest.php
rtk php artisan test --compact Modules/Core/tests/Integration/Helpers/HasClosureTableTest.php
rtk php artisan test --compact Modules/Core/tests/Feature/Filament/WidgetsTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/StockMovementServiceTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/OperationalReportingServicesTest.php
rtk php artisan test --compact Modules/AI/tests/Integration/TranslateContentCommandTest.php
```

Expected: PASS or pre-existing failures documented before any code edit. If a preflight test fails, investigate and decide whether the failure blocks the related task.

- [ ] **Step 3: Confirm no dependency changes are needed**

Run:

```bash
rtk git diff -- composer.json composer.lock Modules/Core/composer.json Modules/ERP/composer.json Modules/CMS/composer.json Modules/AI/composer.json
```

Expected: no dependency change is required for this plan.

---

### Task 2: Core DatabaseEngine SQLite Vector Search - Stream Embeddings

**Files:**
- Modify: `Modules/Core/app/Search/Engines/DatabaseEngine.php`
- Create: `Modules/Core/tests/Integration/Search/DatabaseEngineSQLiteVectorSearchTest.php`

- [ ] **Step 1: Write the failing test**

Create `Modules/Core/tests/Integration/Search/DatabaseEngineSQLiteVectorSearchTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder as ScoutBuilder;
use Modules\Core\Models\ModelEmbedding;
use Modules\Core\Search\Engines\DatabaseEngine;

it('returns the top sqlite vector search matches without changing the public search shape', function (): void {
    config()->set('database.default', 'sqlite');

    $model = new class extends Model {
        protected $table = 'core_model_embeddings';
    };

    ModelEmbedding::query()->create([
        'model_type' => $model::class,
        'model_id' => 1,
        'embedding' => [1.0, 0.0, 0.0],
    ]);
    ModelEmbedding::query()->create([
        'model_type' => $model::class,
        'model_id' => 2,
        'embedding' => [0.9, 0.1, 0.0],
    ]);
    ModelEmbedding::query()->create([
        'model_type' => $model::class,
        'model_id' => 3,
        'embedding' => [0.0, 1.0, 0.0],
    ]);

    $builder = new ScoutBuilder($model, 'vector:[1,0,0]');
    $builder->limit = 2;

    $engine = new DatabaseEngine;
    $method = new ReflectionMethod(DatabaseEngine::class, 'performSQLiteVectorSearch');
    $method->setAccessible(true);

    $results = $method->invoke($engine, [1.0, 0.0, 0.0], $model, $builder);

    expect($results)->toHaveCount(2)
        ->and($results[0])->toHaveKeys(['id', 'similarity_score', 'embedding'])
        ->and($results[0]['similarity_score'])->toBeGreaterThanOrEqual($results[1]['similarity_score']);
});

it('keeps sqlite vector search implementation off full collection map pipelines', function (): void {
    $source = file_get_contents(base_path('Modules/Core/app/Search/Engines/DatabaseEngine.php'));

    expect($source)->not->toContain("->get()\n            ->map(")
        ->and($source)->toContain('->lazy(');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Search/DatabaseEngineSQLiteVectorSearchTest.php
```

Expected: FAIL on the source assertion because the current SQLite path uses `get()->map()`.

- [ ] **Step 3: Replace the SQLite full collection pipeline**

In `Modules/Core/app/Search/Engines/DatabaseEngine.php`, replace `performSQLiteVectorSearch()` with:

```php
private function performSQLiteVectorSearch(array $queryVector, Model $model, Builder $builder): array
{
    $limit = max(1, (int) ($builder->limit ?? 10));
    $results = collect();

    foreach (
        ModelEmbedding::query()
            ->where('model_type', $model::class)
            ->lazy(100) as $embedding
    ) {
        $stored_embedding = $embedding->embedding ?? [];

        if (! is_array($stored_embedding)) {
            $stored_embedding = [];
        }

        $normalized_embedding = array_values(array_map(
            static fn (mixed $value): float => (float) $value,
            $stored_embedding,
        ));

        $similarity = $this->calculateCosineSimilarity($queryVector, $normalized_embedding);

        if ($similarity <= 0.7) {
            continue;
        }

        $results->push([
            'id' => $embedding->id,
            'similarity_score' => $similarity,
            'embedding' => $normalized_embedding,
        ]);

        $results = $results
            ->sortByDesc('similarity_score')
            ->take($limit)
            ->values();
    }

    /** @var list<array<string, mixed>> */
    return $results->all();
}
```

- [ ] **Step 4: Run focused tests**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Search/DatabaseEngineSQLiteVectorSearchTest.php
rtk php artisan test --compact Modules/Core/tests/Integration/Search/SearchEngineContractTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add Modules/Core/app/Search/Engines/DatabaseEngine.php Modules/Core/tests/Integration/Search/DatabaseEngineSQLiteVectorSearchTest.php
git commit -m "perf(core): stream sqlite vector search results"
```

---

### Task 3: Core HasClosureTable - Rebuild Without Recursive Eager Loading

**Files:**
- Modify: `Modules/Core/app/Models/Concerns/HasClosureTable.php`
- Modify: `Modules/Core/tests/Integration/Helpers/HasClosureTableTest.php`

- [ ] **Step 1: Add a large-tree rebuild regression test**

Append this test to `Modules/Core/tests/Integration/Helpers/HasClosureTableTest.php`:

```php
it('rebuilds a larger closure tree without eager loading recursive children', function (): void {
    $root = ClosureTreeStubModel::query()->create(['parent_id' => null]);
    $parent_id = $root->id;

    for ($i = 0; $i < 120; $i++) {
        $node = ClosureTreeStubModel::query()->create(['parent_id' => $parent_id]);
        $parent_id = $node->id;
    }

    DB::enableQueryLog();

    ClosureTreeStubModel::rebuildClosure();

    $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");

    expect(DB::table('closure_tree_nodes_closure')->count())->toBeGreaterThan(120)
        ->and($queries)->not->toContain('select * from "closure_tree_nodes" where "closure_tree_nodes"."parent_id" in');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Helpers/HasClosureTableTest.php --filter="larger closure tree"
```

Expected: FAIL because `rebuildClosure()` and `insertClosures()` currently eager load children.

- [ ] **Step 3: Remove recursive eager loading**

In `Modules/Core/app/Models/Concerns/HasClosureTable.php`, change `rebuildClosure()` root loading to:

```php
static::query()
    ->whereNull('parent_id')
    ->orderBy('id')
    ->chunkById(100, static function (Collection $rootNodes): void {
        foreach ($rootNodes as $root) {
            self::insertClosures($root);
        }
    });
```

In `insertClosures()`, replace:

```php
$children = $model->children()->with('children')->get();
```

with:

```php
$children = $model->children()->orderBy('id')->get();
```

Keep the existing public method names and closure table row shape unchanged.

- [ ] **Step 4: Run focused tests**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Helpers/HasClosureTableTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add Modules/Core/app/Models/Concerns/HasClosureTable.php Modules/Core/tests/Integration/Helpers/HasClosureTableTest.php
git commit -m "perf(core): rebuild closure tables without recursive eager loading"
```

---

### Task 4: Core DynamicContentsService - Cache Per Type Instead Of Global Lists

**Files:**
- Modify: `Modules/Core/app/Services/DynamicContentsService.php`
- Create: `Modules/Core/tests/Integration/Services/DynamicContentsServiceCacheTest.php`

- [ ] **Step 1: Write behavior and invalidation tests**

Create `Modules/Core/tests/Integration/Services/DynamicContentsServiceCacheTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\Core\Services\DynamicContentsService;

it('clears all dynamic contents in-memory caches', function (): void {
    $service = DynamicContentsService::getInstance();

    $service->clearAllCaches();
    DynamicContentsService::reset();

    expect(DynamicContentsService::getInstance())->toBeInstanceOf(DynamicContentsService::class);
});

it('registers namespaced presettable memo keys for later invalidation', function (): void {
    $service = DynamicContentsService::getInstance();
    $reflection = new ReflectionMethod(DynamicContentsService::class, 'presettableMemoKey');
    $reflection->setAccessible(true);

    $key = $reflection->invoke($service, Modules\Core\Models\Pivot\Presettable::class);

    expect($key)->toBe('core.dynamic_contents.presettables.' . sha1(Modules\Core\Models\Pivot\Presettable::class));
});
```

- [ ] **Step 2: Run tests before editing**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Services/DynamicContentsServiceCacheTest.php
```

Expected: PASS for current invariants. This task is a refactor guarded by existing behavior, not a red test.

- [ ] **Step 3: Add per-type in-memory cache buckets**

In `Modules/Core/app/Services/DynamicContentsService.php`, replace the three single cache properties with keyed arrays:

```php
/** @var array<string, Collection<int, Entity>> */
private array $entities_cache = [];

/** @var array<string, Collection<int, Preset>> */
private array $presets_cache = [];

/** @var array<string, Collection<int, Presettable>> */
private array $presettables_cache = [];
```

Add this private helper:

```php
private function typeCacheKey(IDynamicEntityTypable $type): string
{
    return $type::class . ':' . $type->value;
}
```

- [ ] **Step 4: Filter in the database for entities**

In `fetchAvailableEntities()`, use the type cache key and add a database `where('type', $type->value)` before `get()`:

```php
$type_cache_key = $this->typeCacheKey($type);

if (isset($this->entities_cache[$type_cache_key])) {
    return $this->entities_cache[$type_cache_key];
}

$this->entities_cache[$type_cache_key] = Cache::memo()->rememberForever(
    $cache_key . '.' . sha1($type_cache_key),
    fn (): Collection => $entity_class::query()
        ->withoutGlobalScopes()
        ->where('type', $type->value)
        ->orderBy('is_default', 'desc')
        ->orderBy('name', 'asc')
        ->get(),
);

return $this->entities_cache[$type_cache_key];
```

- [ ] **Step 5: Filter presets and presettables in the database**

Apply the same type-keyed pattern to `fetchAvailablePresets()` and `fetchAvailablePresettables()`. Keep the existing returned `Collection` type. Use `where("{$entities_table}.type", $type->value)` for the joined presettable query.

- [ ] **Step 6: Keep invalidation complete**

Update `clearEntitiesCache()`, `clearPresetsCache()`, `clearPresettablesCache()`, and `clearAllCaches()` so each method resets the keyed arrays:

```php
$this->entities_cache = [];
$this->presets_cache = [];
$this->presettables_cache = [];
```

- [ ] **Step 7: Run focused tests**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Services/DynamicContentsServiceCacheTest.php
rtk php artisan test --compact Modules/Core/tests/Integration/Services/PerModelSettingResolverTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

Run:

```bash
git add Modules/Core/app/Services/DynamicContentsService.php Modules/Core/tests/Integration/Services/DynamicContentsServiceCacheTest.php
git commit -m "perf(core): scope dynamic contents caches by type"
```

---

### Task 5: Core HandleLicensesCommand - Lazy List Output

**Files:**
- Modify: `Modules/Core/app/Console/HandleLicensesCommand.php`
- Modify: `Modules/Core/tests/Feature/Console/HandleLicensesCommandTest.php`

- [ ] **Step 1: Add source-level guard test**

Append this assertion to the existing `covers listLicenses private method output path` test in `Modules/Core/tests/Feature/Console/HandleLicensesCommandTest.php`:

```php
$source = file_get_contents((new ReflectionClass(HandleLicensesCommand::class))->getFileName());
expect($source)->toContain("License::with('user')->lazy(100)")
    ->and($source)->not->toContain("License::with('user')->get()");
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Console/HandleLicensesCommandTest.php --filter="listLicenses"
```

Expected: FAIL because `listLicenses()` still uses `get()`.

- [ ] **Step 3: Change listLicenses to lazy iteration**

In `Modules/Core/app/Console/HandleLicensesCommand.php`, replace:

```php
$licenses = License::with('user')->get();
```

with:

```php
$licenses = License::with('user')->lazy(100);
```

Keep the `$remapped` array because Laravel Prompts `table()` needs all rows for display.

- [ ] **Step 4: Run focused test**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Console/HandleLicensesCommandTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add Modules/Core/app/Console/HandleLicensesCommand.php Modules/Core/tests/Feature/Console/HandleLicensesCommandTest.php
git commit -m "perf(core): lazily list licenses"
```

---

### Task 6: Filament Dashboard Widgets - Lazy And Short Cache

**Files:**
- Modify: `Modules/Core/app/Filament/Widgets/CoreStatsWidget.php`
- Modify: `Modules/CMS/app/Filament/Widgets/CMSStatsWidget.php`
- Modify: `Modules/Core/tests/Feature/Filament/WidgetsTest.php`
- Test: `Modules/CMS/tests/Feature/Filament/ResourceConfigurationTest.php`

- [ ] **Step 1: Add Core widget cache expectations**

In `Modules/Core/tests/Feature/Filament/WidgetsTest.php`, update `builds core stats widget data` to assert the widget is lazy:

```php
$property = new ReflectionProperty(CoreStatsWidget::class, 'isLazy');
$property->setAccessible(true);
expect($property->getValue())->toBeTrue();
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Filament/WidgetsTest.php --filter="core stats"
```

Expected: FAIL because `CoreStatsWidget` does not currently define `$isLazy`.

- [ ] **Step 3: Add lazy and cache to CoreStatsWidget**

In `Modules/Core/app/Filament/Widgets/CoreStatsWidget.php`, add:

```php
use Illuminate\Support\Facades\Cache;
```

Inside the class add:

```php
protected static bool $isLazy = true;
```

Wrap the database read in `getStats()`:

```php
return Cache::remember('filament.dashboard.core_stats', 60, function () use ($licenses_table, $users_table): array {
    $data = DB::table($licenses_table)->select([
        DB::raw('count(*) as total'),
        DB::raw("coalesce(sum(case when {$licenses_table}.valid_to >= now() or {$licenses_table}.valid_to is null then 1 else 0 end), 0) as active"),
        DB::raw("coalesce(sum(case when {$users_table}.id is not null then 1 else 0 end), 0) as occupied"),
    ])
        ->leftJoin($users_table, "{$licenses_table}.id", '=', "{$users_table}.license_id")
        ->first();

    return [
        Stat::make('Users', User::query()->count())
            ->description('Total registered users')
            ->descriptionIcon('heroicon-o-users')
            ->color('primary'),
        Stat::make('Active Licenses', "{$data->active} / {$data->total}")
            ->description('Currently valid licenses')
            ->descriptionIcon('heroicon-o-key')
            ->color('primary'),
        Stat::make('Occupied Licenses', "{$data->occupied} / {$data->active}")
            ->description('Active sessions')
            ->descriptionIcon('heroicon-o-user-plus')
            ->color('primary'),
    ];
});
```

- [ ] **Step 4: Apply same pattern to CMSStatsWidget**

In `Modules/CMS/app/Filament/Widgets/CMSStatsWidget.php`, add `Cache`, add `protected static bool $isLazy = true;`, and wrap the returned stats in:

```php
return Cache::remember('filament.dashboard.cms_stats', 60, static fn (): array => [
    Stat::make('Contents', Content::query()->count())
        ->description('Total contents')
        ->descriptionIcon('heroicon-o-pencil')
        ->color('info'),
    Stat::make('Contributors', Contributor::query()->count())
        ->description('Total contributors')
        ->descriptionIcon('heroicon-o-users')
        ->color('info'),
]);
```

- [ ] **Step 5: Run focused tests**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Filament/WidgetsTest.php
rtk php artisan test --compact Modules/CMS/tests/Feature/Filament/ResourceConfigurationTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

Run:

```bash
git add Modules/Core/app/Filament/Widgets/CoreStatsWidget.php Modules/CMS/app/Filament/Widgets/CMSStatsWidget.php Modules/Core/tests/Feature/Filament/WidgetsTest.php
git commit -m "perf(filament): lazily cache dashboard stats"
```

---

### Task 7: ERP InvoicePostingService - Preload Tax Codes For Invoice Lines

**Files:**
- Modify: `Modules/ERP/app/Services/Accounting/InvoicePostingService.php`
- Modify: `Modules/ERP/tests/Feature/InvoicePostingServiceTest.php`

- [ ] **Step 1: Add query-count regression test**

Append this test to `Modules/ERP/tests/Feature/InvoicePostingServiceTest.php`:

```php
/**
 * @return array{0: Company, 1: Invoice, 2: TaxCode}
 */
function erpInvoicePostingFixtureWithRepeatedTaxCode(): array
{
    $company = createInvoicePostingCompany('inv-tax-preload');

    Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Tax preload customer',
    ]);

    $tax_code = TaxCode::query()->create([
        'company_id' => $company->id,
        'code' => 'VAT22-PRELOAD',
        'kind' => 'vat',
        'country' => 'IT',
        'rate' => 22,
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => now()->toDateString(),
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'posted_at' => now(),
    ]);

    return [$company, $invoice, $tax_code];
}

it('preloads tax codes while posting invoices with repeated tax codes', function (): void {
    [$company, $invoice, $tax_code] = erpInvoicePostingFixtureWithRepeatedTaxCode();

    for ($i = 1; $i <= 8; $i++) {
        Modules\ERP\Models\InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'line_no' => $i,
            'description' => 'Taxed line ' . $i,
            'quantity' => '1.0000',
            'unit_price' => '10.0000',
            'tax_code_id' => $tax_code->id,
        ]);
    }

    Illuminate\Support\Facades\DB::enableQueryLog();

    app(Modules\ERP\Services\Accounting\InvoicePostingService::class)->post($invoice);

    $tax_code_queries = collect(Illuminate\Support\Facades\DB::getQueryLog())
        ->pluck('query')
        ->filter(static fn (string $query): bool => str_contains($query, 'erp_tax_codes'))
        ->count();

    expect($tax_code_queries)->toBeLessThanOrEqual(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/ERP/tests/Feature/InvoicePostingServiceTest.php --filter="preloads tax codes"
```

Expected: FAIL because `resolveAndSnapshotTaxes()` currently fetches the tax code inside the line loop.

- [ ] **Step 3: Preload tax codes once**

In `Modules/ERP/app/Services/Accounting/InvoicePostingService.php`, replace the tax-code lookup inside `resolveAndSnapshotTaxes()` with a keyed collection loaded before the loop:

```php
$tax_codes = TaxCode::query()
    ->withoutGlobalScopes()
    ->whereIn('id', $lines->pluck('tax_code_id')->filter()->unique()->values())
    ->get()
    ->keyBy('id');
```

Inside the loop, replace `TaxCode::query()->withoutGlobalScopes()->findOrFail($line->tax_code_id)` with:

```php
$tax_code = $tax_codes->get($line->tax_code_id);

if (! $tax_code instanceof TaxCode) {
    throw ValidationException::withMessages([
        'tax_code_id' => ['The selected tax code is invalid.'],
    ]);
}
```

- [ ] **Step 4: Run accounting tests**

Run:

```bash
rtk php artisan test --compact Modules/ERP/tests/Feature/InvoicePostingServiceTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/InventoryAccountingGoldenMasterTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add Modules/ERP/app/Services/Accounting/InvoicePostingService.php Modules/ERP/tests/Feature/InvoicePostingServiceTest.php
git commit -m "perf(erp): preload invoice posting tax codes"
```

---

### Task 8: ERP StockMovementService - Avoid Full FIFO Layer Scans

**Files:**
- Modify: `Modules/ERP/app/Services/Inventory/StockMovementService.php`
- Modify: `Modules/ERP/tests/Feature/StockMovementServiceTest.php`

- [ ] **Step 1: Add FIFO behavior guard for many layers**

Append this test to `Modules/ERP/tests/Feature/StockMovementServiceTest.php`:

```php
it('consumes only required fifo layers while preserving costing', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-fifo-many',
        'name' => 'Inv Fifo Many',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'FIFO Hub',
        'code' => 'FIFO-HUB',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Layered Item',
        'sku' => 'LAYER-1',
        'uom' => 'pcs',
        'costing_method' => 'fifo',
    ]);

    $service = app(StockMovementService::class);

    for ($i = 1; $i <= 30; $i++) {
        $service->recordInbound($company->id, $item->id, $warehouse->id, 1, (string) $i);
    }

    $out = $service->recordOutbound($company->id, $item->id, $warehouse->id, 3);

    assert_decimal_close('2.0000', (string) $out->unit_cost);

    $remaining = StockCostLayer::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->orderBy('id')
        ->pluck('qty_remaining')
        ->map(static fn (mixed $value): string => (string) $value)
        ->all();

    expect(array_slice($remaining, 0, 4))->toBe(['0.0000', '0.0000', '0.0000', '1.0000']);
});
```

- [ ] **Step 2: Run current stock tests**

Run:

```bash
rtk php artisan test --compact Modules/ERP/tests/Feature/StockMovementServiceTest.php --filter="required fifo layers"
```

Expected: PASS before implementation. This is a behavior guard for a sensitive optimization.

- [ ] **Step 3: Split FIFO availability from layer consumption**

In `consumeFifoLayersAndComputeUnitCost()`, compute availability with a SQL aggregate:

```php
$available = (string) StockCostLayer::query()
    ->where('company_id', $company_id)
    ->where('item_id', $item_id)
    ->where('warehouse_id', $warehouse_id)
    ->where('qty_remaining', '>', 0)
    ->sum('qty_remaining');
```

Then fetch locked layers with the existing query, but break immediately when `$remaining_to_take` reaches zero. Keep `lockForUpdate()` on the row query.

- [ ] **Step 4: Optimize FIFO display average with SQL aggregate**

In `syncFifoDisplayAverage()`, replace the full layer `get()` loop with:

```php
$aggregate = StockCostLayer::query()
    ->where('company_id', $stock_level->company_id)
    ->where('item_id', $stock_level->item_id)
    ->where('warehouse_id', $stock_level->warehouse_id)
    ->where('qty_remaining', '>', 0)
    ->selectRaw('SUM(qty_remaining) as qty_sum, SUM(qty_remaining * unit_cost) as value_sum')
    ->first();

$layer_qty_sum = (string) ($aggregate?->qty_sum ?? '0.0000');
$value = (string) ($aggregate?->value_sum ?? '0.0000');
```

Keep the existing zero-quantity branch and `divideDecimal()` assignment.

- [ ] **Step 5: Run stock and inventory accounting tests**

Run:

```bash
rtk php artisan test --compact Modules/ERP/tests/Feature/StockMovementServiceTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/InventoryAccountingGoldenMasterTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

Run:

```bash
git add Modules/ERP/app/Services/Inventory/StockMovementService.php Modules/ERP/tests/Feature/StockMovementServiceTest.php
git commit -m "perf(erp): aggregate fifo stock layer calculations"
```

---

### Task 9: ERP SalesPipelineService - Stream Opportunity Rows First

**Files:**
- Modify: `Modules/ERP/app/Services/Reporting/SalesPipelineService.php`
- Modify: `Modules/ERP/tests/Feature/OperationalReportingServicesTest.php`
- Modify: `Modules/ERP/tests/Feature/Services/SalesPipelineServiceTest.php`

- [ ] **Step 1: Add behavior guard for open pipeline with won-date filters**

Ensure `Modules/ERP/tests/Feature/OperationalReportingServicesTest.php` keeps this existing expectation:

```php
expect($result['by_status'][OpportunityStatus::Open->value]['count'])->toBe(1);
```

inside the won-date filter test. This prevents accidentally filtering the whole pipeline by won date.

- [ ] **Step 2: Add source guard against full opportunity loading**

Add this test to `Modules/ERP/tests/Feature/Services/SalesPipelineServiceTest.php`:

```php
it('streams opportunity rows for pipeline aggregation', function (): void {
    $source = file_get_contents(base_path('Modules/ERP/app/Services/Reporting/SalesPipelineService.php'));

    expect($source)->not->toContain('->get([')
        ->and($source)->toContain('->lazy(500)');
});
```

- [ ] **Step 3: Run test to verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/ERP/tests/Feature/Services/SalesPipelineServiceTest.php --filter="streams opportunity rows"
```

Expected: FAIL because `loadOpportunities()` currently uses `get([...])`.

- [ ] **Step 4: Change the loader to return an Enumerable stream**

In `Modules/ERP/app/Services/Reporting/SalesPipelineService.php`, add:

```php
use Illuminate\Support\Enumerable;
```

Then replace the `loadOpportunities()` return type and body with:

```php
/**
 * @return Enumerable<int, Opportunity>
 */
protected function loadOpportunities(int $company_id): Enumerable
{
    return Opportunity::query()
        ->where('company_id', $company_id)
        ->orderBy('id')
        ->lazy(500);
}
```

Keep the existing `generate()` aggregation loop unchanged. This first optimization removes full collection loading while preserving support for injected legacy rows in `SalesPipelineServiceStub`.

- [ ] **Step 5: Update SalesPipelineServiceStub signature**

In `Modules/ERP/tests/Stubs/SalesPipelineServiceStub.php`, keep the current injected collection and change the method return type to:

```php
protected function loadOpportunities(int $company_id): \Illuminate\Support\Enumerable
{
    return $this->opportunities;
}
```

- [ ] **Step 6: Run reporting tests**

Run:

```bash
rtk php artisan test --compact Modules/ERP/tests/Feature/OperationalReportingServicesTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/Services/SalesPipelineServiceTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

Run:

```bash
git add Modules/ERP/app/Services/Reporting/SalesPipelineService.php Modules/ERP/tests/Feature/OperationalReportingServicesTest.php Modules/ERP/tests/Feature/Services/SalesPipelineServiceTest.php
git commit -m "perf(erp): stream sales pipeline opportunity rows"
```

---

### Task 10: ERP StockValuationService - Database Ordering And Aggregate Totals

**Files:**
- Modify: `Modules/ERP/app/Services/Reporting/StockValuationService.php`
- Modify: `Modules/ERP/tests/Feature/OperationalReportingServicesTest.php`

- [ ] **Step 1: Add ordering and totals regression test**

Append to `Modules/ERP/tests/Feature/OperationalReportingServicesTest.php`:

```php
it('orders stock valuation by item sku and warehouse code while preserving totals', function (): void {
    $company = Company::query()->where('slug', 'default')->firstOrFail();
    $warehouse_b = Warehouse::query()->create(['company_id' => $company->id, 'name' => 'B Warehouse', 'code' => 'B']);
    $warehouse_a = Warehouse::query()->create(['company_id' => $company->id, 'name' => 'A Warehouse', 'code' => 'A']);
    $item_b = Item::query()->create(['company_id' => $company->id, 'name' => 'B Item', 'sku' => 'B-ITEM', 'uom' => 'pcs']);
    $item_a = Item::query()->create(['company_id' => $company->id, 'name' => 'A Item', 'sku' => 'A-ITEM', 'uom' => 'pcs']);

    StockLevel::query()->create(['company_id' => $company->id, 'item_id' => $item_b->id, 'warehouse_id' => $warehouse_b->id, 'quantity' => '2.0000', 'weighted_avg_cost' => '5.0000']);
    StockLevel::query()->create(['company_id' => $company->id, 'item_id' => $item_a->id, 'warehouse_id' => $warehouse_a->id, 'quantity' => '3.0000', 'weighted_avg_cost' => '7.0000']);

    $result = app(StockValuationService::class)->generate((int) $company->id);

    expect($result['rows'][0]['sku'])->toBe('A-ITEM')
        ->and($result['total_quantity'])->toBe('5.0000')
        ->and($result['total_value'])->toBe('31.0000');
});
```

- [ ] **Step 2: Run test before implementation**

Run:

```bash
rtk php artisan test --compact Modules/ERP/tests/Feature/OperationalReportingServicesTest.php --filter="orders stock valuation"
```

Expected: PASS. This guards behavior before moving ordering and totals into the query.

- [ ] **Step 3: Move ordering into the query**

In `StockValuationService::generate()`, add table-name variables from `ERPTables`:

```php
$stock_levels_table = ERPTables::StockLevels->value;
$items_table = ERPTables::Items->value;
$warehouses_table = ERPTables::Warehouses->value;
```

Then replace `get()->sortBy([...])->values()` with joins and `orderBy`:

```php
$stock_levels = StockLevel::query()
    ->with(['item', 'warehouse'])
    ->join($items_table, "{$items_table}.id", '=', "{$stock_levels_table}.item_id")
    ->join($warehouses_table, "{$warehouses_table}.id", '=', "{$stock_levels_table}.warehouse_id")
    ->where("{$stock_levels_table}.company_id", $company_id)
    ->when($warehouse_id !== null && $warehouse_id > 0, static fn ($query) => $query->where("{$stock_levels_table}.warehouse_id", $warehouse_id))
    ->orderBy("{$items_table}.sku")
    ->orderBy("{$warehouses_table}.code")
    ->select("{$stock_levels_table}.*")
    ->get();
```

- [ ] **Step 4: Compute totals with SQL aggregate**

Before loading rows, add:

```php
$totals = StockLevel::query()
    ->where('company_id', $company_id)
    ->when($warehouse_id !== null && $warehouse_id > 0, static fn ($query) => $query->where('warehouse_id', $warehouse_id))
    ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(quantity * weighted_avg_cost), 0) as total_value')
    ->first();
```

Use `$totals` for returned totals and keep per-row value formatting unchanged.

- [ ] **Step 5: Run reporting tests**

Run:

```bash
rtk php artisan test --compact Modules/ERP/tests/Feature/OperationalReportingServicesTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

Run:

```bash
git add Modules/ERP/app/Services/Reporting/StockValuationService.php Modules/ERP/tests/Feature/OperationalReportingServicesTest.php
git commit -m "perf(erp): order and total stock valuation in database"
```

---

### Task 11: AI TranslateContentCommand - Dispatch In Chunks

**Files:**
- Modify: `Modules/AI/app/Console/TranslateContentCommand.php`
- Modify: `Modules/AI/tests/Integration/TranslateContentCommandTest.php`

- [ ] **Step 1: Add source guard test**

Append this test to `Modules/AI/tests/Integration/TranslateContentCommandTest.php`:

```php
it('streams translate command models instead of loading them all', function (): void {
    $source = file_get_contents(base_path('Modules/AI/app/Console/TranslateContentCommand.php'));

    expect($source)->toContain('->lazyById(')
        ->and($source)->not->toContain('$models = $query->get();');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/AI/tests/Integration/TranslateContentCommandTest.php --filter="streams translate"
```

Expected: FAIL because the command currently calls `$query->get()`.

- [ ] **Step 3: Count first, then lazy iterate**

In `TranslateContentCommand::handle()`, replace:

```php
$models = $query->get();
$count = $models->count();
```

with:

```php
$count = (clone $query)->count();
```

Replace:

```php
foreach ($models as $model) {
```

with:

```php
foreach ($query->orderBy($model_class::query()->getModel()->getKeyName())->lazyById(100) as $model) {
```

Keep the sync and queued branches unchanged.

- [ ] **Step 4: Run AI command tests**

Run:

```bash
rtk php artisan test --compact Modules/AI/tests/Integration/TranslateContentCommandTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add Modules/AI/app/Console/TranslateContentCommand.php Modules/AI/tests/Integration/TranslateContentCommandTest.php
git commit -m "perf(ai): stream translate command models"
```

---

### Task 12: AI TranslateMissingCommand - Avoid Cross-Locale Full Collections

**Files:**
- Modify: `Modules/AI/app/Console/TranslateMissingCommand.php`
- Modify: `Modules/AI/tests/Integration/TranslateMissingCommandTest.php`

- [ ] **Step 1: Add source guard test**

Append this test to `Modules/AI/tests/Integration/TranslateMissingCommandTest.php`:

```php
<?php

declare(strict_types=1);

it('streams missing translation command models instead of loading each locale fully', function (): void {
    $source = file_get_contents(base_path('Modules/AI/app/Console/TranslateMissingCommand.php'));

    expect($source)->toContain('->lazyById(')
        ->and($source)->not->toContain('$missing = $query->get();');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/AI/tests/Integration/TranslateMissingCommandTest.php
```

Expected: FAIL because the command currently calls `$query->get()` per locale.

- [ ] **Step 3: Replace per-locale collection loading**

In `TranslateMissingCommand::handle()`, replace:

```php
$missing = $query->get();

foreach ($missing as $model) {
```

with:

```php
foreach ($query->orderBy($model_class::query()->getModel()->getKeyName())->lazyById(100) as $model) {
```

Keep `$models_to_translate[$model->id]` aggregation so one model still receives all missing locales in one job.

- [ ] **Step 4: Run focused test**

Run:

```bash
rtk php artisan test --compact Modules/AI/tests/Integration/TranslateMissingCommandTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add Modules/AI/app/Console/TranslateMissingCommand.php Modules/AI/tests/Integration/TranslateMissingCommandTest.php
git commit -m "perf(ai): stream missing translation discovery"
```

---

### Task 13: AI DocumentationService - Batch RAG Indexing

**Files:**
- Modify: `Modules/AI/app/Services/DocumentationService.php`
- Modify: `Modules/AI/tests/Integration/DocumentationServiceTest.php`

- [ ] **Step 1: Add source guard test**

Append this test to `Modules/AI/tests/Integration/DocumentationServiceTest.php`:

```php
it('indexes documentation chunks in bounded batches', function (): void {
    $source = file_get_contents(base_path('Modules/AI/app/Services/DocumentationService.php'));

    expect($source)->toContain('array_chunk($split_documents, 100)')
        ->and($source)->not->toContain('$agent->addDocuments($split_documents);');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
rtk php artisan test --compact Modules/AI/tests/Integration/DocumentationServiceTest.php --filter="bounded batches"
```

Expected: FAIL because `indexFromRoots()` sends the full split document array to the agent.

- [ ] **Step 3: Batch addDocuments and reindexBySource**

In `DocumentationService::indexFromRoots()`, replace:

```php
if ($use_incremental_reindex) {
    $agent->reindexBySource($split_documents);
} else {
    $agent->addDocuments($split_documents);
}
```

with:

```php
foreach (array_chunk($split_documents, 100) as $batch) {
    if ($use_incremental_reindex) {
        $agent->reindexBySource($batch);

        continue;
    }

    $agent->addDocuments($batch);
}
```

Keep the method return value as `count($split_documents)`.

- [ ] **Step 4: Run documentation tests**

Run:

```bash
rtk php artisan test --compact Modules/AI/tests/Integration/DocumentationServiceTest.php
rtk php artisan test --compact Modules/AI/tests/Integration/IndexDocumentationCommandTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add Modules/AI/app/Services/DocumentationService.php Modules/AI/tests/Integration/DocumentationServiceTest.php
git commit -m "perf(ai): batch documentation rag indexing"
```

---

### Task 14: Final Cross-Module Verification

**Files:**
- No code edits.
- Verify changed modules only.

- [ ] **Step 1: Run dirty-file formatter**

Run:

```bash
rtk vendor/bin/pint --dirty
```

Expected: PASS and only touched files are formatted.

- [ ] **Step 2: Run focused regression suites**

Run:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Search/DatabaseEngineSQLiteVectorSearchTest.php
rtk php artisan test --compact Modules/Core/tests/Integration/Helpers/HasClosureTableTest.php
rtk php artisan test --compact Modules/Core/tests/Feature/Console/HandleLicensesCommandTest.php
rtk php artisan test --compact Modules/Core/tests/Feature/Filament/WidgetsTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/InvoicePostingServiceTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/StockMovementServiceTest.php
rtk php artisan test --compact Modules/ERP/tests/Feature/OperationalReportingServicesTest.php
rtk php artisan test --compact Modules/AI/tests/Integration/TranslateContentCommandTest.php
rtk php artisan test --compact Modules/AI/tests/Integration/TranslateMissingCommandTest.php
rtk php artisan test --compact Modules/AI/tests/Integration/DocumentationServiceTest.php
```

Expected: PASS.

- [ ] **Step 3: Inspect final diff**

Run:

```bash
rtk git diff --stat
rtk git diff -- Modules/Core Modules/ERP Modules/CMS Modules/AI
```

Expected: only planned files changed. No dependency files changed.

- [ ] **Step 4: Commit final formatting-only changes if Pint changed files after previous commits**

Run:

```bash
git add Modules/Core Modules/ERP Modules/CMS Modules/AI
git commit -m "style: format performance optimization changes"
```

Expected: commit only if `rtk vendor/bin/pint --dirty` changed files after the task commits.

---

## Post-Implementation Manual Checks

Run these on a local or staging environment with realistic data after all automated tests pass:

```bash
rtk php artisan config:cache
rtk php artisan route:cache
rtk php artisan view:cache
```

Then manually inspect:

- Admin dashboard TTFB before and after lazy widget loading.
- Filament list pages for Users, Contents, Categories, Modifications, Invoices, Stock Levels, Opportunities.
- ERP invoice posting with repeated tax codes.
- ERP FIFO outbound movement on an item with many cost layers.
- AI translation commands on a model class with more than 1,000 rows.
- RAG indexing with a large docs corpus.

Do not enable Octane as part of this plan. Octane requires a separate statefulness audit of singleton services and static caches.
