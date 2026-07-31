# Seeder Orchestration and Reconciliation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make production seeding safe and fast to run on every release — correct dependency order, one reconciliation primitive with field-level drift semantics, no duplicated reflection, per-node atomicity with resume, and cleanup that cannot destroy operator data.

**Architecture:** A new `Modules\Core\Seeding` namespace owns the mechanism. `SeedGraph` topologically orders seeder nodes from declared dependencies (replacing `module.json` priority). `SeedReconciler` replaces three ad-hoc idempotency patterns with a single O(1)-query engine driven by `SeedDefinition` value objects that declare which fields are structural (code-owned) and which are initial (operator-owned). A `core_seed_runs` ledger records per-node outcomes for resume. `core_settings` gains `module` and `seeded_value` columns so drift is computed rather than asserted.

**Tech Stack:** PHP 8.5, Laravel 12, `nwidart/laravel-modules`, Pest 3, Eloquent `upsert()`.

**Spec:** [`docs/superpowers/specs/2026-07-31-seeder-orchestration-design.md`](../specs/2026-07-31-seeder-orchestration-design.md)

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`
- Classes are `final` unless designed for extension; explicit param and return types everywhere
- Use `#[Override]` when overriding
- Prefer PHPDoc array shapes over inline comments; comments in English
- Never declare classes/traits/enums inline in test files — they go in `Modules/Core/tests/Stubs/` with PSR-4 registered in `Modules/Core/composer.json` `autoload-dev`
- Core tests under `tests/Unit` bind `Modules\Core\Tests\TestCase`; under `tests/Integration` and `tests/Feature` they bind `Modules\Core\Tests\LaravelTestCase` (full app + `RefreshDatabase`). Binding is by directory in `Modules/Core/tests/Pest.php` — do not add `uses()` calls that duplicate it.
- Run tests with `php artisan test --compact <path>`
- Run `vendor/bin/pint --dirty` before every commit
- Table names are prefixed by lowercase module name; register new tables as cases in `Modules\Core\Enums\CoreTables`
- Migrations use `Modules\Core\Helpers\MigrateUtils::timestamps()` for timestamp/soft-delete columns
- Avoid `DB::`; prefer `Model::query()`. Derive queries from the model's own connection.
- **This plan does not touch `Dev*` seeders or `BatchSeeder`.**

## Commit protocol — `Modules/*` are git submodules

`Modules/Core` is a **separate git repository**. A commit touching it is two commits: one inside
the submodule, one in `laraplate` bumping the recorded pointer. Every command below runs from the
`laraplate` root:

```bash
vendor/bin/pint --dirty
git -C Modules/Core add <paths relative to Modules/Core>
git -C Modules/Core commit -m "<type>(core): <subject>"
git add Modules/Core
git commit -m "chore: bump Core for <subject>"
```

Paths passed to `git -C Modules/Core add` are relative to the **submodule root** —
`app/Seeding`, not `Modules/Core/app/Seeding`.

**Other sessions may be committing to these repositories concurrently.** Never run `git add -A`,
`git add .`, or `git commit -a`. Stage only the exact paths this task creates or modifies. At the
time of writing, `Modules/Core` carries unrelated uncommitted work in
`app/Providers/RouteServiceProvider.php` plus two untracked files under `app/Console/` and
`tests/Feature/Console/` — leave them alone.

Task 9 also modifies `database/seeders/DatabaseSeeder.php`, which lives in the `laraplate`
repository itself, not in a submodule. Its commit block reflects that.

## File Structure

**Created:**

| File | Responsibility |
|---|---|
| `Modules/Core/app/Seeding/Contracts/DeclaresSeedDependencies.php` | Interface for seeders declaring `dependsOn()` |
| `Modules/Core/app/Seeding/SeedNode.php` | Value object: seeder class + owning module + dependencies |
| `Modules/Core/app/Seeding/SeedGraph.php` | Deterministic topological sort, cycle and missing-dependency detection |
| `Modules/Core/app/Seeding/SeedGraphBuilder.php` | Discovers seeders, merges `module.json` requires into implicit edges |
| `Modules/Core/app/Seeding/Exceptions/SeedGraphCycleException.php` | Cycle, carrying the path |
| `Modules/Core/app/Seeding/Exceptions/MissingSeedDependencyException.php` | Declared dependency absent from the graph |
| `Modules/Core/app/Seeding/SeedDefinition.php` | Declares model, identity, structural/initial fields, owner module, rows |
| `Modules/Core/app/Seeding/ReconciliationOutcome.php` | Per-set result counts and names |
| `Modules/Core/app/Seeding/ValueComparator.php` | Decoded-value equality, shared by reconciler and cleaner |
| `Modules/Core/app/Seeding/SeedReconciler.php` | The read/diff/upsert/restore engine |
| `Modules/Core/app/Seeding/ModelCapabilities.php` | Readonly per-model capability flags |
| `Modules/Core/app/Seeding/ModelCapabilityScanner.php` | Single-pass trait scan over `models()` |
| `Modules/Core/app/Seeding/ModuleState.php` | Enum: `Enabled`, `Disabled`, `Absent` |
| `Modules/Core/app/Seeding/ModuleStateResolver.php` | Maps an owner module name to a `ModuleState` |
| `Modules/Core/app/Seeding/CleanupReport.php` | Names affected by a cleanup pass |
| `Modules/Core/app/Seeding/SettingsCleaner.php` | Cleanup keyed on module state and drift |
| `Modules/Core/app/Seeding/SeedLedger.php` | Records run/node outcomes, answers resume queries |
| `Modules/Core/app/Seeding/SeedOrchestrator.php` | Runs the sorted graph, per-node transaction, stop-on-failure |
| `Modules/Core/app/Models/SeedRun.php` | Eloquent model for `core_seed_runs` |
| `Modules/Core/database/migrations/2026_07_31_000001_create_seed_runs_table.php` | Ledger table |
| `Modules/Core/database/migrations/2026_07_31_000002_add_seeding_columns_to_settings_table.php` | `module` + `seeded_value` |

**Modified:**

| File | Change |
|---|---|
| `Modules/Core/app/Enums/CoreTables.php` | Add `SeedRuns = 'core_seed_runs'` |
| `database/seeders/DatabaseSeeder.php` | Delegate to `SeedOrchestrator` |
| `Modules/Core/app/Console/SeedCommand.php` | Add `--resume` |
| `Modules/Core/database/seeders/CoreDatabaseSeeder.php` | Use reconciler + scanner; drop `getModelsWithApprovals()`, `deleteRefuses()`, `cache:clear` |
| `Modules/{AI,CMS,ERP,MES}/database/seeders/*DatabaseSeeder.php` | Emit `SeedDefinition`; declare `dependsOn()` |

---

### Task 1: SeedGraph — deterministic topological ordering

Pure algorithm, no database. This is the task that fixes the originating defect.

**Files:**
- Create: `Modules/Core/app/Seeding/SeedNode.php`
- Create: `Modules/Core/app/Seeding/SeedGraph.php`
- Create: `Modules/Core/app/Seeding/Exceptions/SeedGraphCycleException.php`
- Create: `Modules/Core/app/Seeding/Exceptions/MissingSeedDependencyException.php`
- Test: `Modules/Core/tests/Unit/Seeding/SeedGraphTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: `SeedNode` (readonly: `string $seederClass`, `string $module`, `list<class-string> $dependsOn`); `SeedGraph::sort(list<SeedNode>): list<SeedNode>`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Seeding\Exceptions\MissingSeedDependencyException;
use Modules\Core\Seeding\Exceptions\SeedGraphCycleException;
use Modules\Core\Seeding\SeedGraph;
use Modules\Core\Seeding\SeedNode;

/**
 * @param  list<class-string>  $dependsOn
 */
function node(string $class, string $module, array $dependsOn = []): SeedNode
{
    return new SeedNode($class, $module, $dependsOn);
}

it('orders a dependency before its dependent', function (): void {
    $sorted = SeedGraph::sort([
        node('MesSeeder', 'MES', ['ErpSeeder']),
        node('ErpSeeder', 'ERP'),
    ]);

    expect(array_map(fn (SeedNode $n): string => $n->seederClass, $sorted))
        ->toBe(['ErpSeeder', 'MesSeeder']);
});

it('breaks ties deterministically by module then class', function (): void {
    $sorted = SeedGraph::sort([
        node('ZebraSeeder', 'CMS'),
        node('AlphaSeeder', 'CMS'),
        node('OtherSeeder', 'AI'),
    ]);

    expect(array_map(fn (SeedNode $n): string => $n->seederClass, $sorted))
        ->toBe(['OtherSeeder', 'AlphaSeeder', 'ZebraSeeder']);
});

it('produces the same order regardless of input order', function (): void {
    $nodes = [
        node('C', 'Core', ['A']),
        node('A', 'Core'),
        node('B', 'Core', ['A']),
    ];

    $first = SeedGraph::sort($nodes);
    $second = SeedGraph::sort(array_reverse($nodes));

    expect(array_map(fn (SeedNode $n): string => $n->seederClass, $first))
        ->toBe(array_map(fn (SeedNode $n): string => $n->seederClass, $second));
});

it('throws on a cycle and names the involved nodes', function (): void {
    SeedGraph::sort([
        node('A', 'Core', ['B']),
        node('B', 'Core', ['A']),
    ]);
})->throws(SeedGraphCycleException::class, 'A');

it('throws when a declared dependency is not in the graph', function (): void {
    SeedGraph::sort([
        node('MesSeeder', 'MES', ['ErpSeeder']),
    ]);
})->throws(MissingSeedDependencyException::class, 'ErpSeeder');
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Unit/Seeding/SeedGraphTest.php`
Expected: FAIL — `Class "Modules\Core\Seeding\SeedGraph" not found`

- [ ] **Step 3: Write the implementation**

`Modules/Core/app/Seeding/SeedNode.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

final readonly class SeedNode
{
    /**
     * @param  class-string  $seederClass
     * @param  list<class-string>  $dependsOn
     */
    public function __construct(
        public string $seederClass,
        public string $module,
        public array $dependsOn = [],
    ) {}
}
```

`Modules/Core/app/Seeding/Exceptions/SeedGraphCycleException.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding\Exceptions;

use RuntimeException;

final class SeedGraphCycleException extends RuntimeException
{
    /**
     * @param  list<class-string>  $involved
     */
    public static function for(array $involved): self
    {
        return new self(
            'Seeder dependency cycle detected between: ' . implode(', ', $involved),
        );
    }
}
```

`Modules/Core/app/Seeding/Exceptions/MissingSeedDependencyException.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding\Exceptions;

use RuntimeException;

final class MissingSeedDependencyException extends RuntimeException
{
    public static function for(string $seederClass, string $dependency): self
    {
        return new self(
            "Seeder {$seederClass} depends on {$dependency}, which is not present in the graph. "
            . 'Its owning module is disabled or absent.',
        );
    }
}
```

`Modules/Core/app/Seeding/SeedGraph.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Modules\Core\Seeding\Exceptions\MissingSeedDependencyException;
use Modules\Core\Seeding\Exceptions\SeedGraphCycleException;

final class SeedGraph
{
    /**
     * Order nodes so every dependency precedes its dependents.
     *
     * Ties are broken by module name then class name, so the same set of nodes
     * always produces the same order regardless of discovery order.
     *
     * @param  list<SeedNode>  $nodes
     * @return list<SeedNode>
     */
    public static function sort(array $nodes): array
    {
        $remaining = [];

        foreach ($nodes as $node) {
            $remaining[$node->seederClass] = $node;
        }

        foreach ($remaining as $node) {
            foreach ($node->dependsOn as $dependency) {
                if (! isset($remaining[$dependency])) {
                    throw MissingSeedDependencyException::for($node->seederClass, $dependency);
                }
            }
        }

        $sorted = [];
        $resolved = [];

        while ($remaining !== []) {
            $ready = array_filter(
                $remaining,
                static fn (SeedNode $node): bool => array_all(
                    $node->dependsOn,
                    static fn (string $dependency): bool => isset($resolved[$dependency]),
                ),
            );

            if ($ready === []) {
                throw SeedGraphCycleException::for(array_keys($remaining));
            }

            uasort(
                $ready,
                static fn (SeedNode $a, SeedNode $b): int => [$a->module, $a->seederClass]
                    <=> [$b->module, $b->seederClass],
            );

            $next = (string) array_key_first($ready);
            $sorted[] = $remaining[$next];
            $resolved[$next] = true;
            unset($remaining[$next]);
        }

        return $sorted;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Unit/Seeding/SeedGraphTest.php`
Expected: PASS — 5 tests

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Seeding tests/Unit/Seeding
git -C Modules/Core commit -m "feat(core): deterministic seeder dependency graph"
git add Modules/Core
git commit -m "chore: bump Core for the seeder dependency graph"
```

---

### Task 2: SeedGraphBuilder — discovery and implicit module edges

**Files:**
- Create: `Modules/Core/app/Seeding/Contracts/DeclaresSeedDependencies.php`
- Create: `Modules/Core/app/Seeding/SeedGraphBuilder.php`
- Test: `Modules/Core/tests/Feature/Seeding/SeedGraphBuilderTest.php`

**Interfaces:**
- Consumes: `SeedNode`, `SeedGraph::sort()` from Task 1
- Produces: `SeedGraphBuilder::build(): list<SeedNode>` (already sorted)

- [ ] **Step 1: Write the failing test**

This asserts against the real module layout, so it is the regression test for the originating defect.

```php
<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Seeding\SeedGraphBuilder;
use Modules\Core\Seeding\SeedNode;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\MES\Database\Seeders\MESDatabaseSeeder;

/**
 * @return array<class-string, int>
 */
function seederPositions(): array
{
    $sorted = app(SeedGraphBuilder::class)->build();

    return array_flip(array_map(
        static fn (SeedNode $node): string => $node->seederClass,
        $sorted,
    ));
}

it('orders MES after ERP, which module priority alone got wrong', function (): void {
    $positions = seederPositions();

    expect($positions[MESDatabaseSeeder::class])
        ->toBeGreaterThan($positions[ERPDatabaseSeeder::class]);
});

it('orders Core before every other module seeder', function (): void {
    $positions = seederPositions();
    $core = $positions[CoreDatabaseSeeder::class];

    expect($positions[ERPDatabaseSeeder::class])->toBeGreaterThan($core)
        ->and($positions[MESDatabaseSeeder::class])->toBeGreaterThan($core);
});

it('excludes Dev seeders from the production graph', function (): void {
    $classes = array_keys(seederPositions());

    foreach ($classes as $class) {
        expect(class_basename($class))->not->toStartWith('Dev');
    }
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Feature/Seeding/SeedGraphBuilderTest.php`
Expected: FAIL — `Class "Modules\Core\Seeding\SeedGraphBuilder" not found`

- [ ] **Step 3: Write the implementation**

`Modules/Core/app/Seeding/Contracts/DeclaresSeedDependencies.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding\Contracts;

interface DeclaresSeedDependencies
{
    /**
     * Seeder classes that must complete before this one.
     *
     * Cross-module edges implied by module.json "requires" are added
     * automatically and need not be repeated here.
     *
     * @return list<class-string>
     */
    public static function dependsOn(): array;
}
```

`Modules/Core/app/Seeding/SeedGraphBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Illuminate\Support\Str;
use Modules\Core\Seeding\Contracts\DeclaresSeedDependencies;
use Nwidart\Modules\Facades\Module;

use function modules;
use function module_path;

final class SeedGraphBuilder
{
    /**
     * Discover production seeders of enabled modules and return them in execution order.
     *
     * @return list<SeedNode>
     */
    public function build(): array
    {
        $by_module = $this->discover();
        $nodes = [];

        foreach ($by_module as $module => $classes) {
            $inherited = $this->inheritedDependencies($module, $by_module);

            foreach ($classes as $class) {
                $declared = is_subclass_of($class, DeclaresSeedDependencies::class)
                    ? $class::dependsOn()
                    : [];

                $nodes[] = new SeedNode(
                    seederClass: $class,
                    module: $module,
                    dependsOn: array_values(array_unique([...$declared, ...$inherited])),
                );
            }
        }

        return SeedGraph::sort($nodes);
    }

    /**
     * Production seeder classes per enabled module, Dev* excluded.
     *
     * @return array<string, list<class-string>>
     */
    private function discover(): array
    {
        $relative_path = config('modules.paths.generator.seeder.path');
        $discovered = [];

        foreach (modules(prioritySort: false) as $module) {
            $path = module_path(
                $module,
                is_string($relative_path) ? $relative_path : 'database/seeders',
            );

            $classes = [];

            foreach (glob("{$path}/*.php") ?: [] as $file) {
                $basename = basename($file, '.php');

                if (Str::startsWith($basename, 'Dev')) {
                    continue;
                }

                $class = "Modules\\{$module}\\Database\\Seeders\\{$basename}";

                if (class_exists($class)) {
                    $classes[] = $class;
                }
            }

            if ($classes !== []) {
                $discovered[$module] = $classes;
            }
        }

        return $discovered;
    }

    /**
     * Every seeder of every module this module requires, transitively resolved by the graph.
     *
     * @param  array<string, list<class-string>>  $byModule
     * @return list<class-string>
     */
    private function inheritedDependencies(string $module, array $byModule): array
    {
        $required = Module::find($module)?->get('requires') ?? [];
        $inherited = [];

        foreach ($required as $requirement) {
            foreach ($byModule[$requirement] ?? [] as $class) {
                $inherited[] = $class;
            }
        }

        return $inherited;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Feature/Seeding/SeedGraphBuilderTest.php`
Expected: PASS — 3 tests

Note: `Core` is required implicitly by AI, CMS and ERP via their `module.json` `requires`, so the second test passes without special-casing.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Seeding tests/Feature/Seeding
git -C Modules/Core commit -m "feat(core): seeder discovery with implicit module.json requires edges"
git add Modules/Core
git commit -m "chore: bump Core for seeder discovery"
```

---

### Task 3: Settings columns — `module` and `seeded_value`

**Files:**
- Create: `Modules/Core/database/migrations/2026_07_31_000002_add_seeding_columns_to_settings_table.php`
- Modify: `Modules/Core/app/Models/Setting.php` (fillable + casts)
- Test: `Modules/Core/tests/Integration/Seeding/SettingSeedingColumnsTest.php`

**Interfaces:**
- Produces: `core_settings.module` (nullable string), `core_settings.seeded_value` (nullable json); `Setting::$module`, `Setting::$seeded_value`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;

it('persists the owning module and the seeded baseline', function (): void {
    $setting = Setting::query()->forceCreate([
        'name' => 'seeding_columns_probe',
        'value' => 20,
        'encrypted' => false,
        'type' => SettingTypeEnum::Integer,
        'group_name' => 'base',
        'description' => 'Probe',
        'module' => 'CMS',
        'seeded_value' => 20,
    ]);

    $fresh = Setting::query()->withoutGlobalScopes()->find($setting->getKey());

    expect($fresh->module)->toBe('CMS')
        ->and($fresh->seeded_value)->toBe(20);
});

it('leaves both columns null for hand-created settings', function (): void {
    $setting = Setting::query()->forceCreate([
        'name' => 'hand_created_probe',
        'value' => 'x',
        'encrypted' => false,
        'type' => SettingTypeEnum::String,
        'group_name' => 'base',
        'description' => 'Probe',
    ]);

    $fresh = Setting::query()->withoutGlobalScopes()->find($setting->getKey());

    expect($fresh->module)->toBeNull()
        ->and($fresh->seeded_value)->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Seeding/SettingSeedingColumnsTest.php`
Expected: FAIL — unknown column `module`

- [ ] **Step 3: Write the migration and model changes**

`Modules/Core/database/migrations/2026_07_31_000002_add_seeding_columns_to_settings_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;

return new class() extends Migration
{
    public function up(): void
    {
        $settings_table = CoreTables::Settings->value;

        Schema::table($settings_table, function (Blueprint $table) use ($settings_table): void {
            $table->string('module')
                ->nullable(true)
                ->index("{$settings_table}_module_IDX")
                ->comment('Owning module, null when the setting was not written by a seeder');

            $table->json('seeded_value')
                ->nullable(true)
                ->comment('Last value written by the seeder; drift is value !== seeded_value');
        });
    }

    public function down(): void
    {
        $settings_table = CoreTables::Settings->value;

        Schema::table($settings_table, function (Blueprint $table) use ($settings_table): void {
            $table->dropIndex("{$settings_table}_module_IDX");
            $table->dropColumn(['module', 'seeded_value']);
        });
    }
};
```

In `Modules/Core/app/Models/Setting.php`, add `'module'` and `'seeded_value'` to `$fillable`, and add to the `casts()` method:

```php
'seeded_value' => 'json',
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Integration/Seeding/SettingSeedingColumnsTest.php`
Expected: PASS — 2 tests

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add database/migrations app/Models/Setting.php tests/Integration/Seeding
git -C Modules/Core commit -m "feat(core): settings module ownership and seeded baseline columns"
git add Modules/Core
git commit -m "chore: bump Core for settings seeding columns"
```

---

### Task 4: SeedDefinition value object

**Files:**
- Create: `Modules/Core/app/Seeding/SeedDefinition.php`
- Test: `Modules/Core/tests/Unit/Seeding/SeedDefinitionTest.php`

**Interfaces:**
- Produces: `SeedDefinition::for(class-string<Model>)`, fluent `identity(list<string>)`, `structural(list<string>)`, `initial(list<string>)`, `ownedBy(string)`, `rows(list<array<string,mixed>>)`; readable properties `$modelClass`, `$identity`, `$structural`, `$initial`, `$module`, `$rows`; `identityColumn(): string`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Models\Setting;
use Modules\Core\Seeding\SeedDefinition;

it('builds a definition fluently', function (): void {
    $definition = SeedDefinition::for(Setting::class)
        ->identity(['name'])
        ->structural(['type', 'group_name'])
        ->initial(['value'])
        ->ownedBy('CMS')
        ->rows([['name' => 'a', 'type' => 'string', 'group_name' => 'base', 'value' => 1]]);

    expect($definition->modelClass)->toBe(Setting::class)
        ->and($definition->identityColumn())->toBe('name')
        ->and($definition->structural)->toBe(['type', 'group_name'])
        ->and($definition->initial)->toBe(['value'])
        ->and($definition->module)->toBe('CMS')
        ->and($definition->rows)->toHaveCount(1);
});

it('rejects composite identities, which the reconciler does not support', function (): void {
    SeedDefinition::for(Setting::class)
        ->identity(['name', 'group_name'])
        ->identityColumn();
})->throws(LogicException::class, 'single-column identity');

it('rejects a row missing an identity value', function (): void {
    SeedDefinition::for(Setting::class)
        ->identity(['name'])
        ->rows([['type' => 'string']]);
})->throws(InvalidArgumentException::class, 'name');

it('normalizes empty strings to null, replacing the SettingObserver saving hook', function (): void {
    $definition = SeedDefinition::for(Setting::class)
        ->identity(['name'])
        ->initial(['value'])
        ->rows([['name' => 'empty_probe', 'value' => '']]);

    expect($definition->rows[0]['value'])->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Unit/Seeding/SeedDefinitionTest.php`
Expected: FAIL — `Class "Modules\Core\Seeding\SeedDefinition" not found`

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * Declares how a set of rows is reconciled.
 *
 * Structural fields follow the code and are realigned on every release.
 * Initial fields are written at creation and never touched again.
 */
final class SeedDefinition
{
    /** @var list<string> */
    public array $identity = [];

    /** @var list<string> */
    public array $structural = [];

    /** @var list<string> */
    public array $initial = [];

    public ?string $module = null;

    /** @var list<array<string,mixed>> */
    public array $rows = [];

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function __construct(public string $modelClass) {}

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function for(string $modelClass): self
    {
        return new self($modelClass);
    }

    /**
     * @param  list<string>  $columns
     */
    public function identity(array $columns): self
    {
        $this->identity = $columns;

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function structural(array $columns): self
    {
        $this->structural = $columns;

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function initial(array $columns): self
    {
        $this->initial = $columns;

        return $this;
    }

    public function ownedBy(string $module): self
    {
        $this->module = $module;

        return $this;
    }

    /**
     * Empty strings become null here rather than in a saving hook: bulk upserts
     * do not fire Eloquent events, and this is a rule about the data anyway.
     *
     * @param  list<array<string,mixed>>  $rows
     */
    public function rows(array $rows): self
    {
        $column = $this->identityColumn();
        $normalized = [];

        foreach ($rows as $index => $row) {
            if (! array_key_exists($column, $row)) {
                throw new InvalidArgumentException(
                    "Row {$index} is missing the identity column '{$column}'.",
                );
            }

            $normalized[] = array_map(
                static fn (mixed $value): mixed => $value === '' ? null : $value,
                $row,
            );
        }

        $this->rows = $normalized;

        return $this;
    }

    /**
     * Composite identities would require one OR clause per row, defeating the
     * fixed query count the reconciler exists to provide.
     */
    public function identityColumn(): string
    {
        if (count($this->identity) !== 1) {
            throw new LogicException(
                'SeedReconciler supports a single-column identity only; got '
                . count($this->identity) . ' columns.',
            );
        }

        return $this->identity[0];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Unit/Seeding/SeedDefinitionTest.php`
Expected: PASS — 3 tests

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Seeding/SeedDefinition.php tests/Unit/Seeding/SeedDefinitionTest.php
git -C Modules/Core commit -m "feat(core): SeedDefinition declaring structural vs initial fields"
git add Modules/Core
git commit -m "chore: bump Core for SeedDefinition"
```

---

### Task 5: SeedReconciler — the read/diff/upsert/restore engine

The heart of the plan. One read, one upsert (which both inserts new rows and realigns structural columns of existing ones), one restore, plus one backfill write while baselines are still missing.

**Files:**
- Create: `Modules/Core/app/Seeding/ReconciliationOutcome.php`
- Create: `Modules/Core/app/Seeding/SeedReconciler.php`
- Test: `Modules/Core/tests/Integration/Seeding/SeedReconcilerTest.php`

**Interfaces:**
- Consumes: `SeedDefinition` from Task 4; `core_settings.module` / `seeded_value` from Task 3
- Produces: `SeedReconciler::reconcile(SeedDefinition): ReconciliationOutcome`; `ReconciliationOutcome` readonly with `list<string> $created`, `$realigned`, `$restored`, `int $unchanged`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Seeding\SeedDefinition;
use Modules\Core\Seeding\SeedReconciler;

/**
 * @param  list<array<string,mixed>>  $rows
 */
function settingsDefinition(array $rows): SeedDefinition
{
    return SeedDefinition::for(Setting::class)
        ->identity(['name'])
        ->structural(['type', 'group_name', 'description'])
        ->initial(['value'])
        ->ownedBy('Core')
        ->rows($rows);
}

/**
 * @return array<string,mixed>
 */
function settingRow(string $name, mixed $value, string $description = 'Original'): array
{
    return [
        'name' => $name,
        'value' => $value,
        'encrypted' => false,
        'type' => SettingTypeEnum::Integer->value,
        'group_name' => 'base',
        'description' => $description,
    ];
}

it('creates missing rows with module and baseline populated', function (): void {
    $outcome = app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_create', 10)]),
    );

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'recon_create')->sole();

    expect($outcome->created)->toBe(['recon_create'])
        ->and($setting->module)->toBe('Core')
        ->and($setting->seeded_value)->toBe(10)
        ->and($setting->value)->toBe(10);
});

it('realigns structural fields but never the operator value', function (): void {
    app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_drift', 10)]),
    );

    Setting::query()->withoutGlobalScopes()
        ->where('name', 'recon_drift')
        ->update(['value' => json_encode(99)]);

    $outcome = app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_drift', 10, 'Updated description')]),
    );

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'recon_drift')->sole();

    expect($outcome->realigned)->toBe(['recon_drift'])
        ->and($setting->description)->toBe('Updated description')
        ->and($setting->value)->toBe(99)
        ->and($setting->seeded_value)->toBe(10);
});

it('reports rows as unchanged when nothing structural differs', function (): void {
    $definition = fn (): SeedDefinition => settingsDefinition([settingRow('recon_stable', 5)]);

    app(SeedReconciler::class)->reconcile($definition());
    $outcome = app(SeedReconciler::class)->reconcile($definition());

    expect($outcome->unchanged)->toBe(1)
        ->and($outcome->created)->toBe([])
        ->and($outcome->realigned)->toBe([]);
});

it('restores a soft-deleted row instead of inserting a duplicate', function (): void {
    app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_restore', 7)]),
    );

    Setting::query()->withoutGlobalScopes()->where('name', 'recon_restore')->delete();

    $outcome = app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_restore', 7)]),
    );

    $count = Setting::query()->withoutGlobalScopes()->withTrashed()
        ->where('name', 'recon_restore')->count();

    expect($outcome->restored)->toBe(['recon_restore'])
        ->and($count)->toBe(1)
        ->and(Setting::query()->withoutGlobalScopes()->where('name', 'recon_restore')->exists())
        ->toBeTrue();
});

it('backfills the baseline for rows created before the reconciler existed', function (): void {
    Setting::query()->forceCreate(settingRow('recon_legacy', 3) + ['value' => 3]);

    app(SeedReconciler::class)->reconcile(
        settingsDefinition([settingRow('recon_legacy', 3)]),
    );

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'recon_legacy')->sole();

    expect($setting->seeded_value)->toBe(3)
        ->and($setting->module)->toBe('Core');
});

it('issues a fixed number of queries regardless of row count', function (): void {
    $small = settingsDefinition([settingRow('q_a', 1)]);
    $large = settingsDefinition(array_map(
        static fn (int $i): array => settingRow("q_bulk_{$i}", $i),
        range(1, 50),
    ));

    DB::enableQueryLog();
    app(SeedReconciler::class)->reconcile($small);
    $small_count = count(DB::getQueryLog());

    DB::flushQueryLog();
    app(SeedReconciler::class)->reconcile($large);
    $large_count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($large_count)->toBe($small_count);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Seeding/SeedReconcilerTest.php`
Expected: FAIL — `Class "Modules\Core\Seeding\SeedReconciler" not found`

- [ ] **Step 3: Write the implementation**

`Modules/Core/app/Seeding/ReconciliationOutcome.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

final readonly class ReconciliationOutcome
{
    /**
     * @param  list<string>  $created
     * @param  list<string>  $realigned
     * @param  list<string>  $restored
     */
    public function __construct(
        public array $created = [],
        public array $realigned = [],
        public array $restored = [],
        public int $unchanged = 0,
    ) {}

    public function touchedAnything(): bool
    {
        return $this->created !== [] || $this->realigned !== [] || $this->restored !== [];
    }
}
```

`Modules/Core/app/Seeding/SeedReconciler.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Illuminate\Database\Eloquent\Model;

final class SeedReconciler
{
    /**
     * Align persisted rows with their declared definition.
     *
     * Query budget is fixed: one read, one upsert, one restore, one baseline
     * backfill. Each write runs only when its set is non-empty.
     */
    public function reconcile(SeedDefinition $definition): ReconciliationOutcome
    {
        $column = $definition->identityColumn();
        $model_class = $definition->modelClass;

        /** @var Model $model */
        $model = new $model_class();

        $existing = $model_class::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->whereIn($column, array_column($definition->rows, $column))
            ->get()
            ->keyBy($column);

        $upsert_payload = [];
        $created = [];
        $realigned = [];
        $restored = [];
        $needs_baseline = [];
        $unchanged = 0;

        foreach ($definition->rows as $row) {
            $key = (string) $row[$column];
            $current = $existing->get($key);

            if ($current === null) {
                $created[] = $key;
                $upsert_payload[] = $this->fullPayload($definition, $row);

                continue;
            }

            if ($current->trashed()) {
                $restored[] = $key;
            }

            if ($current->getAttribute('seeded_value') === null) {
                $needs_baseline[] = $this->baselinePayload($definition, $row);
            }

            if ($this->structuralDiffers($definition, $current, $row)) {
                $realigned[] = $key;
                $upsert_payload[] = $this->fullPayload($definition, $row);

                continue;
            }

            $unchanged++;
        }

        $connection = $model->getConnection();

        $connection->transaction(function () use (
            $model_class,
            $definition,
            $column,
            $upsert_payload,
            $restored,
            $needs_baseline,
        ): void {
            if ($upsert_payload !== []) {
                // Structural columns only: value, seeded_value and module are
                // written on insert and must never be moved by a realignment.
                $model_class::query()->upsert($upsert_payload, [$column], $definition->structural);
            }

            if ($restored !== []) {
                $model_class::query()
                    ->withoutGlobalScopes()
                    ->withTrashed()
                    ->whereIn($column, $restored)
                    ->restore();
            }

            if ($needs_baseline !== []) {
                $model_class::query()
                    ->upsert($needs_baseline, [$column], ['seeded_value', 'module']);
            }
        });

        return new ReconciliationOutcome($created, $realigned, $restored, $unchanged);
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function fullPayload(SeedDefinition $definition, array $row): array
    {
        $payload = $row;
        $payload['module'] = $definition->module;

        foreach ($definition->initial as $field) {
            if (array_key_exists($field, $row)) {
                $payload['seeded_value'] = $this->encode($row[$field]);
            }
        }

        foreach ($definition->initial as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->encode($payload[$field]);
            }
        }

        return $payload;
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function baselinePayload(SeedDefinition $definition, array $row): array
    {
        $column = $definition->identityColumn();
        $payload = [
            $column => $row[$column],
            'module' => $definition->module,
        ];

        foreach ($definition->initial as $field) {
            if (array_key_exists($field, $row)) {
                $payload['seeded_value'] = $this->encode($row[$field]);
            }
        }

        return $payload;
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function structuralDiffers(SeedDefinition $definition, Model $current, array $row): bool
    {
        foreach ($definition->structural as $field) {
            if (! array_key_exists($field, $row)) {
                continue;
            }

            if (! ValueComparator::equal($current->getAttribute($field), $row[$field])) {
                return true;
            }
        }

        return false;
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
```

`Modules/Core/app/Seeding/ValueComparator.php` — shared with `SettingsCleaner` in Task 10, which must
answer the same "has this drifted?" question the same way:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use BackedEnum;

final class ValueComparator
{
    /**
     * Compare decoded values so JSON key order, 1 vs 1.0, or an enum against
     * its backing value never read as drift.
     */
    public static function equal(mixed $left, mixed $right): bool
    {
        return self::normalize($left) === self::normalize($right);
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_string($value) && json_validate($value)) {
            $value = json_decode($value, true);
        }

        if (is_array($value)) {
            ksort($value);

            return array_map(static fn (mixed $item): mixed => self::normalize($item), $value);
        }

        return is_numeric($value) ? (string) (float) $value : $value;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Integration/Seeding/SeedReconcilerTest.php`
Expected: PASS — 7 tests

If the query-count test reports a mismatch, check that `whereIn` is not being split — it should be one statement regardless of row count.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Seeding tests/Integration/Seeding/SeedReconcilerTest.php
git -C Modules/Core commit -m "feat(core): SeedReconciler with fixed query budget and drift-safe realignment"
git add Modules/Core
git commit -m "chore: bump Core for SeedReconciler"
```

---

### Task 6: ModelCapabilityScanner — one pass instead of two

Replaces 749 hierarchy traversals with 107, and deletes the duplicate filesystem walk.

**Files:**
- Create: `Modules/Core/app/Seeding/ModelCapabilityScanner.php`
- Test: `Modules/Core/tests/Integration/Seeding/ModelCapabilityScannerTest.php`

**Interfaces:**
- Produces: `ModelCapabilityScanner::scan(): list<ModelCapabilities>` where each entry exposes `string $modelClass`, `string $table`, `bool $hasVersions`, `$hasSoftDeletes`, `$hasLocks`, `$hasOptimisticLocking`, `$hasTranslations`, `$hasApprovals`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Models\Setting;
use Modules\Core\Seeding\ModelCapabilityScanner;

it('reports HasApprovals without a second filesystem walk', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $tables = array_column($scanned, null, 'table');

    expect($tables)->toHaveKey((new Setting)->getTable())
        ->and($tables[(new Setting)->getTable()]->hasApprovals)->toBeTrue();
});

it('computes the trait set once per model', function (): void {
    $scanned = app(ModelCapabilityScanner::class)->scan();
    $classes = array_column($scanned, 'modelClass');

    expect($classes)->toBe(array_unique($classes));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Seeding/ModelCapabilityScannerTest.php`
Expected: FAIL — `Class "Modules\Core\Seeding\ModelCapabilityScanner" not found`

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Locking\Traits\HasOptimisticLocking;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\Models\Concerns\HasTranslations;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\SoftDeletes\SoftDeletes;
use ReflectionClass;
use Throwable;

use function class_uses_recursive;
use function models;

final readonly class ModelCapabilities
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        public string $modelClass,
        public string $table,
        public bool $hasVersions,
        public bool $hasSoftDeletes,
        public bool $hasLocks,
        public bool $hasOptimisticLocking,
        public bool $hasTranslations,
        public bool $hasApprovals,
    ) {}
}

final class ModelCapabilityScanner
{
    /**
     * Walk every discoverable model once, resolving its full trait set in a
     * single traversal instead of one per capability.
     *
     * @return list<ModelCapabilities>
     */
    public function scan(): array
    {
        $scanned = [];

        foreach (models() as $model_class) {
            try {
                /** @var Model $instance */
                $instance = new ReflectionClass($model_class)->newInstanceWithoutConstructor();
                $table = $instance->getTable();
            } catch (Throwable) {
                continue;
            }

            $traits = class_uses_recursive($model_class);

            $scanned[] = new ModelCapabilities(
                modelClass: $model_class,
                table: $table,
                hasVersions: isset($traits[HasVersions::class]),
                hasSoftDeletes: isset($traits[SoftDeletes::class]),
                hasLocks: isset($traits[HasLocks::class]),
                hasOptimisticLocking: isset($traits[HasOptimisticLocking::class]),
                hasTranslations: isset($traits[HasTranslations::class]),
                hasApprovals: isset($traits[HasApprovals::class]),
            );
        }

        return $scanned;
    }
}
```

Place `ModelCapabilities` in its own file `Modules/Core/app/Seeding/ModelCapabilities.php` rather than inline — the two-classes-per-file form above is shown only for readability.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Integration/Seeding/ModelCapabilityScannerTest.php`
Expected: PASS — 2 tests

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Seeding tests/Integration/Seeding/ModelCapabilityScannerTest.php
git -C Modules/Core commit -m "perf(core): single-pass model capability scan"
git add Modules/Core
git commit -m "chore: bump Core for the single-pass capability scan"
```

---

### Task 7: Rewrite `CoreDatabaseSeeder::defaultSettings()` on the new primitives

**Files:**
- Modify: `Modules/Core/database/seeders/CoreDatabaseSeeder.php` — replace `defaultSettings()` and `defaultApprovalSettings()`; delete `getModelsWithApprovals()`, `getClassNameFromFile()`, `usesHasApprovalsTrait()`, `deleteRefuses()`; remove `Artisan::call('cache:clear')`
- Test: `Modules/Core/tests/Feature/Seeding/CoreSettingsReconciliationTest.php`

**Interfaces:**
- Consumes: `SeedReconciler`, `SeedDefinition`, `ModelCapabilityScanner`, `SettingsCacheCoordinator::flushAll()`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;

it('seeds per-model capability settings with the resolver naming', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $name = PerModelSettingResolver::nameFor('version_strategy', (new Setting)->getTable());

    expect(Setting::query()->withoutGlobalScopes()->where('name', $name)->exists())->toBeTrue();
});

it('is idempotent and leaves operator values untouched on a second run', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    Setting::query()->withoutGlobalScopes()
        ->where('name', 'pagination')
        ->update(['value' => json_encode(999)]);

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $setting = Setting::query()->withoutGlobalScopes()->where('name', 'pagination')->sole();

    expect($setting->value)->toBe(999);
});

it('no longer force-deletes settings during a run', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $orphan = Setting::query()->forceCreate([
        'name' => 'version_strategy_gone_table',
        'value' => 'DIFF',
        'encrypted' => false,
        'type' => Modules\Core\Casts\SettingTypeEnum::String,
        'group_name' => 'base',
        'description' => 'Orphan',
    ]);

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    expect(Setting::query()->withoutGlobalScopes()->withTrashed()->find($orphan->getKey()))
        ->not->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Feature/Seeding/CoreSettingsReconciliationTest.php`
Expected: FAIL — the third test fails because `deleteRefuses()` still force-deletes the orphan

- [ ] **Step 3: Write the implementation**

Replace the body of `defaultSettings()` so that it:

1. builds the static definitions (`default_language`, `pagination`, `max_concurrent_sessions`) plus `self::runtimeSettingDefinitions()`;
2. calls `app(ModelCapabilityScanner::class)->scan()` **once** and, for each `ModelCapabilities` entry, appends the per-capability rows using `PerModelSettingResolver::nameFor()` for every name — the existing `seedVersionedModel()` … `seedAiModerationModel()` helpers keep their bodies but push rows instead of writing;
3. hands the whole list to the reconciler in one call:

```php
$outcome = app(SeedReconciler::class)->reconcile(
    SeedDefinition::for(Setting::class)
        ->identity(['name'])
        ->structural(['type', 'group_name', 'description', 'choices'])
        ->initial(['value'])
        ->ownedBy('Core')
        ->rows($default_settings),
);

$this->command->line("    - created {$this->count($outcome->created)}, "
    . "realigned {$this->count($outcome->realigned)}, "
    . "unchanged {$outcome->unchanged}");
```

4. drops `$to_remove_settings` and the `deleteRefuses()` call entirely — orphan handling moves to Task 10;
5. replaces `Artisan::call('cache:clear')` in `run()` with:

```php
app(SettingsCacheCoordinator::class)->flushAll();
```

Delete `getModelsWithApprovals()`, `getClassNameFromFile()`, `usesHasApprovalsTrait()` and `deleteRefuses()`, and drop the now-unused `File`, `ReflectionClass` and `Throwable` imports. `defaultApprovalSettings()` folds into the single pass — its rows join the same definition.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Feature/Seeding/CoreSettingsReconciliationTest.php`
Expected: PASS — 3 tests

Then confirm nothing else regressed:

Run: `php artisan test --compact Modules/Core/tests/Feature/Settings`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add database/seeders/CoreDatabaseSeeder.php tests/Feature/Seeding
git -C Modules/Core commit -m "perf(core): reconcile Core settings in one pass, drop duplicate model scan"
git add Modules/Core
git commit -m "chore: bump Core for single-pass settings reconciliation"
```

---

### Task 8: Ledger table and per-node atomicity

**Files:**
- Modify: `Modules/Core/app/Enums/CoreTables.php` — add `case SeedRuns = 'core_seed_runs';`
- Create: `Modules/Core/database/migrations/2026_07_31_000001_create_seed_runs_table.php`
- Create: `Modules/Core/app/Models/SeedRun.php`
- Create: `Modules/Core/app/Seeding/SeedLedger.php`
- Test: `Modules/Core/tests/Integration/Seeding/SeedLedgerTest.php`

**Interfaces:**
- Produces: `SeedLedger::start(string $runId, string $node): void`, `::succeed(string $runId, string $node, string $contentHash): void`, `::fail(string $runId, string $node, string $error): void`, `::completedNodes(string $runId): list<string>`, `::lastFailedRunId(): ?string`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Models\SeedRun;
use Modules\Core\Seeding\SeedLedger;

it('records a successful node with its content hash', function (): void {
    $ledger = app(SeedLedger::class);
    $ledger->start('run-1', 'CoreSeeder');
    $ledger->succeed('run-1', 'CoreSeeder', 'abc123');

    $row = SeedRun::query()->where('run_id', 'run-1')->sole();

    expect($row->status)->toBe('succeeded')
        ->and($row->content_hash)->toBe('abc123')
        ->and($row->finished_at)->not->toBeNull();
});

it('records a failure with the error message', function (): void {
    $ledger = app(SeedLedger::class);
    $ledger->start('run-2', 'ErpSeeder');
    $ledger->fail('run-2', 'ErpSeeder', 'constraint violation');

    $row = SeedRun::query()->where('run_id', 'run-2')->sole();

    expect($row->status)->toBe('failed')
        ->and($row->error)->toContain('constraint violation');
});

it('lists only the completed nodes of a run', function (): void {
    $ledger = app(SeedLedger::class);
    $ledger->start('run-3', 'A');
    $ledger->succeed('run-3', 'A', 'h1');
    $ledger->start('run-3', 'B');
    $ledger->fail('run-3', 'B', 'boom');

    expect($ledger->completedNodes('run-3'))->toBe(['A']);
});

it('finds the most recent failed run', function (): void {
    $ledger = app(SeedLedger::class);
    $ledger->start('run-4', 'A');
    $ledger->fail('run-4', 'A', 'boom');

    expect($ledger->lastFailedRunId())->toBe('run-4');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Seeding/SeedLedgerTest.php`
Expected: FAIL — `Class "Modules\Core\Models\SeedRun" not found`

- [ ] **Step 3: Write the implementation**

Migration:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class() extends Migration
{
    public function up(): void
    {
        $table_name = CoreTables::SeedRuns->value;

        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('run_id')->nullable(false)->index("{$table_name}_run_id_IDX")
                ->comment('Identifies one invocation of the orchestrator');
            $table->string('node')->nullable(false)->comment('Seeder class executed');
            $table->string('status', 20)->nullable(false)->comment('running, succeeded or failed');
            $table->string('content_hash')->nullable(true)
                ->comment('Hash of the definitions this node would write');
            $table->timestamp('started_at')->nullable(false);
            $table->timestamp('finished_at')->nullable(true);
            $table->text('error')->nullable(true);

            $table->unique(['run_id', 'node'], "{$table_name}_run_node_UN");

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CoreTables::SeedRuns->value);
    }
};
```

`Modules/Core/app/Models/SeedRun.php` — deliberately carries **no** capability traits: the ledger
must stay writable even while the seeder is repairing the settings that govern those traits.

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Modules\Core\Enums\CoreTables;
use Modules\Core\Overrides\Model;
use Override;

final class SeedRun extends Model
{
    /** @var string */
    #[Override]
    protected $table = CoreTables::SeedRuns->value;

    /** @var array<int,string> */
    #[Override]
    protected $fillable = [
        'run_id',
        'node',
        'status',
        'content_hash',
        'started_at',
        'finished_at',
        'error',
    ];

    /**
     * @return array<string,string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
```

`Modules/Core/app/Seeding/SeedLedger.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Modules\Core\Models\SeedRun;

final class SeedLedger
{
    public function start(string $runId, string $node): void
    {
        SeedRun::query()->updateOrCreate(
            ['run_id' => $runId, 'node' => $node],
            ['status' => 'running', 'started_at' => now(), 'finished_at' => null, 'error' => null],
        );
    }

    public function succeed(string $runId, string $node, string $contentHash): void
    {
        SeedRun::query()
            ->where('run_id', $runId)
            ->where('node', $node)
            ->update([
                'status' => 'succeeded',
                'content_hash' => $contentHash,
                'finished_at' => now(),
            ]);
    }

    public function fail(string $runId, string $node, string $error): void
    {
        SeedRun::query()
            ->where('run_id', $runId)
            ->where('node', $node)
            ->update(['status' => 'failed', 'error' => $error, 'finished_at' => now()]);
    }

    /**
     * @return list<string>
     */
    public function completedNodes(string $runId): array
    {
        return SeedRun::query()
            ->where('run_id', $runId)
            ->where('status', 'succeeded')
            ->orderBy('id')
            ->pluck('node')
            ->all();
    }

    public function lastFailedRunId(): ?string
    {
        return SeedRun::query()
            ->where('status', 'failed')
            ->latest('id')
            ->value('run_id');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Integration/Seeding/SeedLedgerTest.php`
Expected: PASS — 4 tests

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Enums/CoreTables.php app/Models/SeedRun.php app/Seeding/SeedLedger.php database/migrations tests/Integration/Seeding/SeedLedgerTest.php
git -C Modules/Core commit -m "feat(core): seed run ledger"
git add Modules/Core
git commit -m "chore: bump Core for the seed run ledger"
```

---

### Task 9: SeedOrchestrator — run the graph, stop loudly, resume

**Files:**
- Create: `Modules/Core/app/Seeding/SeedOrchestrator.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `Modules/Core/app/Console/SeedCommand.php` — add `--resume`
- Test: `Modules/Core/tests/Feature/Seeding/SeedOrchestratorTest.php`

**Interfaces:**
- Consumes: `SeedGraphBuilder::build()`, `SeedLedger`, `SettingsCacheCoordinator::flushAll()`
- Produces: `SeedOrchestrator::run(?string $resumeRunId = null): int` returning an exit code; `SeedOrchestrator::withNodes(list<SeedNode>): self` for tests

- [ ] **Step 1: Write the failing test**

Stubs go in `Modules/Core/tests/Stubs/Seeding/` — `PassingStubSeeder` writes a marker to the container, `FailingStubSeeder` throws `RuntimeException('stub failure')`.

```php
<?php

declare(strict_types=1);

use Modules\Core\Seeding\SeedLedger;
use Modules\Core\Seeding\SeedNode;
use Modules\Core\Seeding\SeedOrchestrator;
use Modules\Core\Tests\Stubs\Seeding\FailingStubSeeder;
use Modules\Core\Tests\Stubs\Seeding\PassingStubSeeder;

it('stops at the first failure and returns a non-zero exit code', function (): void {
    $orchestrator = app(SeedOrchestrator::class)->withNodes([
        new SeedNode(PassingStubSeeder::class, 'Core'),
        new SeedNode(FailingStubSeeder::class, 'Core', [PassingStubSeeder::class]),
    ]);

    expect($orchestrator->run())->not->toBe(0);
});

it('does not re-execute nodes completed in the interrupted run', function (): void {
    $nodes = [
        new SeedNode(PassingStubSeeder::class, 'Core'),
        new SeedNode(FailingStubSeeder::class, 'Core', [PassingStubSeeder::class]),
    ];

    app(SeedOrchestrator::class)->withNodes($nodes)->run();

    $run_id = app(SeedLedger::class)->lastFailedRunId();
    PassingStubSeeder::$runCount = 0;

    app(SeedOrchestrator::class)->withNodes($nodes)->run($run_id);

    expect(PassingStubSeeder::$runCount)->toBe(0);
});

it('rolls back the failing node without touching earlier ones', function (): void {
    $orchestrator = app(SeedOrchestrator::class)->withNodes([
        new SeedNode(PassingStubSeeder::class, 'Core'),
        new SeedNode(FailingStubSeeder::class, 'Core', [PassingStubSeeder::class]),
    ]);

    $orchestrator->run();

    expect(Modules\Core\Models\Setting::query()->withoutGlobalScopes()
        ->where('name', FailingStubSeeder::PARTIAL_MARKER)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Feature/Seeding/SeedOrchestratorTest.php`
Expected: FAIL — `Class "Modules\Core\Seeding\SeedOrchestrator" not found`

- [ ] **Step 3: Write the implementation**

`Modules/Core/app/Seeding/SeedOrchestrator.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Services\SettingsCacheCoordinator;
use Throwable;

final class SeedOrchestrator
{
    /** @var list<SeedNode>|null */
    private ?array $nodes = null;

    public function __construct(
        private readonly SeedGraphBuilder $builder,
        private readonly SeedLedger $ledger,
        private readonly SettingsCacheCoordinator $cache,
    ) {}

    /**
     * Override graph discovery. Tests use this; production always discovers.
     *
     * @param  list<SeedNode>  $nodes
     */
    public function withNodes(array $nodes): self
    {
        $this->nodes = SeedGraph::sort($nodes);

        return $this;
    }

    /**
     * Execute every node in dependency order.
     *
     * Returns 0 on success, 1 on the first failure. Nodes after a failure do
     * not run: a release must stop rather than apply half a configuration.
     */
    public function run(?string $resumeRunId = null): int
    {
        $nodes = $this->nodes ?? $this->builder->build();
        $run_id = $resumeRunId ?? (string) Str::uuid();
        $already_done = $resumeRunId === null
            ? []
            : array_flip($this->ledger->completedNodes($resumeRunId));

        foreach ($nodes as $node) {
            if (isset($already_done[$node->seederClass])) {
                continue;
            }

            $this->ledger->start($run_id, $node->seederClass);

            try {
                /** @var Seeder $seeder */
                $seeder = app($node->seederClass);

                DB::connection()->transaction(static function () use ($seeder): void {
                    $seeder->run();
                });

                $this->ledger->succeed(
                    $run_id,
                    $node->seederClass,
                    hash('xxh128', $node->seederClass),
                );
            } catch (Throwable $throwable) {
                $this->ledger->fail($run_id, $node->seederClass, $throwable->getMessage());

                Log::error('Seed node failed', [
                    'run_id' => $run_id,
                    'node' => $node->seederClass,
                    'exception' => $throwable,
                ]);

                return 1;
            }
        }

        $this->cache->flushAll();

        return 0;
    }
}
```

The `content_hash` above is a placeholder derived from the class name only. Hashing the actual
emitted definitions requires seeders to expose them without executing, which no task in this plan
delivers — `--skip-unchanged` therefore stays unimplemented and the option is registered but
rejected with a clear message until a follow-up plan adds definition extraction. Do **not** wire
`--skip-unchanged` to this hash: it would skip nodes whose content actually changed.

`database/seeders/DatabaseSeeder.php` becomes:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Seeding\SeedOrchestrator;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $exit_code = app(SeedOrchestrator::class)->run(
            resumeRunId: $this->command?->option('resume') === true
                ? app(\Modules\Core\Seeding\SeedLedger::class)->lastFailedRunId()
                : null,
        );

        if ($exit_code !== 0) {
            throw new \RuntimeException('Seeding failed; see the run ledger for the failing node.');
        }
    }
}
```

Add to `SeedCommand::getOptions()`:

```php
['resume', null, InputOption::VALUE_NONE, 'Skip nodes that succeeded in the last failed run'],
```

`--skip-unchanged` is **not** registered by this plan. The spec describes it as an explicit opt-in
over the content hash, but a hash that faithfully represents what a node would write requires
extracting definitions without executing the seeder — capability no task here delivers. Shipping the
flag over the placeholder hash would skip nodes whose content actually changed, which is worse than
not having the flag. It belongs to a follow-up plan.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Feature/Seeding/SeedOrchestratorTest.php`
Expected: PASS — 3 tests

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Seeding/SeedOrchestrator.php app/Console/SeedCommand.php tests/Stubs/Seeding tests/Feature/Seeding/SeedOrchestratorTest.php
git -C Modules/Core commit -m "feat(core): seed orchestrator with per-node atomicity and in-run resume"
git add Modules/Core database/seeders/DatabaseSeeder.php
git commit -m "feat: delegate root DatabaseSeeder to the Core orchestrator"
```

---

### Task 10: Cleanup by module state

The only task that deletes data. It goes last, behind everything that makes deletion safe.

**Files:**
- Create: `Modules/Core/app/Seeding/ModuleState.php`
- Create: `Modules/Core/app/Seeding/ModuleStateResolver.php`
- Create: `Modules/Core/app/Seeding/CleanupReport.php`
- Create: `Modules/Core/app/Seeding/SettingsCleaner.php`
- Create: `Modules/Core/tests/Stubs/Seeding/FixedModuleStateResolver.php`
- Test: `Modules/Core/tests/Integration/Seeding/SettingsCleanerTest.php`

The stub exists because the Global Constraints forbid declaring classes inside test files — an
anonymous `new class … extends ModuleStateResolver` in the test would violate it:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Seeding;

use Modules\Core\Seeding\ModuleState;
use Modules\Core\Seeding\ModuleStateResolver;
use Override;

final class FixedModuleStateResolver extends ModuleStateResolver
{
    public function __construct(private readonly ModuleState $state) {}

    #[Override]
    public function for(?string $module): ModuleState
    {
        return $this->state;
    }
}
```

**Interfaces:**
- Consumes: `core_settings.module` / `seeded_value` from Task 3
- Produces: `ModuleState` enum (`Enabled`, `Disabled`, `Absent`); `ModuleStateResolver::for(?string $module): ModuleState`; `SettingsCleaner::clean(): CleanupReport` with `list<string> $hardDeleted`, `$softDeleted`, `$preserved`

- [ ] **Step 1: Write the failing test**

`ModuleStateResolver` is bound in the container so tests can swap it for a stub returning a fixed map.

```php
<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Seeding\ModuleState;
use Modules\Core\Seeding\ModuleStateResolver;
use Modules\Core\Seeding\SettingsCleaner;
use Modules\Core\Tests\Stubs\Seeding\FixedModuleStateResolver;

function seededSetting(string $name, mixed $value, mixed $baseline, ?string $module): Setting
{
    return Setting::query()->forceCreate([
        'name' => $name,
        'value' => $value,
        'encrypted' => false,
        'type' => SettingTypeEnum::Integer,
        'group_name' => 'base',
        'description' => 'Probe',
        'module' => $module,
        'seeded_value' => $baseline,
    ]);
}

function resolverReturning(ModuleState $state): void
{
    app()->instance(ModuleStateResolver::class, new FixedModuleStateResolver($state));
}

it('hard deletes an untouched setting of a disabled module', function (): void {
    $setting = seededSetting('clean_untouched', 5, 5, 'MES');
    resolverReturning(ModuleState::Disabled);

    app(SettingsCleaner::class)->clean();

    expect(Setting::query()->withoutGlobalScopes()->withTrashed()->find($setting->getKey()))
        ->toBeNull();
});

it('soft deletes a customized setting of a disabled module', function (): void {
    $setting = seededSetting('clean_touched', 99, 5, 'MES');
    resolverReturning(ModuleState::Disabled);

    app(SettingsCleaner::class)->clean();

    $fresh = Setting::query()->withoutGlobalScopes()->withTrashed()->find($setting->getKey());

    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeTrue()
        ->and($fresh->value)->toBe(99);
});

it('force deletes settings of a module absent from disk', function (): void {
    $setting = seededSetting('clean_absent', 99, 5, 'GHOST');
    resolverReturning(ModuleState::Absent);

    app(SettingsCleaner::class)->clean();

    expect(Setting::query()->withoutGlobalScopes()->withTrashed()->find($setting->getKey()))
        ->toBeNull();
});

it('never touches a setting the seeder did not write', function (): void {
    $setting = seededSetting('clean_hand_made', 42, null, null);
    resolverReturning(ModuleState::Absent);

    app(SettingsCleaner::class)->clean();

    $fresh = Setting::query()->withoutGlobalScopes()->withTrashed()->find($setting->getKey());

    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeFalse();
});

it('leaves settings of enabled modules alone', function (): void {
    $setting = seededSetting('clean_active', 5, 5, 'Core');
    resolverReturning(ModuleState::Enabled);

    app(SettingsCleaner::class)->clean();

    expect(Setting::query()->withoutGlobalScopes()->find($setting->getKey()))->not->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Seeding/SettingsCleanerTest.php`
Expected: FAIL — `Class "Modules\Core\Seeding\ModuleState" not found`

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

enum ModuleState
{
    case Enabled;
    case Disabled;
    case Absent;
}
```

`ModuleStateResolver` (non-final so tests can extend it):

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Nwidart\Modules\Facades\Module;

class ModuleStateResolver
{
    public function for(?string $module): ModuleState
    {
        if ($module === null || $module === '') {
            return ModuleState::Enabled;
        }

        if (array_key_exists($module, Module::allEnabled())) {
            return ModuleState::Enabled;
        }

        return array_key_exists($module, Module::all())
            ? ModuleState::Disabled
            : ModuleState::Absent;
    }
}
```

`Modules/Core/app/Seeding/CleanupReport.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

final readonly class CleanupReport
{
    /**
     * @param  list<string>  $hardDeleted
     * @param  list<string>  $softDeleted
     * @param  list<string>  $preserved
     */
    public function __construct(
        public array $hardDeleted = [],
        public array $softDeleted = [],
        public array $preserved = [],
    ) {}
}
```

`Modules/Core/app/Seeding/SettingsCleaner.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Modules\Core\Models\Setting;

final class SettingsCleaner
{
    public function __construct(private readonly ModuleStateResolver $resolver) {}

    /**
     * Remove derived settings whose owning module is disabled or gone.
     *
     * The whereNotNull pair is the safety mechanism, not a convenience: a row
     * without a module or without a baseline was never written by a seeder, so
     * it is never a candidate for deletion. Keep the filter in the query.
     */
    public function clean(): CleanupReport
    {
        $hard_deleted = [];
        $soft_deleted = [];
        $preserved = [];

        $candidates = Setting::query()
            ->withoutGlobalScopes()
            ->whereNotNull('module')
            ->whereNotNull('seeded_value')
            ->get();

        foreach ($candidates as $setting) {
            $state = $this->resolver->for($setting->getAttribute('module'));

            if ($state === ModuleState::Enabled) {
                $preserved[] = (string) $setting->getAttribute('name');

                continue;
            }

            $drifted = ! ValueComparator::equal(
                $setting->getAttribute('value'),
                $setting->getAttribute('seeded_value'),
            );

            if ($state === ModuleState::Absent || ! $drifted) {
                $setting->forceDelete();
                $hard_deleted[] = (string) $setting->getAttribute('name');

                continue;
            }

            $setting->delete();
            $soft_deleted[] = (string) $setting->getAttribute('name');
        }

        return new CleanupReport($hard_deleted, $soft_deleted, $preserved);
    }
}
```

The resulting behaviour, restated as the matrix the spec locks in:

| `ModuleState` | Drift | Action |
|---|---|---|
| `Enabled` | — | skip |
| `Disabled` | no | `forceDelete()` |
| `Disabled` | yes | `delete()` (soft) |
| `Absent` | — | `forceDelete()` |
| any | `module` or `seeded_value` null | never selected |

Finally, wire it into `SeedOrchestrator::run()` — after the node loop completes successfully and
**before** `flushAll()`, so a cleanup pass never runs against a half-applied configuration:

```php
$report = app(SettingsCleaner::class)->clean();

if ($report->hardDeleted !== [] || $report->softDeleted !== []) {
    Log::info('Seed cleanup removed orphaned settings', [
        'hard_deleted' => $report->hardDeleted,
        'soft_deleted' => $report->softDeleted,
    ]);
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Integration/Seeding/SettingsCleanerTest.php`
Expected: PASS — 5 tests

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Seeding tests/Stubs/Seeding tests/Integration/Seeding/SettingsCleanerTest.php
git -C Modules/Core commit -m "feat(core): settings cleanup keyed on module state and drift"
git add Modules/Core
git commit -m "chore: bump Core for settings cleanup"
```

---

### Task 11: Migrate AI, CMS, ERP and MES seeders — DEFERRED

> **Do not execute this task in the current sequence.** It is the only task touching submodules
> other than Core, and `Modules/ERP/database/seeders/ERPDatabaseSeeder.php` — which this task
> rewrites — carries uncommitted work from a concurrent session. Executing it would risk that work.
>
> Resume it once ERP's in-flight changes are committed and the file is clean. Until then Tasks 1–10
> stand on their own: the graph, reconciler, ledger and cleaner all work, and the module seeders keep
> their current behaviour because the orchestrator runs them unchanged.

**Files:**
- Modify: `Modules/AI/database/seeders/AIDatabaseSeeder.php`
- Modify: `Modules/CMS/database/seeders/CMSDatabaseSeeder.php`
- Modify: `Modules/ERP/database/seeders/ERPDatabaseSeeder.php`, `Modules/ERP/database/seeders/ItalianTaxCodesSeeder.php`
- Modify: `Modules/MES/database/seeders/MESDatabaseSeeder.php`
- Test: `Modules/Core/tests/Feature/Seeding/FullSeedRunTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1–10

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Models\Setting;
use Modules\Core\Seeding\SeedOrchestrator;

it('completes a full seed run and is idempotent', function (): void {
    expect(app(SeedOrchestrator::class)->run())->toBe(0);

    $before = Setting::query()->withoutGlobalScopes()->count();

    expect(app(SeedOrchestrator::class)->run())->toBe(0)
        ->and(Setting::query()->withoutGlobalScopes()->count())->toBe($before);
});

it('attributes every seeded setting to an owning module', function (): void {
    app(SeedOrchestrator::class)->run();

    $unattributed = Setting::query()->withoutGlobalScopes()
        ->whereNull('module')
        ->pluck('name')
        ->all();

    expect($unattributed)->toBe([]);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Feature/Seeding/FullSeedRunTest.php`
Expected: FAIL — module seeders still write directly, leaving `module` null

- [ ] **Step 3: Write the implementation**

For each module seeder, replace `seedSettingDefinitions()` and `firstOrCreate()` loops with a `SeedDefinition` handed to the reconciler, passing `->ownedBy('<Module>')`. Add `implements DeclaresSeedDependencies` with `dependsOn()` where intra-module order matters:

- `ItalianTaxCodesSeeder::dependsOn()` returns `[ERPDatabaseSeeder::class]` — today that order holds only because `E` sorts before `I`.
- `ERPDatabaseSeeder::ensureDomainPermissions()` keeps `firstOrCreate` on `Permission`, which is not a `SeedDefinition` target; leave it, but move the `permission:refresh` call out of `CoreDatabaseSeeder::defaultPermissions()` into its own seeder node so ERP can depend on it.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/Core/tests/Feature/Seeding/FullSeedRunTest.php`
Expected: PASS — 2 tests

Then the full suites of every touched module:

Run: `php artisan test --compact Modules/Core/tests Modules/ERP/tests Modules/CMS/tests`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add Modules/AI Modules/CMS Modules/ERP Modules/MES Modules/Core/tests/Feature/Seeding/FullSeedRunTest.php
git commit -m "refactor(modules): seed definitions and declared dependencies per module"
```

---

### Task 12: Documentation

**Files:**
- Modify: `Modules/Core/README.md` — seeding section
- Modify: `docs/superpowers/plans/INDEX.md` — add this plan

- [ ] **Step 1: Write the Core README section**

Document, in the existing README style: the `dependsOn()` contract, the `SeedDefinition` structural/initial distinction, the `module` and `seeded_value` columns and what drift means, the cleanup matrix, and the `--resume` / `--skip-unchanged` options. No new env vars are introduced by this work, so the env table is unchanged.

- [ ] **Step 2: Add the plan to the index**

Append a one-line entry to `docs/superpowers/plans/INDEX.md` following the existing format.

- [ ] **Step 3: Commit**

```bash
git -C Modules/Core add README.md
git -C Modules/Core commit -m "docs(core): seeding orchestration and reconciliation"
git add Modules/Core docs/superpowers/plans/INDEX.md
git commit -m "docs: index the seeder orchestration plan"
```

`docs/superpowers/plans/INDEX.md` may carry unrelated pending entries from other sessions. Stage the
file only if your line is the sole change; otherwise leave it and say so in the report.

---

## Notes for the implementer

**Order matters and is not negotiable.** Task 10 deletes data and depends on `seeded_value` being populated by Tasks 3, 5 and 7. Running it earlier would delete rows whose baseline is still null — which the `seeded_value IS NOT NULL` filter prevents, but only if that filter is written exactly as specified.

**The upsert `$update` list is the safety mechanism.** `SeedReconciler` passes `$definition->structural` and nothing else. Adding `value` or `seeded_value` to that list would silently overwrite operator customizations on every release. If a test ever needs them there, the test is wrong.

**Query-count assertions are load-bearing.** The test in Task 5 that compares 1 row against 50 rows exists to catch a regression into per-row queries. If it fails after a refactor, the refactor reintroduced the N+1 this plan removes.
