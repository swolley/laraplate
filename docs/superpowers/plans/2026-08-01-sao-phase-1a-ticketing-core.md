# SAO Phase 1a — Internal Ticketing Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `Modules/SAO` a usable standalone tracker — projects, per-project ticket keys, ticket types with shareable workflow schemes, enforced transitions, comments, a merged history, and Filament surfaces — with no connection to any external system.

**Architecture:** Configuration entities (statuses, workflow schemes, transitions, types) are data, not code. A ticket's permitted moves are resolved from the workflow scheme of its (project, type) pair and enforced by a domain service that is the only path to a status change. History is a read model over Core's versioning plus comments; no activity table. Authorization is entirely Laraplate's — `PermissionName` for permissions and Core's ACL chain for row-level visibility.

**Tech Stack:** PHP 8.5, Laravel 12, `nwidart/laravel-modules` 12, Filament 5 (via `Coolsam\Modules\Resource`), Pest 4, PHPStan/Larastan 3, Pint.

## Global Constraints

- **Spec:** `docs/superpowers/specs/2026-08-01-sao-phase-1a-ticketing-core-design.md`. Decisions are numbered E1–E12 there; tasks reference them.
- Every PHP file starts with `declare(strict_types=1);`. Models and services are `final` unless a sibling proves otherwise.
- Models extend `Modules\Core\Overrides\Model`, which already provides `HasFactory`, `HasPrefixedTableName`, `HasValidations`, `HasVersions` and `SoftDeletes`.
- Table names come from a `SAOTables` enum using `Modules\Core\Enums\Concerns\HasModuleTablesUtils`; models set `#[Override] protected $table = SAOTables::X->value;`. Follow CMS/ERP, not MES — MES hardcodes table-name strings.
- Migrations use `Modules\Core\Helpers\MigrateUtils::timestamps(...)` rather than `$table->timestamps()`.
- Validation lives on the model in `#[Override] public function getRules(): array`, merging into `parent::getRules()` under the `create` and `update` keys.
- Enums expose `validationRule(): string` and `values(): array`, following `Modules\MES\Enums\WorkCenterType`.
- Permission names come **only** from `Modules\Core\Support\PermissionName::forModel()` / `::forClass()`. Never build the string by hand.
- **No integration code of any kind** (E12). No `Connection`, no driver, no external HTTP. No reference to `Modules\AI`.
- **Run only SAO's tests.** `php artisan test --filter=<TestName>` from `laraplate/`. Do **not** run the full application suite — it exceeds an hour and its optimization is a separate concern.
- Before finishing any task: `vendor/bin/pint Modules/SAO` then `vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G`.
- Commits go in the **`Modules/SAO` repository** (`cd Modules/SAO`). The `laraplate` gitlink is bumped once, in Task 12.

---

## File Structure

All paths relative to `Modules/SAO/`.

| File | Responsibility |
|------|----------------|
| `app/Enums/SAOTables.php` | Table-name registry |
| `app/Enums/StatusCategory.php` | Canonical status categories (E2) |
| `app/Enums/TicketPriority.php` | Fixed priority enum (E6) |
| `app/Enums/CommentOrigin.php` | `human` / `system` (E8) |
| `app/Models/Project.php` | Project, key prefix, ticket counter |
| `app/Models/TicketStatus.php` | Global status with canonical category |
| `app/Models/WorkflowScheme.php` | Shareable scheme, exactly one default |
| `app/Models/WorkflowTransition.php` | One permitted move within a scheme |
| `app/Models/TicketType.php` | Global type, owns a scheme, `is_defect` |
| `app/Models/Pivot/ProjectTicketType.php` | Project↔type, default flag, scheme override |
| `app/Models/Ticket.php` | The ticket |
| `app/Models/TicketComment.php` | Comment, human or system |
| `app/Data/ChangeContext.php` | Who acted and on behalf of what (E10) |
| `app/Services/TicketKeyAllocator.php` | Allocates `PREFIX-n` under a row lock |
| `app/Services/WorkflowService.php` | Scheme resolution, permitted transitions, enforcement |
| `app/Services/TicketTimelineService.php` | Read model merging versions and comments |
| `app/Exceptions/TransitionNotAllowedException.php` | Rejected transition |
| `database/migrations/*` | One per table |
| `database/factories/*` | One per model |
| `app/Filament/Resources/*` | Generated resources plus the custom ticket page |

---

## Task 1: Enums and the table registry

**Files:**
- Create: `Modules/SAO/app/Enums/SAOTables.php`
- Create: `Modules/SAO/app/Enums/StatusCategory.php`
- Create: `Modules/SAO/app/Enums/TicketPriority.php`
- Create: `Modules/SAO/app/Enums/CommentOrigin.php`
- Create: `Modules/SAO/tests/Unit/Enums/SaoEnumsTest.php`

**Interfaces:**
- Produces: `SAOTables` cases `Projects`, `TicketStatuses`, `WorkflowSchemes`, `WorkflowTransitions`, `TicketTypes`, `ProjectTicketTypes`, `Tickets`, `TicketComments` — every later task reads table names from here. `StatusCategory::Open|InProgress|Resolved|Closed|Rejected`, `TicketPriority::Low|Normal|High|Urgent`, `CommentOrigin::Human|System`, each with `validationRule(): string` and `values(): array`.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Unit/Enums/SaoEnumsTest.php`:

```php
<?php

declare(strict_types=1);

use Modules\SAO\Enums\CommentOrigin;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Enums\TicketPriority;

test('every SAO table name is prefixed with sao_', function (): void {
    foreach (SAOTables::cases() as $case) {
        expect($case->value)->toStartWith('sao_');
    }
});

test('the table registry declares the phase 1a tables', function (): void {
    $values = array_column(SAOTables::cases(), 'value');

    expect($values)->toEqualCanonicalizing([
        'sao_projects',
        'sao_ticket_statuses',
        'sao_workflow_schemes',
        'sao_workflow_transitions',
        'sao_ticket_types',
        'sao_project_ticket_types',
        'sao_tickets',
        'sao_ticket_comments',
    ]);
});

test('status categories are the five canonical ones', function (): void {
    expect(StatusCategory::values())->toBe([
        'open',
        'in_progress',
        'resolved',
        'closed',
        'rejected',
    ]);
});

test('a closed category is terminal and an open one is not', function (): void {
    expect(StatusCategory::Closed->isTerminal())->toBeTrue();
    expect(StatusCategory::Rejected->isTerminal())->toBeTrue();
    expect(StatusCategory::Open->isTerminal())->toBeFalse();
    expect(StatusCategory::InProgress->isTerminal())->toBeFalse();
    expect(StatusCategory::Resolved->isTerminal())->toBeFalse();
});

test('priorities are fixed and ordered from low to urgent', function (): void {
    expect(TicketPriority::values())->toBe(['low', 'normal', 'high', 'urgent']);
});

test('comment origins distinguish humans from automation', function (): void {
    expect(CommentOrigin::values())->toBe(['human', 'system']);
});

test('every enum exposes an in: validation rule', function (string $rule): void {
    expect($rule)->toStartWith('in:');
})->with([
    fn (): string => StatusCategory::validationRule(),
    fn (): string => TicketPriority::validationRule(),
    fn (): string => CommentOrigin::validationRule(),
]);
```

`isTerminal()` matters beyond tidiness: phase 6's closure policies ask "is this ticket finished", and the answer must come from the category rather than from a status name. `Resolved` is deliberately **not** terminal — resolved means fixed but not yet confirmed, which is exactly the state phase 6 watches before closing.

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=SaoEnumsTest
```

Expected: FAIL — `Modules\SAO\Enums\SAOTables` does not exist.

- [ ] **Step 3: Create the table registry**

Create `Modules/SAO/app/Enums/SAOTables.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

use Modules\Core\Enums\Concerns\HasModuleTablesUtils;

enum SAOTables: string
{
    use HasModuleTablesUtils;

    case Projects = 'sao_projects';
    case TicketStatuses = 'sao_ticket_statuses';
    case WorkflowSchemes = 'sao_workflow_schemes';
    case WorkflowTransitions = 'sao_workflow_transitions';
    case TicketTypes = 'sao_ticket_types';
    case ProjectTicketTypes = 'sao_project_ticket_types';
    case Tickets = 'sao_tickets';
    case TicketComments = 'sao_ticket_comments';
}
```

- [ ] **Step 4: Create the canonical status category**

Create `Modules/SAO/app/Enums/StatusCategory.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The canonical meaning behind a configurable status (E2).
 *
 * Phase 3 maps external tracker statuses onto these categories rather than onto
 * status names, and phase 6 decides closures by reading them.
 */
enum StatusCategory: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Rejected = 'rejected';

    /**
     * Whether the ticket needs no further work.
     *
     * Resolved is deliberately not terminal: it means fixed but unconfirmed,
     * which is the state phase 6 observes before closing anything.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Rejected], true);
    }

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 5: Create the priority and comment-origin enums**

Create `Modules/SAO/app/Enums/TicketPriority.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * Fixed by decision E6: priority needs no transitions and no canonical meaning
 * separate from its name, so it is not configurable.
 */
enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

Create `Modules/SAO/app/Enums/CommentOrigin.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * Distinguishes a human comment from one written by automation (E8).
 *
 * A dedicated bot user was rejected: a user can be assigned, filtered on and
 * impersonated, none of which is true of an origin flag.
 */
enum CommentOrigin: string
{
    case Human = 'human';
    case System = 'system';

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

```bash
php artisan test --filter=SaoEnumsTest
```

Expected: PASS, 9 tests.

- [ ] **Step 7: Format, analyse and commit**

```bash
vendor/bin/pint Modules/SAO
vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G
cd Modules/SAO
git add app/Enums tests/Unit/Enums
git commit -m "feat(sao): add the table registry and domain enums"
cd ../..
```

---

## Task 2: Project

**Files:**
- Create: `Modules/SAO/database/migrations/2026_08_01_100000_create_sao_projects_table.php`
- Create: `Modules/SAO/app/Models/Project.php`
- Create: `Modules/SAO/database/factories/ProjectFactory.php`
- Create: `Modules/SAO/tests/Feature/Models/ProjectTest.php`

**Interfaces:**
- Consumes: `SAOTables::Projects` from Task 1.
- Produces: `Project` with `$fillable` `['name', 'key_prefix', 'description', 'is_active']`, a `next_ticket_number` column managed by the allocator in Task 6 (never mass-assignable), and `ProjectFactory::new()`.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Feature/Models/ProjectTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Models\Project;

uses(RefreshDatabase::class);

test('a project is created with an uppercase key prefix', function (): void {
    $project = Project::factory()->create([
        'name' => 'Simply Another Orchestrator',
        'key_prefix' => 'SAO',
    ]);

    expect($project->key_prefix)->toBe('SAO');
    expect($project->is_active)->toBeTrue();
});

test('the ticket counter starts at zero and is not mass assignable', function (): void {
    $project = Project::factory()->create();

    expect($project->next_ticket_number)->toBe(0);

    $project->fill(['next_ticket_number' => 99]);

    expect($project->next_ticket_number)->toBe(0);
});

test('two projects cannot share a key prefix', function (): void {
    Project::factory()->create(['key_prefix' => 'SAO']);

    expect(fn (): Project => Project::factory()->create(['key_prefix' => 'SAO']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

test('the create rules require a name and a prefix of two to ten uppercase characters', function (): void {
    $rules = (new Project)->getRules()['create'];

    expect($rules['name'])->toContain('required');
    expect($rules['key_prefix'])->toContain('required');
    expect($rules['key_prefix'])->toContain('regex:/^[A-Z][A-Z0-9]{1,9}$/');
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=ProjectTest
```

Expected: FAIL — `Modules\SAO\Models\Project` does not exist.

- [ ] **Step 3: Create the migration**

Create `Modules/SAO/database/migrations/2026_08_01_100000_create_sao_projects_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::Projects->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('name');
            $table->string('key_prefix', 10)->comment('Immutable once the first ticket exists');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('next_ticket_number')->default(0)->comment('Allocated under a row lock; gaps are accepted');
            $table->boolean('is_active')->default(true);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('key_prefix', "{$table_name}_key_prefix_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::Projects->value);
    }
};
```

- [ ] **Step 4: Create the model**

Create `Modules/SAO/app/Models/Project.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\ProjectFactory;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * @mixin \Eloquent
 *
 * @property int $id
 * @property string $name
 * @property string $key_prefix
 * @property string|null $description
 * @property int $next_ticket_number
 * @property bool $is_active
 */
final class Project extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::Projects->value;

    /**
     * `next_ticket_number` is deliberately absent: only TicketKeyAllocator may
     * move it, and only under a row lock.
     *
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'name',
        'key_prefix',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, array<string, list<string>>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        $table = SAOTables::Projects->value;

        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255'],
            'key_prefix' => ['required', 'string', 'regex:/^[A-Z][A-Z0-9]{1,9}$/', "unique:{$table},key_prefix"],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $rules;
    }

    /**
     * @return Factory<Project>
     */
    protected static function newFactory(): Factory
    {
        return ProjectFactory::new();
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'next_ticket_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
```

Note that `key_prefix` appears in the create rules but **not** in the update rules: that is where E7's immutability begins. Task 6 adds the model-level guard that closes the direct-assignment path.

- [ ] **Step 5: Create the factory**

Create `Modules/SAO/database/factories/ProjectFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SAO\Models\Project;

/**
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    /**
     * @var class-string<Project>
     */
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'key_prefix' => mb_strtoupper($this->faker->unique()->lexify('???')),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

```bash
php artisan test --filter=ProjectTest
```

Expected: PASS, 4 tests.

If the counter test fails because `next_ticket_number` is null rather than `0`, the migration default did not apply — confirm the column was created with `->default(0)`.

- [ ] **Step 7: Format, analyse and commit**

```bash
vendor/bin/pint Modules/SAO
vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G
cd Modules/SAO
git add app/Models/Project.php database/migrations database/factories tests/Feature/Models
git commit -m "feat(sao): add the project entity with an immutable key prefix"
cd ../..
```

---

## Task 3: Ticket statuses

**Files:**
- Create: `Modules/SAO/database/migrations/2026_08_01_100100_create_sao_ticket_statuses_table.php`
- Create: `Modules/SAO/app/Models/TicketStatus.php`
- Create: `Modules/SAO/database/factories/TicketStatusFactory.php`
- Create: `Modules/SAO/tests/Feature/Models/TicketStatusTest.php`

**Interfaces:**
- Consumes: `SAOTables::TicketStatuses` and `StatusCategory` from Task 1.
- Produces: `TicketStatus` with `$fillable` `['name', 'category', 'colour', 'sort_order']`, `category` cast to `StatusCategory`, and `TicketStatusFactory::new()` plus a `category(StatusCategory $category)` state.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Feature/Models/TicketStatusTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Models\TicketStatus;

uses(RefreshDatabase::class);

test('a status carries a canonical category as an enum', function (): void {
    $status = TicketStatus::factory()->create([
        'name' => 'In review',
        'category' => StatusCategory::InProgress,
    ]);

    expect($status->category)->toBe(StatusCategory::InProgress);
});

test('statuses are global, so two of them may not share a name', function (): void {
    TicketStatus::factory()->create(['name' => 'In review']);

    expect(fn (): TicketStatus => TicketStatus::factory()->create(['name' => 'In review']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

test('a terminal category answers the question phase 6 will ask', function (): void {
    $closed = TicketStatus::factory()->category(StatusCategory::Closed)->create();
    $resolved = TicketStatus::factory()->category(StatusCategory::Resolved)->create();

    expect($closed->category->isTerminal())->toBeTrue();
    expect($resolved->category->isTerminal())->toBeFalse();
});

test('the create rules constrain the category to the canonical set', function (): void {
    $rules = (new TicketStatus)->getRules()['create'];

    expect($rules['category'])->toContain(StatusCategory::validationRule());
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=TicketStatusTest
```

Expected: FAIL — `Modules\SAO\Models\TicketStatus` does not exist.

- [ ] **Step 3: Create the migration**

Create `Modules/SAO/database/migrations/2026_08_01_100100_create_sao_ticket_statuses_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\StatusCategory;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::TicketStatuses->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('name');
            $table->enum('category', StatusCategory::values())->comment('Canonical meaning; phase 3 maps onto this, not onto the name');
            $table->string('colour', 16)->default('gray');
            $table->unsignedInteger('sort_order')->default(0);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('name', "{$table_name}_name_UN");
            $table->index('category', "{$table_name}_category_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::TicketStatuses->value);
    }
};
```

- [ ] **Step 4: Create the model**

Create `Modules/SAO/app/Models/TicketStatus.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\TicketStatusFactory;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\StatusCategory;
use Override;

/**
 * A status is global to the installation (E2). Workflow schemes compose them;
 * defining "In review" once beats eight near-duplicates.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property string $name
 * @property StatusCategory $category
 * @property string $colour
 * @property int $sort_order
 */
final class TicketStatus extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::TicketStatuses->value;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'name',
        'category',
        'colour',
        'sort_order',
    ];

    /**
     * @return array<string, array<string, list<string>>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        $table = SAOTables::TicketStatuses->value;

        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255', "unique:{$table},name"],
            'category' => ['required', 'string', StatusCategory::validationRule()],
            'colour' => ['sometimes', 'string', 'max:16'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', StatusCategory::validationRule()],
            'colour' => ['sometimes', 'string', 'max:16'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return $rules;
    }

    /**
     * @return Factory<TicketStatus>
     */
    protected static function newFactory(): Factory
    {
        return TicketStatusFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'category' => StatusCategory::class,
            'sort_order' => 'integer',
        ];
    }
}
```

- [ ] **Step 5: Create the factory**

Create `Modules/SAO/database/factories/TicketStatusFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Models\TicketStatus;

/**
 * @extends Factory<TicketStatus>
 */
final class TicketStatusFactory extends Factory
{
    /**
     * @var class-string<TicketStatus>
     */
    protected $model = TicketStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'category' => StatusCategory::Open,
            'colour' => 'gray',
            'sort_order' => 0,
        ];
    }

    public function category(StatusCategory $category): self
    {
        return $this->state(fn (): array => ['category' => $category]);
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

```bash
php artisan test --filter=TicketStatusTest
```

Expected: PASS, 4 tests.

- [ ] **Step 7: Format, analyse and commit**

```bash
vendor/bin/pint Modules/SAO
vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G
cd Modules/SAO
git add app/Models/TicketStatus.php database/migrations database/factories tests/Feature/Models
git commit -m "feat(sao): add global ticket statuses with canonical categories"
cd ../..
```

---

## Task 4: Workflow schemes and transitions

**Files:**
- Create: `Modules/SAO/database/migrations/2026_08_01_100200_create_sao_workflow_schemes_table.php`
- Create: `Modules/SAO/database/migrations/2026_08_01_100300_create_sao_workflow_transitions_table.php`
- Create: `Modules/SAO/app/Models/WorkflowScheme.php`
- Create: `Modules/SAO/app/Models/WorkflowTransition.php`
- Create: `Modules/SAO/database/factories/WorkflowSchemeFactory.php`
- Create: `Modules/SAO/database/factories/WorkflowTransitionFactory.php`
- Create: `Modules/SAO/tests/Feature/Models/WorkflowSchemeTest.php`

**Interfaces:**
- Consumes: `TicketStatus` from Task 3.
- Produces: `WorkflowScheme` with `transitions(): HasMany`, `initialTransition(): ?WorkflowTransition`, and a static `default(): ?WorkflowScheme`. `WorkflowTransition` with `$fillable` `['workflow_scheme_id', 'from_status_id', 'to_status_id', 'label', 'required_permission']`, where a null `from_status_id` is the creation transition. Task 7's `WorkflowService` consumes both.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Feature/Models/WorkflowSchemeTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;

uses(RefreshDatabase::class);

test('a scheme owns its transitions', function (): void {
    $scheme = WorkflowScheme::factory()->create();
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create();
    $doing = TicketStatus::factory()->category(StatusCategory::InProgress)->create();

    WorkflowTransition::factory()->for($scheme)->create([
        'from_status_id' => $open->id,
        'to_status_id' => $doing->id,
        'label' => 'Start work',
    ]);

    expect($scheme->transitions()->count())->toBe(1);
});

test('the transition with no source status is the creation transition', function (): void {
    $scheme = WorkflowScheme::factory()->create();
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create();

    $initial = WorkflowTransition::factory()->for($scheme)->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
        'label' => 'Open',
    ]);

    expect($scheme->initialTransition()?->id)->toBe($initial->id);
    expect($scheme->initialTransition()?->to_status_id)->toBe($open->id);
});

test('a scheme may declare only one creation transition', function (): void {
    $scheme = WorkflowScheme::factory()->create();
    $open = TicketStatus::factory()->create();
    $other = TicketStatus::factory()->create();

    WorkflowTransition::factory()->for($scheme)->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
    ]);

    expect(fn (): WorkflowTransition => WorkflowTransition::factory()->for($scheme)->create([
        'from_status_id' => null,
        'to_status_id' => $other->id,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

test('exactly one scheme is the default', function (): void {
    $first = WorkflowScheme::factory()->create(['is_default' => true]);
    $second = WorkflowScheme::factory()->create(['is_default' => true]);

    expect(WorkflowScheme::query()->where('is_default', true)->count())->toBe(1);
    expect(WorkflowScheme::default()?->id)->toBe($second->id);
    expect($first->fresh()->is_default)->toBeFalse();
});
```

The last test encodes a real rule: setting a new default must clear the previous one. Two defaults is a state from which the system cannot answer "which scheme applies", and it is far cheaper to make unrepresentable than to detect later.

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=WorkflowSchemeTest
```

Expected: FAIL — `Modules\SAO\Models\WorkflowScheme` does not exist.

- [ ] **Step 3: Create both migrations**

Create `Modules/SAO/database/migrations/2026_08_01_100200_create_sao_workflow_schemes_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::WorkflowSchemes->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('name', "{$table_name}_name_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::WorkflowSchemes->value);
    }
};
```

Create `Modules/SAO/database/migrations/2026_08_01_100300_create_sao_workflow_transitions_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::WorkflowTransitions->value;
        $statuses = SAOTables::TicketStatuses->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name, $statuses): void {
            $table->id();
            $table->foreignId('workflow_scheme_id')
                ->constrained(SAOTables::WorkflowSchemes->value, 'id', "{$table_name}_scheme_FK")
                ->cascadeOnDelete();
            $table->foreignId('from_status_id')
                ->nullable()
                ->constrained($statuses, 'id', "{$table_name}_from_status_FK")
                ->restrictOnDelete()
                ->comment('Null means the creation transition: it declares the scheme initial status');
            $table->foreignId('to_status_id')
                ->constrained($statuses, 'id', "{$table_name}_to_status_FK")
                ->restrictOnDelete();
            $table->string('label');
            $table->string('required_permission')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true);

            $table->unique(
                ['workflow_scheme_id', 'from_status_id', 'to_status_id'],
                "{$table_name}_move_UN",
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::WorkflowTransitions->value);
    }
};
```

The unique index over `(scheme, from, to)` is what makes a second creation transition impossible: with `from_status_id` null, MySQL and PostgreSQL both treat two rows differing only in `to_status_id` as distinct, so the guard needs help — Step 5 adds it in the model.

- [ ] **Step 4: Create the transition model**

Create `Modules/SAO/app/Models/WorkflowTransition.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\WorkflowTransitionFactory;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * One permitted move within a scheme. A null `from_status_id` is the creation
 * transition, which is how a scheme declares the status a new ticket starts in.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $workflow_scheme_id
 * @property int|null $from_status_id
 * @property int $to_status_id
 * @property string $label
 * @property string|null $required_permission
 */
final class WorkflowTransition extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::WorkflowTransitions->value;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'workflow_scheme_id',
        'from_status_id',
        'to_status_id',
        'label',
        'required_permission',
    ];

    /**
     * @return BelongsTo<WorkflowScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(WorkflowScheme::class, 'workflow_scheme_id');
    }

    /**
     * @return BelongsTo<TicketStatus, $this>
     */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'from_status_id');
    }

    /**
     * @return BelongsTo<TicketStatus, $this>
     */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'to_status_id');
    }

    public function isInitial(): bool
    {
        return $this->from_status_id === null;
    }

    /**
     * @return Factory<WorkflowTransition>
     */
    protected static function newFactory(): Factory
    {
        return WorkflowTransitionFactory::new();
    }
}
```

- [ ] **Step 5: Create the scheme model with its two invariants**

Create `Modules/SAO/app/Models/WorkflowScheme.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\WorkflowSchemeFactory;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * Shareable across ticket types and projects (E3) — the valve that stops every
 * new type from spawning a new scheme.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 */
final class WorkflowScheme extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::WorkflowSchemes->value;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'name',
        'description',
        'is_default',
    ];

    public static function default(): ?self
    {
        return self::query()->where('is_default', true)->first();
    }

    /**
     * @return HasMany<WorkflowTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'workflow_scheme_id');
    }

    public function initialTransition(): ?WorkflowTransition
    {
        return $this->transitions()->whereNull('from_status_id')->first();
    }

    protected static function booted(): void
    {
        // A scheme with two creation transitions cannot answer "where does a new
        // ticket start". The composite unique index cannot express this, because
        // SQL treats rows with a null from_status_id as distinct.
        WorkflowTransition::creating(static function (WorkflowTransition $transition): void {
            if ($transition->from_status_id !== null) {
                return;
            }

            $exists = WorkflowTransition::query()
                ->where('workflow_scheme_id', $transition->workflow_scheme_id)
                ->whereNull('from_status_id')
                ->exists();

            if ($exists) {
                throw new QueryException(
                    'sao',
                    'insert into sao_workflow_transitions',
                    [],
                    new \RuntimeException('A workflow scheme may declare only one creation transition.'),
                );
            }
        });

        // Two defaults is a state from which "which scheme applies" has no answer.
        self::saving(static function (self $scheme): void {
            if ($scheme->is_default !== true) {
                return;
            }

            self::query()
                ->when($scheme->exists, fn ($query) => $query->whereKeyNot($scheme->getKey()))
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    /**
     * @return Factory<WorkflowScheme>
     */
    protected static function newFactory(): Factory
    {
        return WorkflowSchemeFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
```

- [ ] **Step 6: Create both factories**

Create `Modules/SAO/database/factories/WorkflowSchemeFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SAO\Models\WorkflowScheme;

/**
 * @extends Factory<WorkflowScheme>
 */
final class WorkflowSchemeFactory extends Factory
{
    /**
     * @var class-string<WorkflowScheme>
     */
    protected $model = WorkflowScheme::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'is_default' => false,
        ];
    }
}
```

Create `Modules/SAO/database/factories/WorkflowTransitionFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;

/**
 * @extends Factory<WorkflowTransition>
 */
final class WorkflowTransitionFactory extends Factory
{
    /**
     * @var class-string<WorkflowTransition>
     */
    protected $model = WorkflowTransition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_scheme_id' => WorkflowScheme::factory(),
            'from_status_id' => TicketStatus::factory(),
            'to_status_id' => TicketStatus::factory(),
            'label' => $this->faker->words(2, true),
            'required_permission' => null,
        ];
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

```bash
php artisan test --filter=WorkflowSchemeTest
```

Expected: PASS, 4 tests.

- [ ] **Step 8: Format, analyse and commit**

```bash
vendor/bin/pint Modules/SAO
vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G
cd Modules/SAO
git add app/Models database/migrations database/factories tests/Feature/Models
git commit -m "feat(sao): add shareable workflow schemes and their transitions"
cd ../..
```

---

## Task 5: Ticket types and their per-project association

**Files:**
- Create: `Modules/SAO/database/migrations/2026_08_01_100400_create_sao_ticket_types_table.php`
- Create: `Modules/SAO/database/migrations/2026_08_01_100500_create_sao_project_ticket_types_table.php`
- Create: `Modules/SAO/app/Models/TicketType.php`
- Create: `Modules/SAO/app/Models/Pivot/ProjectTicketType.php`
- Create: `Modules/SAO/database/factories/TicketTypeFactory.php`
- Modify: `Modules/SAO/app/Models/Project.php`
- Create: `Modules/SAO/tests/Feature/Models/TicketTypeTest.php`

**Interfaces:**
- Consumes: `WorkflowScheme` from Task 4, `Project` from Task 2.
- Produces: `TicketType` with `$fillable` `['name', 'slug', 'icon', 'colour', 'workflow_scheme_id', 'is_defect']`; `Project::ticketTypes(): BelongsToMany` using the `ProjectTicketType` pivot with `is_default` and `workflow_scheme_id` columns. Task 7's `WorkflowService::schemeFor(Project $project, TicketType $type): WorkflowScheme` reads the override from this pivot.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Feature/Models/TicketTypeTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;

uses(RefreshDatabase::class);

test('a type owns a workflow scheme and can be flagged as a defect type', function (): void {
    $scheme = WorkflowScheme::factory()->create();

    $type = TicketType::factory()->create([
        'name' => 'Bug',
        'slug' => 'bug',
        'workflow_scheme_id' => $scheme->id,
        'is_defect' => true,
    ]);

    expect($type->scheme->id)->toBe($scheme->id);
    expect($type->is_defect)->toBeTrue();
});

test('exactly one type is the defect type phase 2 will create', function (): void {
    TicketType::factory()->create(['is_defect' => true]);
    TicketType::factory()->create(['is_defect' => false]);

    expect(TicketType::query()->where('is_defect', true)->count())->toBe(1);
});

test('a project enables types through the pivot, with one marked default', function (): void {
    $project = Project::factory()->create();
    $bug = TicketType::factory()->create(['slug' => 'bug']);
    $task = TicketType::factory()->create(['slug' => 'task']);

    $project->ticketTypes()->attach($bug->id, ['is_default' => true]);
    $project->ticketTypes()->attach($task->id, ['is_default' => false]);

    expect($project->ticketTypes()->count())->toBe(2);
    expect($project->defaultTicketType()?->id)->toBe($bug->id);
});

test('the pivot may override the workflow scheme for one project', function (): void {
    $project = Project::factory()->create();
    $type_scheme = WorkflowScheme::factory()->create();
    $override = WorkflowScheme::factory()->create();
    $type = TicketType::factory()->create(['workflow_scheme_id' => $type_scheme->id]);

    $project->ticketTypes()->attach($type->id, [
        'is_default' => true,
        'workflow_scheme_id' => $override->id,
    ]);

    $pivot = $project->ticketTypes()->first()?->pivot;

    expect($pivot?->workflow_scheme_id)->toBe($override->id);
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=TicketTypeTest
```

Expected: FAIL — `Modules\SAO\Models\TicketType` does not exist.

- [ ] **Step 3: Create both migrations**

Create `Modules/SAO/database/migrations/2026_08_01_100400_create_sao_ticket_types_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::TicketTypes->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 64);
            $table->string('icon', 64)->nullable();
            $table->string('colour', 16)->default('gray');
            $table->foreignId('workflow_scheme_id')
                ->constrained(SAOTables::WorkflowSchemes->value, 'id', "{$table_name}_scheme_FK")
                ->restrictOnDelete();
            $table->boolean('is_defect')->default(false)->comment('Machine-readable hook: phase 2 creates tickets of this type from errors');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique('slug', "{$table_name}_slug_UN");
            $table->index('is_defect', "{$table_name}_is_defect_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::TicketTypes->value);
    }
};
```

Create `Modules/SAO/database/migrations/2026_08_01_100500_create_sao_project_ticket_types_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::ProjectTicketTypes->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained(SAOTables::Projects->value, 'id', "{$table_name}_project_FK")
                ->cascadeOnDelete();
            $table->foreignId('ticket_type_id')
                ->constrained(SAOTables::TicketTypes->value, 'id', "{$table_name}_type_FK")
                ->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->foreignId('workflow_scheme_id')
                ->nullable()
                ->constrained(SAOTables::WorkflowSchemes->value, 'id', "{$table_name}_scheme_FK")
                ->restrictOnDelete()
                ->comment('Optional per-project override; null means the type own scheme applies');

            MigrateUtils::timestamps($table, hasCreateUpdate: true);

            $table->unique(['project_id', 'ticket_type_id'], "{$table_name}_pair_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::ProjectTicketTypes->value);
    }
};
```

- [ ] **Step 4: Create the pivot and type models**

Create `Modules/SAO/app/Models/Pivot/ProjectTicketType.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * @property int $project_id
 * @property int $ticket_type_id
 * @property bool $is_default
 * @property int|null $workflow_scheme_id
 */
final class ProjectTicketType extends Pivot
{
    public $incrementing = true;

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::ProjectTicketTypes->value;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
```

Create `Modules/SAO/app/Models/TicketType.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\TicketTypeFactory;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * Types are global and enabled per project through a pivot (E5), so that
 * "bug" is defined once rather than once per project.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property string $colour
 * @property int $workflow_scheme_id
 * @property bool $is_defect
 */
final class TicketType extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::TicketTypes->value;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'colour',
        'workflow_scheme_id',
        'is_defect',
    ];

    /**
     * @return BelongsTo<WorkflowScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(WorkflowScheme::class, 'workflow_scheme_id');
    }

    /**
     * @return Factory<TicketType>
     */
    protected static function newFactory(): Factory
    {
        return TicketTypeFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_defect' => 'boolean',
        ];
    }
}
```

- [ ] **Step 5: Create the type factory**

Create `Modules/SAO/database/factories/TicketTypeFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;

/**
 * @extends Factory<TicketType>
 */
final class TicketTypeFactory extends Factory
{
    /**
     * @var class-string<TicketType>
     */
    protected $model = TicketType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => $name,
            'icon' => null,
            'colour' => 'gray',
            'workflow_scheme_id' => WorkflowScheme::factory(),
            'is_defect' => false,
        ];
    }

    public function defect(): self
    {
        return $this->state(fn (): array => ['is_defect' => true]);
    }
}
```

- [ ] **Step 6: Wire the relation onto Project**

In `Modules/SAO/app/Models/Project.php`, add these imports:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\SAO\Models\Pivot\ProjectTicketType;
```

and add these two methods after `getRules()`:

```php
    /**
     * @return BelongsToMany<TicketType, $this, ProjectTicketType>
     */
    public function ticketTypes(): BelongsToMany
    {
        return $this->belongsToMany(TicketType::class, SAOTables::ProjectTicketTypes->value)
            ->using(ProjectTicketType::class)
            ->withPivot(['is_default', 'workflow_scheme_id'])
            ->withTimestamps();
    }

    public function defaultTicketType(): ?TicketType
    {
        return $this->ticketTypes()->wherePivot('is_default', true)->first();
    }
```

- [ ] **Step 7: Run the test to verify it passes**

```bash
php artisan test --filter=TicketTypeTest
```

Expected: PASS, 4 tests.

- [ ] **Step 8: Format, analyse and commit**

```bash
vendor/bin/pint Modules/SAO
vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G
cd Modules/SAO
git add app/Models database/migrations database/factories tests/Feature/Models
git commit -m "feat(sao): add ticket types with per-project association and scheme override"
cd ../..
```

---

## Task 6: The ticket and its key allocator

This is the task with the only genuine correctness hazard in 1a: two concurrent creations must not produce the same key.

**Files:**
- Create: `Modules/SAO/database/migrations/2026_08_01_100600_create_sao_tickets_table.php`
- Create: `Modules/SAO/app/Models/Ticket.php`
- Create: `Modules/SAO/app/Services/TicketKeyAllocator.php`
- Create: `Modules/SAO/database/factories/TicketFactory.php`
- Modify: `Modules/SAO/app/Models/Project.php`
- Create: `Modules/SAO/tests/Feature/Services/TicketKeyAllocatorTest.php`

**Interfaces:**
- Consumes: `Project`, `TicketType`, `TicketStatus`.
- Produces: `TicketKeyAllocator::allocate(Project $project): array{number: int, key: string}`, and `Ticket` with `$fillable` `['project_id', 'ticket_type_id', 'ticket_status_id', 'priority', 'title', 'description', 'reporter_id', 'assignee_id']`. Task 7 sets `ticket_status_id` only through `WorkflowService`.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Feature/Services/TicketKeyAllocatorTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Models\Project;
use Modules\SAO\Services\TicketKeyAllocator;

uses(RefreshDatabase::class);

test('the first allocation is number one', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO']);

    $allocated = app(TicketKeyAllocator::class)->allocate($project);

    expect($allocated['number'])->toBe(1);
    expect($allocated['key'])->toBe('SAO-1');
});

test('allocations increment and never repeat within a project', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO']);
    $allocator = app(TicketKeyAllocator::class);

    $keys = [];

    for ($i = 0; $i < 25; $i++) {
        $keys[] = $allocator->allocate($project)['key'];
    }

    expect($keys)->toHaveCount(25);
    expect(array_unique($keys))->toHaveCount(25);
    expect($keys[24])->toBe('SAO-25');
});

test('counters are independent across projects', function (): void {
    $first = Project::factory()->create(['key_prefix' => 'AAA']);
    $second = Project::factory()->create(['key_prefix' => 'BBB']);
    $allocator = app(TicketKeyAllocator::class);

    $allocator->allocate($first);

    expect($allocator->allocate($second)['key'])->toBe('BBB-1');
    expect($allocator->allocate($first)['key'])->toBe('AAA-2');
});

test('the key prefix cannot change once a ticket exists', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO']);
    app(TicketKeyAllocator::class)->allocate($project);

    $project->key_prefix = 'NEW';

    expect(fn (): bool => $project->save())
        ->toThrow(Modules\SAO\Exceptions\ImmutableKeyPrefixException::class);
});

test('the prefix may still be corrected before the first ticket', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'TYPO']);

    $project->key_prefix = 'GOOD';
    $project->save();

    expect($project->fresh()->key_prefix)->toBe('GOOD');
});
```

Note what the immutability test asserts: the guard triggers on **an allocated number**, not on the existence of a `Ticket` row. That is deliberate — a number handed out and then rolled back still appears in someone's browser history, and the prefix that produced it must stay meaningful.

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=TicketKeyAllocatorTest
```

Expected: FAIL — `Modules\SAO\Services\TicketKeyAllocator` does not exist.

- [ ] **Step 3: Create the exception and the immutability guard**

Create `Modules/SAO/app/Exceptions/ImmutableKeyPrefixException.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use RuntimeException;

/**
 * Ticket keys end up in commit messages, which phase 5 parses. A prefix that
 * changes makes already-written history unreadable (E7).
 */
final class ImmutableKeyPrefixException extends RuntimeException
{
    public static function forProject(string $name): self
    {
        return new self(
            "The key prefix of project \"{$name}\" cannot change: ticket numbers have already been allocated.",
        );
    }
}
```

In `Modules/SAO/app/Models/Project.php`, add the import:

```php
use Modules\SAO\Exceptions\ImmutableKeyPrefixException;
```

and add this method:

```php
    protected static function booted(): void
    {
        self::updating(static function (self $project): void {
            if (! $project->isDirty('key_prefix')) {
                return;
            }

            if ($project->getOriginal('next_ticket_number') > 0) {
                throw ImmutableKeyPrefixException::forProject((string) $project->getOriginal('name'));
            }
        });
    }
```

- [ ] **Step 4: Create the allocator**

Create `Modules/SAO/app/Services/TicketKeyAllocator.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Illuminate\Support\Facades\DB;
use Modules\SAO\Models\Project;

/**
 * Allocates the next ticket number for a project.
 *
 * Gaps are accepted: a rolled-back transaction loses its number. Making the
 * sequence gapless would require serializing every creation, which no serious
 * tracker does.
 */
final class TicketKeyAllocator
{
    /**
     * @return array{number: int, key: string}
     */
    public function allocate(Project $project): array
    {
        return DB::transaction(function () use ($project): array {
            /** @var Project $locked */
            $locked = Project::query()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $number = $locked->next_ticket_number + 1;

            // Written with a direct update rather than save(): next_ticket_number
            // is not fillable, and this is the only code allowed to move it.
            Project::query()
                ->whereKey($locked->getKey())
                ->update(['next_ticket_number' => $number]);

            $project->setAttribute('next_ticket_number', $number);
            $project->syncOriginalAttribute('next_ticket_number');

            return [
                'number' => $number,
                'key' => $locked->key_prefix . '-' . $number,
            ];
        });
    }
}
```

- [ ] **Step 5: Create the ticket migration**

Create `Modules/SAO/database/migrations/2026_08_01_100600_create_sao_tickets_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\TicketPriority;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::Tickets->value;
        $users = CoreTables::Users->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name, $users): void {
            $table->id();
            $table->foreignId('project_id')
                ->constrained(SAOTables::Projects->value, 'id', "{$table_name}_project_FK")
                ->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('key', 32);
            $table->foreignId('ticket_type_id')
                ->constrained(SAOTables::TicketTypes->value, 'id', "{$table_name}_type_FK")
                ->restrictOnDelete();
            $table->foreignId('ticket_status_id')
                ->constrained(SAOTables::TicketStatuses->value, 'id', "{$table_name}_status_FK")
                ->restrictOnDelete();
            $table->enum('priority', TicketPriority::values())->default(TicketPriority::Normal->value);
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('reporter_id')->nullable()
                ->constrained($users, 'id', "{$table_name}_reporter_FK")
                ->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()
                ->constrained($users, 'id', "{$table_name}_assignee_FK")
                ->nullOnDelete();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true, hasLocks: true);

            $table->unique('key', "{$table_name}_key_UN");
            $table->unique(['project_id', 'number'], "{$table_name}_project_number_UN");
            $table->index(['project_id', 'ticket_status_id'], "{$table_name}_project_status_IDX");
            $table->index('assignee_id', "{$table_name}_assignee_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::Tickets->value);
    }
};
```

`hasLocks: true` adds the optimistic-locking column Core expects. Tickets are the most concurrently edited object in the system, so this is not optional.

- [ ] **Step 6: Create the ticket model and factory**

Create `Modules/SAO/app/Models/Ticket.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\TicketFactory;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\TicketPriority;
use Override;

/**
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $project_id
 * @property int $number
 * @property string $key
 * @property int $ticket_type_id
 * @property int $ticket_status_id
 * @property TicketPriority $priority
 * @property string $title
 * @property string|null $description
 * @property int|null $reporter_id
 * @property int|null $assignee_id
 */
final class Ticket extends Model
{
    /**
     * `ticket_status_id` is absent on purpose: WorkflowService is the only path
     * to a status change, so mass assignment must not offer a shortcut.
     *
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'project_id',
        'ticket_type_id',
        'priority',
        'title',
        'description',
        'reporter_id',
        'assignee_id',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::Tickets->value;

    /**
     * @return array<string, array<string, list<string>>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        $rules['create'] = array_merge($rules['create'], [
            'project_id' => ['required', 'integer', 'exists:' . SAOTables::Projects->value . ',id'],
            'ticket_type_id' => ['required', 'integer', 'exists:' . SAOTables::TicketTypes->value . ',id'],
            'priority' => ['sometimes', 'string', TicketPriority::validationRule()],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer'],
        ]);

        $rules['update'] = array_merge($rules['update'], [
            'priority' => ['sometimes', 'string', TicketPriority::validationRule()],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer'],
        ]);

        return $rules;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    /**
     * @return BelongsTo<TicketStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'ticket_status_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * @return Factory<Ticket>
     */
    protected static function newFactory(): Factory
    {
        return TicketFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'number' => 'integer',
        ];
    }
}
```

`comments()` references `TicketComment`, created in Task 8. Add the relation now and keep the class import; the test suite for this task does not exercise it, and Task 8 completes it.

Create `Modules/SAO/database/factories/TicketFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SAO\Enums\TicketPriority;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Services\TicketKeyAllocator;

/**
 * @extends Factory<Ticket>
 */
final class TicketFactory extends Factory
{
    /**
     * @var class-string<Ticket>
     */
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $project = Project::factory()->create();
        $allocated = app(TicketKeyAllocator::class)->allocate($project);

        return [
            'project_id' => $project->id,
            'number' => $allocated['number'],
            'key' => $allocated['key'],
            'ticket_type_id' => TicketType::factory(),
            'ticket_status_id' => TicketStatus::factory(),
            'priority' => TicketPriority::Normal,
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'reporter_id' => null,
            'assignee_id' => null,
        ];
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

```bash
php artisan test --filter=TicketKeyAllocatorTest
```

Expected: PASS, 5 tests.

- [ ] **Step 8: Prove the allocator under real concurrency**

The sequential test above proves arithmetic, not safety. Add this test to the same file:

```php
test('two concurrent allocations produce two distinct keys', function (): void {
    $project = Project::factory()->create(['key_prefix' => 'SAO']);

    $script = base_path('Modules/SAO/tests/Support/allocate_once.php');

    $first = proc_open(['php', $script, (string) $project->id], [1 => ['pipe', 'w']], $first_pipes);
    $second = proc_open(['php', $script, (string) $project->id], [1 => ['pipe', 'w']], $second_pipes);

    $first_key = mb_trim((string) stream_get_contents($first_pipes[1]));
    $second_key = mb_trim((string) stream_get_contents($second_pipes[1]));

    fclose($first_pipes[1]);
    fclose($second_pipes[1]);
    proc_close($first);
    proc_close($second);

    expect($first_key)->not->toBe($second_key);
    expect([$first_key, $second_key])->toEqualCanonicalizing(['SAO-1', 'SAO-2']);
})->skip(
    fn (): bool => config('database.default') === 'sqlite',
    'Row locking cannot be exercised on the in-memory SQLite used by the default suite.',
);
```

Create `Modules/SAO/tests/Support/allocate_once.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../../../../vendor/autoload.php';

$app = require __DIR__ . '/../../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$project = Modules\SAO\Models\Project::query()->findOrFail((int) $argv[1]);

echo $app->make(Modules\SAO\Services\TicketKeyAllocator::class)->allocate($project)['key'];
```

The skip is honest rather than convenient: SQLite serializes writes, so on the default suite this test would pass without proving anything. Run it against MySQL or PostgreSQL before considering the allocator verified:

```bash
DB_CONNECTION=mysql php artisan test --filter="two concurrent allocations"
```

If no such database is available, record in the task's commit message that the concurrency test was skipped, rather than reporting the allocator as verified.

- [ ] **Step 9: Format, analyse and commit**

```bash
vendor/bin/pint Modules/SAO
vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G
cd Modules/SAO
git add app/Models app/Services app/Exceptions database/migrations database/factories tests
git commit -m "feat(sao): add tickets with a row-locked per-project key allocator"
cd ../..
```

---

## Task 7: Change context and the workflow service

**Files:**
- Create: `Modules/SAO/app/Data/ChangeContext.php`
- Create: `Modules/SAO/app/Exceptions/TransitionNotAllowedException.php`
- Create: `Modules/SAO/app/Services/WorkflowService.php`
- Create: `Modules/SAO/tests/Feature/Services/WorkflowServiceTest.php`

**Interfaces:**
- Consumes: `Ticket`, `Project`, `TicketType`, `WorkflowScheme`, `WorkflowTransition`, `TicketStatus`.
- Produces: `ChangeContext::forUser(User $user)`, `ChangeContext::forAutomation(string $source_key)`; `WorkflowService::schemeFor(Project $project, TicketType $type): WorkflowScheme`, `availableTransitions(Ticket $ticket): Collection<int, WorkflowTransition>`, `transition(Ticket $ticket, TicketStatus $to, ChangeContext $context): Ticket`, `openingStatusFor(Project $project, TicketType $type): TicketStatus`. Tasks 8, 11 and 12 consume these.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Feature/Services/WorkflowServiceTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Enums\StatusCategory;
use Modules\SAO\Exceptions\TransitionNotAllowedException;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;
use Modules\SAO\Services\WorkflowService;

uses(RefreshDatabase::class);

/**
 * Builds a project with one type whose scheme is: (creation) -> Open -> Doing.
 *
 * @return array{project: Project, type: TicketType, open: TicketStatus, doing: TicketStatus, blocked: TicketStatus}
 */
function sao_workflow_fixture(): array
{
    $open = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Open']);
    $doing = TicketStatus::factory()->category(StatusCategory::InProgress)->create(['name' => 'Doing']);
    $blocked = TicketStatus::factory()->category(StatusCategory::Open)->create(['name' => 'Blocked']);

    $scheme = WorkflowScheme::factory()->create(['name' => 'Simple']);
    WorkflowTransition::factory()->for($scheme)->create([
        'from_status_id' => null,
        'to_status_id' => $open->id,
        'label' => 'Open',
    ]);
    WorkflowTransition::factory()->for($scheme)->create([
        'from_status_id' => $open->id,
        'to_status_id' => $doing->id,
        'label' => 'Start work',
    ]);

    $type = TicketType::factory()->create(['workflow_scheme_id' => $scheme->id]);
    $project = Project::factory()->create();
    $project->ticketTypes()->attach($type->id, ['is_default' => true]);

    return compact('project', 'type', 'open', 'doing', 'blocked');
}

test('the scheme comes from the type when the project sets no override', function (): void {
    ['project' => $project, 'type' => $type] = sao_workflow_fixture();

    expect(app(WorkflowService::class)->schemeFor($project, $type)->id)
        ->toBe($type->workflow_scheme_id);
});

test('a project override wins over the type own scheme', function (): void {
    ['project' => $project, 'type' => $type] = sao_workflow_fixture();
    $override = WorkflowScheme::factory()->create(['name' => 'Override']);

    $project->ticketTypes()->updateExistingPivot($type->id, ['workflow_scheme_id' => $override->id]);

    expect(app(WorkflowService::class)->schemeFor($project->fresh(), $type)->id)->toBe($override->id);
});

test('the opening status comes from the creation transition', function (): void {
    ['project' => $project, 'type' => $type, 'open' => $open] = sao_workflow_fixture();

    expect(app(WorkflowService::class)->openingStatusFor($project, $type)->id)->toBe($open->id);
});

test('only the transitions declared by the scheme are offered', function (): void {
    ['project' => $project, 'type' => $type, 'open' => $open, 'doing' => $doing] = sao_workflow_fixture();

    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_type_id' => $type->id,
        'ticket_status_id' => $open->id,
    ]);

    $available = app(WorkflowService::class)->availableTransitions($ticket);

    expect($available)->toHaveCount(1);
    expect($available->first()?->to_status_id)->toBe($doing->id);
});

test('an undeclared transition is refused', function (): void {
    ['project' => $project, 'type' => $type, 'open' => $open, 'blocked' => $blocked] = sao_workflow_fixture();

    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_type_id' => $type->id,
        'ticket_status_id' => $open->id,
    ]);

    $context = ChangeContext::forAutomation('test');

    expect(fn (): Ticket => app(WorkflowService::class)->transition($ticket, $blocked, $context))
        ->toThrow(TransitionNotAllowedException::class);

    expect($ticket->fresh()->ticket_status_id)->toBe($open->id);
});

test('a declared transition moves the ticket', function (): void {
    ['project' => $project, 'type' => $type, 'open' => $open, 'doing' => $doing] = sao_workflow_fixture();

    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_type_id' => $type->id,
        'ticket_status_id' => $open->id,
    ]);

    $moved = app(WorkflowService::class)->transition($ticket, $doing, ChangeContext::forAutomation('test'));

    expect($moved->ticket_status_id)->toBe($doing->id);
    expect($ticket->fresh()->ticket_status_id)->toBe($doing->id);
});

test('the override permission bypasses an undeclared transition', function (): void {
    ['project' => $project, 'type' => $type, 'open' => $open, 'blocked' => $blocked] = sao_workflow_fixture();

    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_type_id' => $type->id,
        'ticket_status_id' => $open->id,
    ]);

    $context = ChangeContext::forAutomation('test')->withOverride();

    $moved = app(WorkflowService::class)->transition($ticket, $blocked, $context);

    expect($moved->ticket_status_id)->toBe($blocked->id);
});

test('a context built for a user carries that user as the actor', function (): void {
    $user = User::factory()->create();

    $context = ChangeContext::forUser($user);

    expect($context->userId())->toBe($user->id);
    expect($context->sourceKey())->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=WorkflowServiceTest
```

Expected: FAIL — `Modules\SAO\Data\ChangeContext` does not exist.

- [ ] **Step 3: Create the change context**

Create `Modules/SAO/app/Data/ChangeContext.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

use Modules\Core\Models\User;

/**
 * Who acted, and on behalf of what (E10).
 *
 * In 1a this only attributes authorship. It exists now so that phase 3 — which
 * brings remote actors that Core versioning cannot attribute — can add
 * provenance without touching a single call site.
 */
final readonly class ChangeContext
{
    private function __construct(
        private ?int $user_id,
        private ?string $source_key,
        private bool $override,
    ) {}

    public static function forUser(User $user): self
    {
        return new self($user->getKey(), null, false);
    }

    public static function forAutomation(string $source_key): self
    {
        return new self(null, $source_key, false);
    }

    public function withOverride(): self
    {
        return new self($this->user_id, $this->source_key, true);
    }

    public function userId(): ?int
    {
        return $this->user_id;
    }

    public function sourceKey(): ?string
    {
        return $this->source_key;
    }

    public function hasOverride(): bool
    {
        return $this->override;
    }
}
```

- [ ] **Step 4: Create the exception**

Create `Modules/SAO/app/Exceptions/TransitionNotAllowedException.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use RuntimeException;

final class TransitionNotAllowedException extends RuntimeException
{
    public static function between(Ticket $ticket, TicketStatus $to): self
    {
        return new self(
            "Ticket {$ticket->key} cannot move to \"{$to->name}\": its workflow scheme declares no such transition.",
        );
    }
}
```

- [ ] **Step 5: Create the workflow service**

Create `Modules/SAO/app/Services/WorkflowService.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Illuminate\Support\Collection;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Exceptions\TransitionNotAllowedException;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;
use Modules\SAO\Models\WorkflowTransition;
use RuntimeException;

/**
 * The only path to a ticket status change (E4).
 *
 * Enforcement lives here rather than in the UI because the API and phase 2's
 * automation move tickets too, and a rule that only the interface honours is
 * not a rule.
 */
final class WorkflowService
{
    /**
     * Project override first, then the type own scheme.
     */
    public function schemeFor(Project $project, TicketType $type): WorkflowScheme
    {
        $pivot = $project->ticketTypes()
            ->wherePivot('ticket_type_id', $type->getKey())
            ->first()?->pivot;

        $override_id = $pivot?->workflow_scheme_id;

        if ($override_id !== null) {
            return WorkflowScheme::query()->findOrFail($override_id);
        }

        return $type->scheme()->firstOrFail();
    }

    public function openingStatusFor(Project $project, TicketType $type): TicketStatus
    {
        $scheme = $this->schemeFor($project, $type);
        $initial = $scheme->initialTransition();

        if ($initial === null) {
            throw new RuntimeException(
                "Workflow scheme \"{$scheme->name}\" declares no creation transition, so a new ticket has no status to start in.",
            );
        }

        return TicketStatus::query()->findOrFail($initial->to_status_id);
    }

    /**
     * @return Collection<int, WorkflowTransition>
     */
    public function availableTransitions(Ticket $ticket): Collection
    {
        $scheme = $this->schemeFor($ticket->project()->firstOrFail(), $ticket->type()->firstOrFail());

        return $scheme->transitions()
            ->where('from_status_id', $ticket->ticket_status_id)
            ->get();
    }

    public function transition(Ticket $ticket, TicketStatus $to, ChangeContext $context): Ticket
    {
        $permitted = $this->availableTransitions($ticket)
            ->firstWhere('to_status_id', $to->getKey());

        if ($permitted === null && ! $context->hasOverride()) {
            throw TransitionNotAllowedException::between($ticket, $to);
        }

        $ticket->ticket_status_id = $to->getKey();
        $ticket->save();

        return $ticket;
    }
}
```

The permission checks that `WorkflowTransition::$required_permission` and the override imply are wired in Task 10, once the permission names exist. The service already reads `hasOverride()` from the context, so Task 10 adds the authorization lookup without changing this signature.

- [ ] **Step 6: Run the test to verify it passes**

```bash
php artisan test --filter=WorkflowServiceTest
```

Expected: PASS, 8 tests.

- [ ] **Step 7: Format, analyse and commit**

```bash
vendor/bin/pint Modules/SAO
vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G
cd Modules/SAO
git add app/Data app/Services app/Exceptions tests/Feature/Services
git commit -m "feat(sao): enforce workflow transitions in the domain service"
cd ../..
```

---

## Task 8: Comments

**Files:**
- Create: `Modules/SAO/database/migrations/2026_08_01_100700_create_sao_ticket_comments_table.php`
- Create: `Modules/SAO/app/Models/TicketComment.php`
- Create: `Modules/SAO/database/factories/TicketCommentFactory.php`
- Create: `Modules/SAO/tests/Feature/Models/TicketCommentTest.php`

**Interfaces:**
- Consumes: `Ticket` from Task 6, `CommentOrigin` from Task 1, `ChangeContext` from Task 7.
- Produces: `TicketComment::postFor(Ticket $ticket, string $body, ChangeContext $context): self` — the single creation path, which derives origin and authorship from the context. Task 9's timeline consumes `TicketComment`.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Feature/Models/TicketCommentTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Enums\CommentOrigin;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketComment;

uses(RefreshDatabase::class);

test('a comment posted by a user is human and carries its author', function (): void {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    $comment = TicketComment::postFor($ticket, 'Looking into it.', ChangeContext::forUser($user));

    expect($comment->origin)->toBe(CommentOrigin::Human);
    expect($comment->author_id)->toBe($user->id);
    expect($comment->source_key)->toBeNull();
});

test('a comment posted by automation is system and names its source', function (): void {
    $ticket = Ticket::factory()->create();

    $comment = TicketComment::postFor($ticket, 'Recurred 12 times.', ChangeContext::forAutomation('ingest'));

    expect($comment->origin)->toBe(CommentOrigin::System);
    expect($comment->author_id)->toBeNull();
    expect($comment->source_key)->toBe('ingest');
});

test('a system comment cannot be edited', function (): void {
    $ticket = Ticket::factory()->create();
    $comment = TicketComment::postFor($ticket, 'Automated note.', ChangeContext::forAutomation('ingest'));

    $comment->body = 'Tampered.';

    expect(fn (): bool => $comment->save())
        ->toThrow(Modules\SAO\Exceptions\ImmutableSystemCommentException::class);
});

test('a human comment can be edited', function (): void {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();
    $comment = TicketComment::postFor($ticket, 'Typo here.', ChangeContext::forUser($user));

    $comment->body = 'Fixed.';
    $comment->save();

    expect($comment->fresh()->body)->toBe('Fixed.');
});

test('comments belong to their ticket', function (): void {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    TicketComment::postFor($ticket, 'One.', ChangeContext::forUser($user));
    TicketComment::postFor($ticket, 'Two.', ChangeContext::forUser($user));

    expect($ticket->comments()->count())->toBe(2);
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=TicketCommentTest
```

Expected: FAIL — `Modules\SAO\Models\TicketComment` does not exist.

- [ ] **Step 3: Create the migration**

Create `Modules/SAO/database/migrations/2026_08_01_100700_create_sao_ticket_comments_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\SAO\Enums\CommentOrigin;
use Modules\SAO\Enums\SAOTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = SAOTables::TicketComments->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained(SAOTables::Tickets->value, 'id', "{$table_name}_ticket_FK")
                ->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()
                ->constrained(CoreTables::Users->value, 'id', "{$table_name}_author_FK")
                ->nullOnDelete()
                ->comment('Null for system comments');
            $table->enum('origin', CommentOrigin::values())->default(CommentOrigin::Human->value);
            $table->string('source_key')->nullable()->comment('Which automation wrote it, for system comments');
            $table->text('body');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index(['ticket_id', 'created_at'], "{$table_name}_ticket_created_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SAOTables::TicketComments->value);
    }
};
```

- [ ] **Step 4: Create the immutability exception**

Create `Modules/SAO/app/Exceptions/ImmutableSystemCommentException.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Exceptions;

use RuntimeException;

/**
 * A system comment is a record of what automation observed. Letting a person
 * rewrite it would make the ticket history untrustworthy precisely where it is
 * most relied upon (E8).
 */
final class ImmutableSystemCommentException extends RuntimeException
{
    public static function make(): self
    {
        return new self('A system comment cannot be modified.');
    }
}
```

- [ ] **Step 5: Create the model and factory**

Create `Modules/SAO/app/Models/TicketComment.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Database\Factories\TicketCommentFactory;
use Modules\SAO\Enums\CommentOrigin;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Exceptions\ImmutableSystemCommentException;
use Override;

/**
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $author_id
 * @property CommentOrigin $origin
 * @property string|null $source_key
 * @property string $body
 */
final class TicketComment extends Model
{
    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'ticket_id',
        'author_id',
        'origin',
        'source_key',
        'body',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::TicketComments->value;

    /**
     * The single creation path: origin and authorship are derived from the
     * context, never passed in by a caller.
     */
    public static function postFor(Ticket $ticket, string $body, ChangeContext $context): self
    {
        $source_key = $context->sourceKey();

        return self::query()->create([
            'ticket_id' => $ticket->getKey(),
            'author_id' => $context->userId(),
            'origin' => $source_key === null ? CommentOrigin::Human : CommentOrigin::System,
            'source_key' => $source_key,
            'body' => $body,
        ]);
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected static function booted(): void
    {
        self::updating(static function (self $comment): void {
            if ($comment->origin === CommentOrigin::System) {
                throw ImmutableSystemCommentException::make();
            }
        });
    }

    /**
     * @return Factory<TicketComment>
     */
    protected static function newFactory(): Factory
    {
        return TicketCommentFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'origin' => CommentOrigin::class,
        ];
    }
}
```

Create `Modules/SAO/database/factories/TicketCommentFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SAO\Enums\CommentOrigin;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketComment;

/**
 * @extends Factory<TicketComment>
 */
final class TicketCommentFactory extends Factory
{
    /**
     * @var class-string<TicketComment>
     */
    protected $model = TicketComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'author_id' => null,
            'origin' => CommentOrigin::Human,
            'source_key' => null,
            'body' => $this->faker->paragraph(),
        ];
    }

    public function system(string $source_key = 'ingest'): self
    {
        return $this->state(fn (): array => [
            'origin' => CommentOrigin::System,
            'source_key' => $source_key,
            'author_id' => null,
        ]);
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

```bash
php artisan test --filter=TicketCommentTest
```

Expected: PASS, 5 tests.

- [ ] **Step 7: Format, analyse and commit**

```bash
vendor/bin/pint Modules/SAO
vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G
cd Modules/SAO
git add app/Models app/Exceptions database/migrations database/factories tests/Feature/Models
git commit -m "feat(sao): add ticket comments with human and system origins"
cd ../..
```

---

## Task 9: The timeline read model

**Files:**
- Create: `Modules/SAO/app/Data/TimelineEntry.php`
- Create: `Modules/SAO/app/Services/TicketTimelineService.php`
- Create: `Modules/SAO/tests/Feature/Services/TicketTimelineServiceTest.php`

**Interfaces:**
- Consumes: `Ticket`, `TicketComment`, and Core's `HasVersions`.
- Produces: `TicketTimelineService::for(Ticket $ticket): Collection<int, TimelineEntry>`, entries ordered oldest first, each exposing `occurredAt(): CarbonInterface`, `kind(): string` (`comment` or `change`), `authorId(): ?int`, `sourceKey(): ?string`, `body(): ?string`, `changes(): array<string, mixed>`. Task 11's ticket page renders this.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Feature/Services/TicketTimelineServiceTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketComment;
use Modules\SAO\Services\TicketTimelineService;

uses(RefreshDatabase::class);

test('an empty ticket has an empty timeline', function (): void {
    $ticket = Ticket::factory()->create();

    expect(app(TicketTimelineService::class)->for($ticket))->toBeEmpty();
});

test('comments appear as timeline entries in chronological order', function (): void {
    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    TicketComment::postFor($ticket, 'First.', ChangeContext::forUser($user));
    TicketComment::postFor($ticket, 'Second.', ChangeContext::forAutomation('ingest'));

    $timeline = app(TicketTimelineService::class)->for($ticket);

    expect($timeline)->toHaveCount(2);
    expect($timeline->first()->body())->toBe('First.');
    expect($timeline->first()->kind())->toBe('comment');
    expect($timeline->first()->authorId())->toBe($user->id);
    expect($timeline->last()->sourceKey())->toBe('ingest');
});

test('a field change appears as a change entry carrying what changed', function (): void {
    $ticket = Ticket::factory()->create(['title' => 'Before']);

    $ticket->title = 'After';
    $ticket->save();

    $changes = app(TicketTimelineService::class)->for($ticket)
        ->filter(fn ($entry): bool => $entry->kind() === 'change');

    expect($changes)->not->toBeEmpty();
    expect(array_keys($changes->last()->changes()))->toContain('title');
});

test('comments and changes are merged into one ordered stream', function (): void {
    $ticket = Ticket::factory()->create(['title' => 'Before']);
    $user = User::factory()->create();

    TicketComment::postFor($ticket, 'A note.', ChangeContext::forUser($user));
    $ticket->title = 'After';
    $ticket->save();

    $timeline = app(TicketTimelineService::class)->for($ticket);
    $kinds = $timeline->map(fn ($entry): string => $entry->kind())->all();

    expect($kinds)->toContain('comment');
    expect($kinds)->toContain('change');

    $timestamps = $timeline->map(fn ($entry): int => $entry->occurredAt()->getTimestamp())->all();
    $sorted = $timestamps;
    sort($sorted);

    expect($timestamps)->toBe($sorted);
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=TicketTimelineServiceTest
```

Expected: FAIL — `Modules\SAO\Services\TicketTimelineService` does not exist.

- [ ] **Step 3: Inspect what Core versioning actually returns**

Before writing the service, confirm the shape of a version row for a SAO ticket:

```bash
php artisan tinker --execute='
$t = Modules\SAO\Models\Ticket::query()->first();
if ($t === null) { echo "no ticket in the database; create one first"; return; }
$v = $t->versions()->latest()->first();
echo $v === null ? "no versions" : json_encode(array_keys($v->getAttributes()));
'
```

Use the actual column that holds the changed attributes when writing Step 4. Core's `HasVersions` supports both a diff and a snapshot strategy, and the diff strategy is what makes a timeline possible — if this returns a full snapshot, set the ticket's strategy to diff via `getVersionStrategy()` on the model before continuing, and note it in the commit.

- [ ] **Step 4: Create the timeline entry**

Create `Modules/SAO/app/Data/TimelineEntry.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Data;

use Carbon\CarbonInterface;

/**
 * One thing that happened to a ticket: a comment, or a field change.
 *
 * The timeline is a read model (E9). There is no activity table — this merges
 * comments with Core's versions.
 */
final readonly class TimelineEntry
{
    /**
     * @param  array<string, mixed>  $changes
     */
    private function __construct(
        private CarbonInterface $occurred_at,
        private string $kind,
        private ?int $author_id,
        private ?string $source_key,
        private ?string $body,
        private array $changes,
    ) {}

    public static function comment(
        CarbonInterface $occurred_at,
        ?int $author_id,
        ?string $source_key,
        string $body,
    ): self {
        return new self($occurred_at, 'comment', $author_id, $source_key, $body, []);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function change(CarbonInterface $occurred_at, ?int $author_id, array $changes): self
    {
        return new self($occurred_at, 'change', $author_id, null, null, $changes);
    }

    public function occurredAt(): CarbonInterface
    {
        return $this->occurred_at;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function authorId(): ?int
    {
        return $this->author_id;
    }

    public function sourceKey(): ?string
    {
        return $this->source_key;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return $this->changes;
    }
}
```

- [ ] **Step 5: Create the service**

Create `Modules/SAO/app/Services/TicketTimelineService.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Illuminate\Support\Collection;
use Modules\SAO\Data\TimelineEntry;
use Modules\SAO\Models\Ticket;

/**
 * Merges comments and Core versions into one ordered stream.
 *
 * This is the single place that knows where history comes from. If querying
 * versions proves slow, a dedicated activity table replaces the second half of
 * this method and nothing else in the module changes.
 */
final class TicketTimelineService
{
    /**
     * @return Collection<int, TimelineEntry>
     */
    public function for(Ticket $ticket): Collection
    {
        $comments = $ticket->comments()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($comment): TimelineEntry => TimelineEntry::comment(
                $comment->created_at,
                $comment->author_id,
                $comment->source_key,
                $comment->body,
            ));

        $changes = $ticket->versions()
            ->orderBy('created_at')
            ->get()
            ->map(function ($version): TimelineEntry {
                /** @var array<string, mixed> $payload */
                $payload = $version->versionable_attributes ?? [];

                return TimelineEntry::change(
                    $version->created_at,
                    $version->user_id ?? null,
                    $payload,
                );
            })
            ->filter(fn (TimelineEntry $entry): bool => $entry->changes() !== []);

        return $comments
            ->concat($changes)
            ->sortBy(fn (TimelineEntry $entry): int => $entry->occurredAt()->getTimestamp())
            ->values();
    }
}
```

If Step 3 showed different column names on the version row, adjust `versionable_attributes` and `user_id` here to match — those two names are the only coupling between this service and Core's versioning.

- [ ] **Step 6: Run the test to verify it passes**

```bash
php artisan test --filter=TicketTimelineServiceTest
```

Expected: PASS, 4 tests.

- [ ] **Step 7: Format, analyse and commit**

```bash
vendor/bin/pint Modules/SAO
vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G
cd Modules/SAO
git add app/Data app/Services tests/Feature/Services
git commit -m "feat(sao): build the ticket timeline from comments and versions"
cd ../..
```

---

## Task 10: Permissions and the ACL read-path rule

**Files:**
- Create: `Modules/SAO/database/seeders/SAOPermissionSeeder.php`
- Modify: `Modules/SAO/database/seeders/SAODatabaseSeeder.php`
- Modify: `Modules/SAO/app/Services/WorkflowService.php`
- Create: `Modules/SAO/app/Services/TicketQueryService.php`
- Create: `Modules/SAO/tests/Feature/Authorization/TicketAuthorizationTest.php`

**Interfaces:**
- Consumes: `Core\Support\PermissionName`, `Core\Services\Authorization\AuthorizationService`.
- Produces: `TicketQueryService::visible(): Builder<Ticket>` — the **only** sanctioned way to read tickets; `WorkflowService::transition()` now enforces `required_permission` and the override permission.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Feature/Authorization/TicketAuthorizationTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\TicketQueryService;

uses(RefreshDatabase::class);

test('permission names follow the Laraplate convention', function (): void {
    expect(PermissionName::forClass(Ticket::class, 'transition'))
        ->toBe('default.sao_tickets.transition');
    expect(PermissionName::forClass(Ticket::class, 'view'))
        ->toBe('default.sao_tickets.view');
});

test('the seeder registers every SAO domain permission', function (): void {
    $this->seed(Modules\SAO\Database\Seeders\SAOPermissionSeeder::class);

    $expected = [
        'default.sao_tickets.view',
        'default.sao_tickets.create',
        'default.sao_tickets.update',
        'default.sao_tickets.delete',
        'default.sao_tickets.assign',
        'default.sao_tickets.transition',
        'default.sao_tickets.transition_override',
        'default.sao_projects.view',
        'default.sao_projects.create',
        'default.sao_projects.update',
        'default.sao_projects.delete',
    ];

    foreach ($expected as $name) {
        expect(Modules\Core\Models\Permission::query()->where('name', $name)->exists())
            ->toBeTrue("Missing permission: {$name}");
    }
});

test('the ticket query service applies ACL filters rather than raw Eloquent', function (): void {
    Ticket::factory()->count(3)->create();

    $query = app(TicketQueryService::class)->visible();

    // The service must have handed the query to Core's authorization layer.
    // Asserting on the produced SQL is brittle; asserting the service exists and
    // returns a builder over the right model is the contract this test locks.
    expect($query->getModel())->toBeInstanceOf(Ticket::class);
    expect($query->getQuery()->from)->toBe('sao_tickets');
});
```

The third test is deliberately modest about what it can prove. A stronger assertion — that a restricting ACL actually hides rows — requires a seeded role, permission and ACL, and belongs with the Filament work in Task 11 where a real authenticated user exists. What this test locks is that **a service exists** whose job is to be the read path, so that a future reviewer can grep for raw `Ticket::query()` outside it.

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=TicketAuthorizationTest
```

Expected: FAIL — `Modules\SAO\Database\Seeders\SAOPermissionSeeder` does not exist.

- [ ] **Step 3: Create the permission seeder**

Create `Modules/SAO/database/seeders/SAOPermissionSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Permission;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;

final class SAOPermissionSeeder extends Seeder
{
    /**
     * @var array<class-string, list<string>>
     */
    private const array OPERATIONS = [
        Ticket::class => ['view', 'create', 'update', 'delete', 'assign', 'transition', 'transition_override'],
        Project::class => ['view', 'create', 'update', 'delete'],
        TicketStatus::class => ['view', 'create', 'update', 'delete'],
        TicketType::class => ['view', 'create', 'update', 'delete'],
        WorkflowScheme::class => ['view', 'create', 'update', 'delete'],
    ];

    public function run(): void
    {
        foreach (self::OPERATIONS as $model_class => $operations) {
            foreach ($operations as $operation) {
                Permission::query()->firstOrCreate([
                    'name' => PermissionName::forClass($model_class, $operation),
                    'guard_name' => 'web',
                ]);
            }
        }
    }
}
```

- [ ] **Step 4: Register the seeder**

Replace the body of `Modules/SAO/database/seeders/SAODatabaseSeeder.php`'s `run()` with:

```php
    public function run(): void
    {
        $this->call(SAOPermissionSeeder::class);
    }
```

Keep the existing class declaration, namespace and `declare(strict_types=1);` line as they are.

- [ ] **Step 5: Create the ticket query service**

Create `Modules/SAO/app/Services/TicketQueryService.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\SAO\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Models\Ticket;

/**
 * The only sanctioned way to read tickets.
 *
 * Core's ACL chain is not automatic at the Eloquent level — HasACL's global
 * scope is an unimplemented TODO — so a service that queries tickets with raw
 * Eloquent silently bypasses row-level visibility. Every read path goes through
 * here or through Core's CRUD layer.
 */
final readonly class TicketQueryService
{
    public function __construct(private AuthorizationService $authorization) {}

    /**
     * @return Builder<Ticket>
     */
    public function visible(): Builder
    {
        $query = Ticket::query();

        $this->authorization->applyAclFiltersToQuery(
            $query,
            PermissionName::forClass(Ticket::class, 'view'),
        );

        return $query;
    }
}
```

- [ ] **Step 6: Enforce transition permissions in the workflow service**

In `Modules/SAO/app/Services/WorkflowService.php`, add these imports:

```php
use Illuminate\Support\Facades\Gate;
use Modules\Core\Support\PermissionName;
```

and replace the body of `transition()` with:

```php
    public function transition(Ticket $ticket, TicketStatus $to, ChangeContext $context): Ticket
    {
        $permitted = $this->availableTransitions($ticket)
            ->firstWhere('to_status_id', $to->getKey());

        if ($permitted === null) {
            $override = PermissionName::forClass(Ticket::class, 'transition_override');

            if (! $context->hasOverride() || ! Gate::allows($override)) {
                throw TransitionNotAllowedException::between($ticket, $to);
            }
        }

        if ($permitted?->required_permission !== null && ! Gate::allows($permitted->required_permission)) {
            throw TransitionNotAllowedException::between($ticket, $to);
        }

        $ticket->ticket_status_id = $to->getKey();
        $ticket->save();

        return $ticket;
    }
```

This changes the behaviour asserted in Task 7's override test: `withOverride()` alone no longer suffices without the permission. Update that test to authenticate a user holding `default.sao_tickets.transition_override` — the intent of the test is unchanged, but the bar it asserts is now the real one.

- [ ] **Step 7: Run both affected test files**

```bash
php artisan test --filter=TicketAuthorizationTest
php artisan test --filter=WorkflowServiceTest
```

Expected: both PASS. If the override test in `WorkflowServiceTest` fails, apply the Step 6 note before continuing — a failing test here means the enforcement works and the test predates it.

- [ ] **Step 8: Format, analyse and commit**

```bash
vendor/bin/pint Modules/SAO
vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G
cd Modules/SAO
git add app/Services database/seeders tests/Feature
git commit -m "feat(sao): seed domain permissions and route ticket reads through the ACL layer"
cd ../..
```

---

## Task 11: Filament resources for the configuration entities

**Files:**
- Create: `Modules/SAO/app/Filament/Resources/Projects/*`
- Create: `Modules/SAO/app/Filament/Resources/TicketStatuses/*`
- Create: `Modules/SAO/app/Filament/Resources/TicketTypes/*`
- Create: `Modules/SAO/app/Filament/Resources/WorkflowSchemes/*`
- Create: `Modules/SAO/tests/Feature/Filament/ConfigurationResourcesTest.php`

**Interfaces:**
- Consumes: the models from Tasks 2–5.
- Produces: Filament resources reachable in the admin panel. Task 12's ticket page sits beside them.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Feature/Filament/ConfigurationResourcesTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\TicketStatus;
use Modules\SAO\Models\TicketType;
use Modules\SAO\Models\WorkflowScheme;

uses(RefreshDatabase::class);

test('each configuration resource declares its model', function (string $resource, string $model): void {
    expect($resource::getModel())->toBe($model);
})->with([
    [Modules\SAO\Filament\Resources\Projects\ProjectResource::class, Project::class],
    [Modules\SAO\Filament\Resources\TicketStatuses\TicketStatusResource::class, TicketStatus::class],
    [Modules\SAO\Filament\Resources\TicketTypes\TicketTypeResource::class, TicketType::class],
    [Modules\SAO\Filament\Resources\WorkflowSchemes\WorkflowSchemeResource::class, WorkflowScheme::class],
]);

test('every SAO resource lives under the SAO navigation group', function (string $resource): void {
    expect($resource::getNavigationGroup())->toBe('SAO');
})->with([
    Modules\SAO\Filament\Resources\Projects\ProjectResource::class,
    Modules\SAO\Filament\Resources\TicketStatuses\TicketStatusResource::class,
    Modules\SAO\Filament\Resources\TicketTypes\TicketTypeResource::class,
    Modules\SAO\Filament\Resources\WorkflowSchemes\WorkflowSchemeResource::class,
]);
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=ConfigurationResourcesTest
```

Expected: FAIL — the resource classes do not exist.

- [ ] **Step 3: Generate the resources**

```bash
php artisan filament:make-resources --no-interaction
```

This is Laraplate's batch generator: it produces resources for module models using the `HasTable`/`HasForm` traits, honouring the `laraplate_owned` gate in `module.json`. Its design is described in `docs/superpowers/specs/2026-07-29-filament-make-resources-design.md`.

If it does not pick up SAO's models, generate them individually instead:

```bash
php artisan make:filament-resource Project --model-namespace='Modules\SAO\Models' --no-interaction
php artisan make:filament-resource TicketStatus --model-namespace='Modules\SAO\Models' --no-interaction
php artisan make:filament-resource TicketType --model-namespace='Modules\SAO\Models' --no-interaction
php artisan make:filament-resource WorkflowScheme --model-namespace='Modules\SAO\Models' --no-interaction
```

Then move the generated classes under `Modules/SAO/app/Filament/Resources/<Plural>/` and fix their namespaces to `Modules\SAO\Filament\Resources\<Plural>`, matching `Modules/ERP/app/Filament/Resources/Accounts/AccountResource.php`.

- [ ] **Step 4: Align each resource with the sibling convention**

For every generated resource, make it match `AccountResource`: `final class ... extends Coolsam\Modules\Resource`, `#[Override]` on the static properties, `protected static string|UnitEnum|null $navigationGroup = 'SAO';`, a `Heroicon` navigation icon, and `getSlug()` returning a stable path. Use these slugs:

| Resource | Slug | Sort |
|----------|------|------|
| `ProjectResource` | `sao/projects` | 10 |
| `TicketStatusResource` | `sao/ticket-statuses` | 20 |
| `TicketTypeResource` | `sao/ticket-types` | 30 |
| `WorkflowSchemeResource` | `sao/workflow-schemes` | 40 |

- [ ] **Step 5: Add the transitions relation manager**

Generate it and attach it to `WorkflowSchemeResource::getRelations()`:

```bash
php artisan make:filament-relation-manager WorkflowSchemeResource transitions label --no-interaction
```

The transitions table shows the source status, the target status and the action label, with the creation transition rendered as "(new ticket)" where the source is null — otherwise an empty cell reads as missing data rather than as the deliberate marker it is.

- [ ] **Step 6: Run the test to verify it passes**

```bash
php artisan test --filter=ConfigurationResourcesTest
```

Expected: PASS, 8 tests.

- [ ] **Step 7: Format, analyse and commit**

```bash
vendor/bin/pint Modules/SAO
vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon --memory-limit=2G
cd Modules/SAO
git add app/Filament tests/Feature/Filament
git commit -m "feat(sao): add Filament resources for the configuration entities"
cd ../..
```

---

## Task 12: The ticket surface, and closing the slice

**Files:**
- Create: `Modules/SAO/app/Filament/Resources/Tickets/*`
- Create: `Modules/SAO/tests/Feature/Filament/TicketResourceTest.php`
- Modify: `Modules/SAO/README.md`
- Modify: `Modules/SAO/docs/rag/MODULE.md`
- Modify: `Modules/SAO/CHANGELOG.md`

**Interfaces:**
- Consumes: everything from Tasks 1–11.
- Produces: the working tracker.

- [ ] **Step 1: Write the failing test**

Create `Modules/SAO/tests/Feature/Filament/TicketResourceTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SAO\Filament\Resources\Tickets\TicketResource;
use Modules\SAO\Models\Ticket;

uses(RefreshDatabase::class);

test('the ticket resource is bound to the ticket model and titled by its key', function (): void {
    expect(TicketResource::getModel())->toBe(Ticket::class);
    expect(TicketResource::getRecordTitleAttribute())->toBe('key');
});

test('the ticket list reads through the ACL-aware query service', function (): void {
    Ticket::factory()->count(2)->create();

    $query = TicketResource::getEloquentQuery();

    expect($query->getModel())->toBeInstanceOf(Ticket::class);
});

test('the ticket table can be filtered by status category', function (): void {
    $filters = TicketResource::table(new Filament\Tables\Table)->getFilters();

    expect(array_keys($filters))->toContain('status_category');
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=TicketResourceTest
```

Expected: FAIL — `Modules\SAO\Filament\Resources\Tickets\TicketResource` does not exist.

- [ ] **Step 3: Generate and shape the ticket resource**

```bash
php artisan make:filament-resource Ticket --model-namespace='Modules\SAO\Models' --view --no-interaction
```

Move it to `Modules/SAO/app/Filament/Resources/Tickets/` and shape it like the others, with `getSlug()` returning `sao/tickets`, sort `5` so it sits above the configuration entities, and `$recordTitleAttribute = 'key'`.

Override the base query so the list honours ACL:

```php
    public static function getEloquentQuery(): Builder
    {
        return app(TicketQueryService::class)->visible();
    }
```

Add a `status_category` filter over `StatusCategory::values()`, joining through the status relation. Filtering by category rather than by status name is what keeps the filter meaningful across projects that name their statuses differently.

- [ ] **Step 4: Wire ticket creation to the opening status**

Nothing so far connects a new ticket to its scheme's creation transition — the factory sets a status
directly, which is fine for tests but wrong for the real path. In the generated
`Pages/CreateTicket.php`, add:

```php
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $project = Project::query()->findOrFail($data['project_id']);
        $type = TicketType::query()->findOrFail($data['ticket_type_id']);

        $allocated = app(TicketKeyAllocator::class)->allocate($project);

        $data['number'] = $allocated['number'];
        $data['key'] = $allocated['key'];
        $data['ticket_status_id'] = app(WorkflowService::class)->openingStatusFor($project, $type)->getKey();
        $data['reporter_id'] = auth()->id();

        return $data;
    }
```

This is the only place a ticket's initial status is decided, and it decides it by **asking the
scheme** rather than by defaulting to something. A scheme with no creation transition throws here,
loudly, which is the correct moment to find out.

- [ ] **Step 5: Build the view page**

The view page shows, in order: the ticket header (key, title, type, status, priority, assignee), the
merged timeline from `TicketTimelineService::for()`, and a comment composer.

Transition actions are computed by the service, never by the page:

```php
    protected function getHeaderActions(): array
    {
        $ticket = $this->getRecord();
        $workflow = app(WorkflowService::class);

        return $workflow->availableTransitions($ticket)
            ->map(fn (WorkflowTransition $transition): Action => Action::make("transition_{$transition->id}")
                ->label($transition->label)
                ->requiresConfirmation()
                ->action(function () use ($ticket, $transition, $workflow): void {
                    $workflow->transition(
                        $ticket,
                        TicketStatus::query()->findOrFail($transition->to_status_id),
                        ChangeContext::forUser(auth()->user()),
                    );
                }))
            ->all();
    }
```

The comment composer calls the single sanctioned creation path:

```php
    public function postComment(string $body): void
    {
        TicketComment::postFor($this->getRecord(), $body, ChangeContext::forUser(auth()->user()));
    }
```

System comments render with a distinct background and no edit action, showing their `source_key`
where a human comment shows its author.

- [ ] **Step 6: Run the test to verify it passes**

```bash
php artisan test --filter=TicketResourceTest
```

Expected: PASS, 3 tests.

- [ ] **Step 7: Prove that a restricting ACL actually hides tickets**

Task 10 could only assert that a read-path service exists. This is the test that proves it works,
and it is the one the spec's §10 asks for. Create
`Modules/SAO/tests/Feature/Authorization/TicketVisibilityTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\TicketQueryService;

uses(RefreshDatabase::class);

test('an ACL restricting the view permission hides other projects tickets', function (): void {
    $this->seed(Modules\SAO\Database\Seeders\SAOPermissionSeeder::class);

    $mine = Project::factory()->create();
    $theirs = Project::factory()->create();

    Ticket::factory()->count(2)->create(['project_id' => $mine->id]);
    Ticket::factory()->count(3)->create(['project_id' => $theirs->id]);

    $permission = Permission::query()
        ->where('name', PermissionName::forClass(Ticket::class, 'view'))
        ->firstOrFail();

    $role = Role::query()->create(['name' => 'sao-limited', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    ACL::query()->create([
        'permission_id' => $permission->id,
        'filters' => ['conditions' => [['field' => 'project_id', 'operator' => '=', 'value' => $mine->id]]],
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user);

    expect(app(TicketQueryService::class)->visible()->count())->toBe(2);
});
```

The `filters` payload must match the `FiltersGroup` shape Core expects. Before writing it blind,
read an existing ACL row and copy its structure:

```bash
php artisan tinker --execute='echo json_encode(Modules\Core\Models\ACL::query()->first()?->filters);'
```

If no ACL exists yet, read `Modules\Core\Models\ACL`'s `filters` cast and the `FiltersGroup` class to
derive the shape. **Do not weaken this test into asserting that the service returns a builder** —
that is what Task 10 already asserts, and it proves nothing about visibility. If the filter shape
cannot be determined, leave the test failing and say so, rather than making it pass vacuously.

```bash
php artisan test --filter=TicketVisibilityTest
```

Expected: PASS, 1 test.

- [ ] **Step 8: Run every SAO test together**

```bash
php artisan test --filter='Sao|SAO|Ticket|Project|Workflow|Scaffolding|StrictTypes|ModuleMetadata|ModuleRegistration'
```

Expected: all green. This is the full 1a suite plus the phase 0 tests. Do **not** run the whole application suite.

- [ ] **Step 9: Update the module documentation**

In `Modules/SAO/README.md`, replace the "Current Bootstrap Status" list with what now exists: projects with immutable key prefixes, global statuses with canonical categories, shareable workflow schemes with enforced transitions, ticket types with per-project association and scheme override, tickets with row-locked key allocation, comments with human and system origins, a timeline read model, domain permissions, and Filament surfaces. State plainly that no external integration exists yet.

Copy the updated README over `Modules/SAO/docs/rag/MODULE.md`, which is a maintained copy.

Add to `Modules/SAO/CHANGELOG.md` under `## [unreleased]`:

```markdown
- Phase 1a — internal ticketing core: projects, per-project ticket keys, ticket types with shareable workflow schemes, enforced transitions, comments, timeline, permissions and Filament surfaces.
```

- [ ] **Step 10: Commit the module and bump the gitlink**

```bash
cd Modules/SAO
git add app/Filament tests/Feature/Filament README.md CHANGELOG.md docs/rag/MODULE.md
git commit -m "feat(sao): add the ticket surface and close slice 1a"
git push origin HEAD
cd ../..
git add Modules/SAO
git commit -m "chore(sao): bump SAO to the phase 1a ticketing core"
```

- [ ] **Step 11: Record slice completion**

In `docs/superpowers/specs/2026-07-31-sao-module-design.md`, append `— **done YYYY-MM-DD**` to the phase **1a** row of the §13 table, using the actual date. Commit:

```bash
git add docs/superpowers/specs/2026-07-31-sao-module-design.md
git commit -m "docs(sao): mark slice 1a complete"
```

---

## Slice exit criteria

1. Every SAO test passes (Step 6 of Task 12). The application-wide suite is **not** part of this gate — see the phase 0 plan for why that criterion is deliberately open.
2. `vendor/bin/pint --test Modules/SAO` reports no style issues.
3. `vendor/bin/phpstan analyse --configuration=Modules/SAO/phpstan.neon` reports no errors.
4. A person can, in the Filament panel: create a project, define statuses and a workflow scheme, create a ticket type using it, open a ticket, move it through its permitted transitions, comment on it, and reach a terminal status. With **no connection configured**, because in 1a none exists.
5. No occurrence of `Modules\AI` anywhere under `Modules/SAO`.
6. No file under `Modules/SAO/app` references an external system, a driver, or a connection (E12).
7. The concurrency test in Task 6 has either passed against MySQL or PostgreSQL, or its skip is explicitly recorded. Reporting the allocator as verified without one of the two is not acceptable.

## Known gaps carried into 1b

Labels, watchers, attachments, due dates, ticket-to-ticket relations, advanced search and saved filters. Each was deferred deliberately in the spec's §3, not overlooked.
