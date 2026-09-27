# Core Approvals Subsystem Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Approvals cover deletes, force deletes and restores as well as creates and updates, with the approval mechanism moved from `stephenlake/laravel-approval` into Core.

**Architecture:** `Modules\Core\Models\Concerns\HasApprovals` becomes the only trait: it listens to `saving`, `deleting` and `restoring`, captures a `Modification` carrying an `Operation`, and cancels the Eloquent operation. `ModificationVoteService` is the single place that votes, applies an approved operation, rejects and withdraws. Panel, CRUD API and AI tools read the captured outcome from the model instead of assuming the write happened.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest, nwidart modules (`Modules/Core`, `Modules/CMS`, `Modules/AI`).

**Spec:** `docs/superpowers/specs/2026-09-27-core-approvals-subsystem-design.md`

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`; explicit parameter and return types; `#[Override]` on overrides; code, comments and PHPDoc in English.
- No new migrations for existing data: change `Modules/Core/database/migrations/2024_03_30_161448_create_modifications_table.php` in place and convert the developer database by hand with the tinker snippet the task gives. The project has no other installation.
- Never declare classes, traits, interfaces or enums inside test files: test models go in `Modules/Core/tests/Stubs/` (namespace `Modules\Core\Tests\Stubs`).
- Tests live in the module that owns the code (`Modules/Core/tests`, `Modules/CMS/tests`, `Modules/AI/tests`), use Pest, and run from the repository root: `php artisan test --compact <path>`.
- Format with the root Pint config only, on explicit files: `vendor/bin/pint --format agent <files>`. Never run Pint from inside a module directory, never without a file list.
- Tests run in the console, where approvals never apply. A test exercising capture must pretend an HTTP request: `App::swap()` a partial mock whose `runningInConsole()` returns `false` (helper `pretendHttpRequest()` from Task 3).
- Derived files carry the header line `Derived from cloudcake/laravel-approval (MIT), see LICENSES/laravel-approval.md.` in their class PHPDoc.
- Superadmin writes are never captured (`User::isSuperAdmin()`), whatever `approversRequired` is.
- Commits go in the submodule that owns the change (`Modules/Core`, `Modules/CMS`, `Modules/AI`), then the parent repository bumps the submodule pointer. End every commit message with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. No push.
- Removing `stephenlake/laravel-approval` from `composer.json` (Task 13) needs the user's explicit approval at that moment.

## File Structure

| Path | Responsibility |
|---|---|
| `Modules/Core/app/Approvals/Operation.php` | enum `create`/`update`/`delete`/`force_delete`/`restore` |
| `Modules/Core/app/Approvals/PendingDeletionStrategy.php` | enum `Block`/`Hide` |
| `Modules/Core/app/Approvals/PendingDeletionLock.php` | exception for a write on a record blocked by a pending deletion |
| `Modules/Core/app/Approvals/Events/ModificationRejected.php`, `ModificationWithdrawn.php` | new events |
| `Modules/Core/app/Models/Concerns/HasApprovals.php` | the only approvals trait: listeners, capture, outcome, read-only check, hooks, scope |
| `Modules/Core/app/Models/Modification.php`, `Approval.php`, `Disapproval.php` | stand-alone models (no package base class) |
| `Modules/Core/app/Services/ModificationVoteService.php` | vote, apply, reject, withdraw |
| `Modules/Core/app/Filament/Concerns/ReportsApprovalOutcome.php` | shared panel behaviour for edit pages and delete/restore actions |
| `Modules/Core/LICENSES/laravel-approval.md` | MIT license and upstream version |
| `Modules/Core/tests/Stubs/Approvals/*` | test models |
| `Modules/Core/tests/Support/pretendHttpRequest.php` helper | registered in `Modules/Core/tests/Pest.php` |

---

## Phase 1 — The package code moves into Core, behaviour unchanged

### Task 1: Attribution and stand-alone approval models

**Files:**
- Create: `Modules/Core/LICENSES/laravel-approval.md`
- Modify: `Modules/Core/app/Models/Modification.php`, `Modules/Core/app/Models/Approval.php`, `Modules/Core/app/Models/Disapproval.php`
- Modify: `Modules/Core/README.md` (section "Third-party code")
- Test: `Modules/Core/tests/Integration/Models/ModificationModelTest.php`

**Interfaces:**
- Produces: `Modification::approvals(): HasMany`, `Modification::disapprovals(): HasMany`, `Modification::modifiable(): MorphTo`, `Modification::modifier(): MorphTo`, accessors `approversRemaining`, `disapproversRemaining`, scopes `activeOnly()`, `inactiveOnly()`; `Approval::modification()`, `Approval::approver()`; `Disapproval::modification()`, `Disapproval::disapprover()`. None of them extends an `Approval\Models\*` class any more.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Modules\Core\Models\Approval;
use Modules\Core\Models\Disapproval;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;

it('stands alone without the package base classes', function (): void {
    expect(get_parent_class(Modification::class))->toBe(Illuminate\Database\Eloquent\Model::class)
        ->and(get_parent_class(Approval::class))->toBe(Illuminate\Database\Eloquent\Model::class)
        ->and(get_parent_class(Disapproval::class))->toBe(Illuminate\Database\Eloquent\Model::class);
});

it('counts the approvals and disapprovals still needed', function (): void {
    $user = User::factory()->create();
    $modification = Modification::query()->create([
        'modifiable_type' => User::class,
        'modifiable_id' => $user->id,
        'active' => true,
        'is_update' => true,
        'approvers_required' => 2,
        'disapprovers_required' => 1,
        'md5' => md5('counts'),
        'modifications' => ['name' => ['original' => 'a', 'modified' => 'b']],
    ]);

    Approval::query()->create(['approver_id' => $user->id, 'approver_type' => User::class, 'modification_id' => $modification->id]);

    expect($modification->approversRemaining)->toBe(1)
        ->and($modification->disapproversRemaining)->toBe(1)
        ->and(Modification::query()->activeOnly()->whereKey($modification->id)->exists())->toBeTrue()
        ->and($modification->approvals()->sole()->modification->is($modification))->toBeTrue();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Models/ModificationModelTest.php`
Expected: FAIL on the first test (`get_parent_class` is `Approval\Models\Modification`).

- [ ] **Step 3: Make the three models stand alone**

In `Modification.php` replace `use Approval\Models\Modification as ApprovalModification;` and `extends ApprovalModification` with `use Illuminate\Database\Eloquent\Model;` / `extends Model`, keep everything already there, and add (class PHPDoc gains the derived-from line):

```php
    /**
     * @var list<string>
     */
    #[Override]
    protected $guarded = ['id'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function modifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function modifier(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<Approval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    /**
     * @return HasMany<Disapproval, $this>
     */
    public function disapprovals(): HasMany
    {
        return $this->hasMany(Disapproval::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function activeOnly(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function inactiveOnly(Builder $query): void
    {
        $query->where('active', false);
    }

    protected function approversRemaining(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->approvers_required - $this->approvals()->count());
    }

    protected function disapproversRemaining(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->disapprovers_required - $this->disapprovals()->count());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modifications' => 'json',
            'active' => 'boolean',
        ];
    }
```

Imports: `Illuminate\Database\Eloquent\Attributes\Scope`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Database\Eloquent\Casts\Attribute`, `Illuminate\Database\Eloquent\Relations\HasMany`, `Illuminate\Database\Eloquent\Relations\MorphTo`.

In `Approval.php` and `Disapproval.php` extend `Illuminate\Database\Eloquent\Model`, keep `HasModerationMeta` and `$table`, and add:

```php
    /**
     * @var list<string>
     */
    #[Override]
    protected $guarded = ['id'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function approver(): MorphTo   // `disapprover()` in Disapproval
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Modification, $this>
     */
    public function modification(): BelongsTo
    {
        return $this->belongsTo(Modification::class);
    }
```

Search the code for `Approval\Models\` (`command grep -rn 'Approval\\\\Models' Modules --include=*.php`) and replace every remaining type hint or `instanceof` with the Core class.

- [ ] **Step 4: Write the attribution**

`Modules/Core/LICENSES/laravel-approval.md`, same layout as `LICENSES/laravel-locked.md`: title `# cloudcake/laravel-approval (published as stephenlake/laravel-approval)`, source `https://github.com/cloudcake/laravel-approval`, "Derived into Core from version 1.1.4, upstream commit e7527e1.", the list of derived files (`app/Models/Modification.php`, `app/Models/Approval.php`, `app/Models/Disapproval.php`, `app/Models/Concerns/HasApprovals.php`, `app/Services/ModificationVoteService.php`), then the license text copied verbatim from `vendor/stephenlake/laravel-approval/LICENSE.md` inside a fenced block. Add the header line from the Global Constraints to each derived file's class PHPDoc. In `Modules/Core/README.md`, add to the "derived code" list:

```markdown
-   [cloudcake/laravel-approval](https://github.com/cloudcake/laravel-approval): approvals (`HasApprovals`, `Modification`, `ModificationVoteService`), see [`LICENSES/laravel-approval.md`](LICENSES/laravel-approval.md)
```

- [ ] **Step 5: Run the new test and the approvals suite**

Run: `php artisan test --compact Modules/Core/tests/Integration/Models/ModificationModelTest.php Modules/Core/tests/Integration/Models Modules/Core/tests/Feature/Filament/ModificationResourceTest.php Modules/Core/tests/Feature/Controllers/CrudPendingApprovalsTest.php Modules/CMS/tests/Feature/CommentModerationTest.php Modules/AI/tests/Feature/Jobs/ApproveModificationJobTest.php`
Expected: PASS.

- [ ] **Step 6: Pint and commit**

```bash
vendor/bin/pint --format agent Modules/Core/app/Models/Modification.php Modules/Core/app/Models/Approval.php Modules/Core/app/Models/Disapproval.php Modules/Core/tests/Integration/Models/ModificationModelTest.php
git -C Modules/Core add LICENSES/laravel-approval.md README.md app/Models tests/Integration/Models/ModificationModelTest.php
git -C Modules/Core commit -m "refactor(core): approval models stand alone, attributed to laravel-approval"
```

### Task 2: `HasApprovals` without the package trait, `User` without `ApprovesChanges`

**Files:**
- Modify: `Modules/Core/app/Models/Concerns/HasApprovals.php`
- Modify: `Modules/Core/app/Models/User.php` (drop `use ApprovesChanges;`)
- Modify: `Modules/AI/app/Jobs/ApproveModificationJob.php:198,216` (vote through the service)
- Test: `Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php` (add cases)

**Interfaces:**
- Consumes: Task 1 models.
- Produces on every `HasApprovals` model: `protected int $approversRequired = 1`, `protected int $disapproversRequired = 1`, `protected bool $updateWhenApproved = true`, `isForcedApprovalUpdate(): bool`, `setForcedApprovalUpdate(bool $forced = true): void`, `modifications(): MorphMany<Modification, $this>`, `applyModificationChanges(Modification $modification, bool $approved): void`, `static captureSave(Model $item): bool`, `protected requiresApprovalWhen(array $modifications): bool` (superadmin rule included).

- [ ] **Step 1: Write the failing test** (append to `HasApprovalsTest.php`)

```php
it('no longer relies on the laravel-approval trait', function (): void {
    expect(class_uses_recursive(HasApprovalsStubModel::class))->not->toHaveKey(Approval\Traits\RequiresApproval::class)
        ->and(class_uses_recursive(Modules\Core\Models\User::class))->not->toHaveKey(Approval\Traits\ApprovesChanges::class);
});

it('never captures what a superadmin writes', function (): void {
    pretendHttpRequest();
    $superadmin = Modules\Core\Models\User::factory()->create();
    $superadmin->assignRole(Modules\Core\Models\Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
    Illuminate\Support\Facades\Auth::login($superadmin);

    $model = new HasApprovalsStubModel;
    $model->setApproversRequired(3);

    $method = new ReflectionMethod($model, 'requiresApprovalWhen');

    expect($method->invoke($model, ['name' => 'next']))->toBeFalse();
});
```

and create the helper `Modules/Core/tests/Support/HttpContext.php`, autoloaded as a file from `Modules/Core/tests/Pest.php` (`require_once __DIR__ . '/Support/HttpContext.php';`):

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;

/**
 * Approvals never apply to console writes, and tests run in the console: pretend an HTTP request.
 */
function pretendHttpRequest(): void
{
    $mock = Mockery::mock(App::getFacadeRoot())->makePartial();
    $mock->shouldReceive('runningInConsole')->andReturn(false);
    App::swap($mock);
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php`
Expected: FAIL on "no longer relies on the laravel-approval trait".

- [ ] **Step 3: Bring the package behaviour into `HasApprovals`**

Remove `use RequiresApproval;` and the `Approval\...` imports; the trait now declares what the package trait provided:

```php
    protected int $approversRequired = 1;

    protected int $disapproversRequired = 1;

    protected bool $updateWhenApproved = true;

    protected bool $deleteWhenApproved = true;

    protected bool $deleteWhenDisapproved = true;

    private bool $forcedApprovalUpdate = false;

    public static function bootHasApprovals(): void
    {
        static::saving(static function (Model $item): ?bool {
            if (! $item->isForcedApprovalUpdate() && $item->requiresApprovalWhen($item->getDirty()) === true) {
                return static::captureSave($item);
            }

            $item->setForcedApprovalUpdate(false);

            return null;
        });
    }

    public function isForcedApprovalUpdate(): bool
    {
        return $this->forcedApprovalUpdate;
    }

    public function setForcedApprovalUpdate(bool $forced = true): void
    {
        $this->forcedApprovalUpdate = $forced;
    }

    /**
     * @return MorphMany<Modification, $this>
     */
    public function modifications(): MorphMany
    {
        return $this->morphMany(Modification::class, 'modifiable');
    }

    public function applyModificationChanges(Modification $modification, bool $approved): void
    {
        if ($approved && $this->updateWhenApproved) {
            $this->setForcedApprovalUpdate(true);

            foreach ($modification->modifications as $key => $change) {
                $this->{$key} = $change['modified'];
            }

            $this->save();
        }

        $delete = $approved ? $this->deleteWhenApproved : $this->deleteWhenDisapproved;

        if ($delete) {
            $modification->delete();

            return;
        }

        $modification->active = false;
        $modification->save();
    }
```

`captureSave()` keeps its body; the modifier becomes `Auth::user()` (it was the package's `modifier()`), and the `config('approval.models.modification', …)` lookups become `Modification::class`. `requiresApprovalWhen()` keeps the superadmin rule already in the working tree. Change every `applyModificationChanges(\Approval\Models\Modification …)` signature in models (`Comment`) to `Modification`.

In `User.php` remove `use ApprovesChanges;` and add, below `isAuthorizedToCastApprovalVote()`:

```php
    public function approve(Modification $modification, ?string $reason = null): bool
    {
        return resolve(ModificationVoteService::class)->cast($this, $modification, true, $reason);
    }

    public function disapprove(Modification $modification, ?string $reason = null): bool
    {
        return resolve(ModificationVoteService::class)->cast($this, $modification, false, $reason);
    }
```

- [ ] **Step 4: Run the approvals suites**

Run: `php artisan test --compact Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php Modules/Core/tests/Integration/Models Modules/Core/tests/Feature/Models Modules/Core/tests/Feature/Controllers Modules/Core/tests/Feature/Filament/ModificationResourceTest.php Modules/CMS/tests/Feature Modules/AI/tests/Feature/Jobs Modules/AI/tests/Feature/ApproveModificationJobTest.php Modules/AI/tests/Feature/ModificationModerationListenerTest.php`
Expected: PASS.

- [ ] **Step 5: Pint and commit** (Core, then CMS for `Comment`, then AI if the job changed)

```bash
vendor/bin/pint --format agent Modules/Core/app/Models/Concerns/HasApprovals.php Modules/Core/app/Models/User.php Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php Modules/Core/tests/Support/HttpContext.php Modules/CMS/app/Models/Comment.php
git -C Modules/Core add -A app tests && git -C Modules/Core commit -m "refactor(core): HasApprovals carries the approval behaviour itself"
git -C Modules/CMS add app/Models/Comment.php && git -C Modules/CMS commit -m "refactor(cms): comment approvals use the Core modification type"
```

### Task 3: Settings save through the shared write rule

This is the work left uncommitted in `Modules/Core`: `Setting::requiresApprovalWhen()` checks its guarded fields, then defers to the trait; `EditSetting` reports a change sent for approval. Close it on top of Task 2.

**Files:**
- Modify: `Modules/Core/app/Models/Setting.php`, `Modules/Core/app/Filament/Resources/Settings/Pages/EditSetting.php` (already in the working tree)
- Test: `Modules/Core/tests/Integration/Models/SettingTest.php`, `Modules/Core/tests/Feature/Filament/EditSettingFormTest.php` (already in the working tree)

- [ ] **Step 1: Switch the tests to `pretendHttpRequest()`**

Replace the local helpers `settingWrittenOverHttp()` (SettingTest) and `settingFormOverHttp()` (EditSettingFormTest) with calls to `pretendHttpRequest()` and delete the two local functions. In "sends an is_public change from the form to approval", replace `editSettingActor();` with `editSettingActorWithoutApproval();` and add `->assertNotified('Change sent for approval')` after `->assertHasNoFormErrors()`: a superadmin is never captured, so only a non-approver can reach approval.

- [ ] **Step 2: Diagnose "offers no create or delete actions on settings"**

It fails with `SettingsTable::loadUserPermissionsForTable(): Argument #1 ($user) must be of type ?App\Models\User, Modules\Core\Models\User given`. Use superpowers:systematic-debugging. Start from `Modules/Core/app/Filament/Utils/HasTable.php:139-142` (`Auth::user()` typed as `App\Models\User`) and find which guard or provider hands back a `Modules\Core\Models\User` in that test (`Filament::setCurrentPanel('admin')` switches the guard to `admin`; compare `config('auth.guards.admin.provider')`, `config('auth.providers.*.model')` and `LockAwareUserProvider`). Fix the cause, not the test. If the cause is `HasTable` typing the application user class while guards may return the Core base class, type the parameter as `?Modules\Core\Models\User` there.

- [ ] **Step 3: Run**

Run: `php artisan test --compact Modules/Core/tests/Integration/Models/SettingTest.php Modules/Core/tests/Feature/Filament/EditSettingFormTest.php Modules/Core/tests/Feature/Filament/SettingResourceTest.php`
Expected: PASS.

- [ ] **Step 4: Pint and commit**

```bash
vendor/bin/pint --format agent Modules/Core/app/Models/Setting.php Modules/Core/app/Filament/Resources/Settings/Pages/EditSetting.php Modules/Core/tests/Integration/Models/SettingTest.php Modules/Core/tests/Feature/Filament/EditSettingFormTest.php
git -C Modules/Core add -A app tests && git -C Modules/Core commit -m "fix(core): settings follow the shared approval write rule and say when a change waits for approval"
```

---

## Phase 2 — `operation` replaces `is_update`

### Task 4: `Operation` enum and column

**Files:**
- Create: `Modules/Core/app/Approvals/Operation.php`
- Modify: `Modules/Core/database/migrations/2024_03_30_161448_create_modifications_table.php`
- Modify: `Modules/Core/app/Models/Modification.php` (cast, hidden list), `Modules/Core/app/Models/Concerns/HasApprovals.php` (`captureSave`), `Modules/CMS/app/Services/CommentApprovalCapture.php:116`, `Modules/Core/app/Filament/Resources/Modifications/Schemas/ModificationForm.php:48-50`, `Modules/Core/app/Filament/Resources/Modifications/Tables/ModificationsTable.php` (operation column)
- Modify: every test creating a `Modification` with `'is_update' => …` (find them with `command grep -rln "'is_update'" Modules/*/tests`)
- Test: `Modules/Core/tests/Integration/Models/ModificationModelTest.php`

**Interfaces:**
- Produces: `enum Operation: string { Create = 'create'; Update = 'update'; Delete = 'delete'; ForceDelete = 'force_delete'; Restore = 'restore' }` with `isDeletion(): bool` (Delete, ForceDelete) and `carriesDiff(): bool` (Create, Update); `Modification::$operation` cast to `Operation`.

- [ ] **Step 1: Write the failing test** (append to `ModificationModelTest.php`)

```php
it('stores the operation it carries', function (): void {
    $user = Modules\Core\Models\User::factory()->create();
    $modification = Modification::query()->create([
        'modifiable_type' => Modules\Core\Models\User::class,
        'modifiable_id' => $user->id,
        'active' => true,
        'operation' => Modules\Core\Approvals\Operation::Delete,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5('delete'),
        'modifications' => [],
    ]);

    expect($modification->fresh()->operation)->toBe(Modules\Core\Approvals\Operation::Delete)
        ->and(Modules\Core\Approvals\Operation::Delete->isDeletion())->toBeTrue()
        ->and(Modules\Core\Approvals\Operation::Update->carriesDiff())->toBeTrue()
        ->and(Illuminate\Support\Facades\Schema::hasColumn($modification->getTable(), 'is_update'))->toBeFalse();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Models/ModificationModelTest.php`
Expected: FAIL (`Operation` class not found).

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Approvals;

enum Operation: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case ForceDelete = 'force_delete';
    case Restore = 'restore';

    public function isDeletion(): bool
    {
        return $this === self::Delete || $this === self::ForceDelete;
    }

    public function carriesDiff(): bool
    {
        return $this === self::Create || $this === self::Update;
    }
}
```

In the create migration replace the `is_update` line with

```php
            $table->string('operation', 16)->default('update')->comment('The operation the modification carries');
```

and add after the modifier index:

```php
            $table->index(['modifiable_type', 'modifiable_id', 'active', 'operation'], "{$modifications_table}_pending_IDX");
```

`Modification`: cast `'operation' => Operation::class`, replace `'is_update'` with `'operation'` in `$hidden`. `HasApprovals::captureSave()` and `CommentApprovalCapture::capture()`: replace `$modification->is_update = false;` with `$modification->operation = $item->getKey() === null ? Operation::Create : Operation::Update;` (same for `$comment`). `ModificationForm`: replace the `is_update` toggle with `TextInput::make('operation')->formatStateUsing(self::fromRecord('operation'))->disabled()`. `ModificationsTable`: add `TextColumn::make('operation')->badge()` after the modifiable column. Update the tests found by the grep: `'is_update' => true` → `'operation' => Operation::Update`, `'is_update' => false` → `'operation' => Operation::Create`.

- [ ] **Step 4: Convert the developer database**

```bash
php artisan tinker --execute '
Illuminate\Support\Facades\Schema::table("core_modifications", function ($t) { $t->string("operation", 16)->default("update")->after("active"); });
Illuminate\Support\Facades\DB::table("core_modifications")->where("is_update", false)->update(["operation" => "create"]);
Illuminate\Support\Facades\Schema::table("core_modifications", function ($t) { $t->dropColumn("is_update"); $t->index(["modifiable_type", "modifiable_id", "active", "operation"], "core_modifications_pending_IDX"); });
echo Illuminate\Support\Facades\DB::table("core_modifications")->selectRaw("operation, count(*) c")->groupBy("operation")->pluck("c", "operation");'
```

Expected: the pending rows are kept, each with `create` or `update`.

- [ ] **Step 5: Run the approvals suites** (same command as Task 2 Step 4). Expected: PASS.

- [ ] **Step 6: Pint and commit** in Core and CMS, message `refactor(core): modifications carry an operation instead of is_update`.

---

## Phase 3 — Deletes, force deletes and restores go through approval

### Task 5: Capture of every operation, outcome and read-only check

**Files:**
- Modify: `Modules/Core/app/Models/Concerns/HasApprovals.php`
- Create: `Modules/Core/tests/Stubs/Approvals/SoftDeletableApprovalModel.php` (Core `SoftDeletes` + `HasApprovals`, table `approvals_soft_stub`, fillable `name`)
- Test: `Modules/Core/tests/Integration/Approvals/OperationCaptureTest.php`

**Interfaces:**
- Consumes: `Operation` (Task 4).
- Produces: `approvalOperations(): list<Operation>` (overridable, default `Operation::cases()`), `pendingModification(): ?Modification`, `wouldRequireApproval(Operation $operation): bool`, `static captureOperation(Model $item, Operation $operation): bool`, `pendingOperationRequest(Operation $operation): ?Modification`.

- [ ] **Step 1: Write the stub**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Approvals;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Approvals\Operation;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\SoftDeletes\SoftDeletes;

final class SoftDeletableApprovalModel extends Model
{
    use HasApprovals;
    use SoftDeletes;

    /**
     * @var list<Operation>|null
     */
    public static ?array $operations = null;

    protected $table = 'approvals_soft_stub';

    protected $fillable = ['name'];

    public function approvalOperations(): array
    {
        return self::$operations ?? Operation::cases();
    }
}
```

- [ ] **Step 2: Write the failing tests**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Approvals\Operation;
use Modules\Core\Models\User;
use Modules\Core\Tests\Stubs\Approvals\SoftDeletableApprovalModel;

beforeEach(function (): void {
    Schema::create('approvals_soft_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });
    SoftDeletableApprovalModel::$operations = null;
    $this->record = SoftDeletableApprovalModel::query()->create(['name' => 'kept']);
    pretendHttpRequest();
    Auth::login(User::factory()->create());
});

afterEach(fn () => Schema::dropIfExists('approvals_soft_stub'));

it('captures a soft delete instead of running it', function (): void {
    expect($this->record->delete())->toBeFalse()
        ->and($this->record->fresh()->trashed())->toBeFalse()
        ->and($this->record->pendingModification()?->operation)->toBe(Operation::Delete)
        ->and($this->record->pendingModification()?->modifications)->toBe([]);
});

it('captures a force delete as force_delete', function (): void {
    expect($this->record->forceDelete())->toBeFalse()
        ->and(SoftDeletableApprovalModel::query()->whereKey($this->record->id)->exists())->toBeTrue()
        ->and($this->record->pendingModification()?->operation)->toBe(Operation::ForceDelete);
});

it('captures a restore of a trashed record', function (): void {
    Auth::logout();
    $this->record->delete();
    Auth::login(User::factory()->create());

    $trashed = SoftDeletableApprovalModel::withTrashed()->find($this->record->id);

    expect($trashed->restore())->toBeFalse()
        ->and($trashed->fresh()->trashed())->toBeTrue()
        ->and($trashed->pendingModification()?->operation)->toBe(Operation::Restore);
});

it('keeps a single pending request per deletion', function (): void {
    $this->record->delete();
    $this->record->delete();

    expect($this->record->modifications()->activeOnly()->count())->toBe(1);
});

it('lets an operation the model does not cover run directly', function (): void {
    SoftDeletableApprovalModel::$operations = [Operation::Update];

    expect($this->record->delete())->toBeTrue()
        ->and(SoftDeletableApprovalModel::withTrashed()->find($this->record->id)->trashed())->toBeTrue();
});

it('answers in advance whether an operation needs approval', function (): void {
    expect($this->record->wouldRequireApproval(Operation::Delete))->toBeTrue();

    SoftDeletableApprovalModel::$operations = [Operation::Update];

    expect($this->record->wouldRequireApproval(Operation::Delete))->toBeFalse();
});
```

- [ ] **Step 3: Run to verify they fail**

Run: `php artisan test --compact Modules/Core/tests/Integration/Approvals/OperationCaptureTest.php`
Expected: FAIL (`pendingModification` undefined, deletes run).

- [ ] **Step 4: Implement in `HasApprovals`**

Add the outcome property and extend the boot method:

```php
    private ?Modification $pendingModification = null;

    public static function bootHasApprovals(): void
    {
        static::saving(static function (Model $item): ?bool {
            if (! $item->isForcedApprovalUpdate() && $item->shouldCapture(Operation::Update) && $item->requiresApprovalWhen($item->getDirty()) === true) {
                return static::captureSave($item);
            }

            $item->setForcedApprovalUpdate(false);

            return null;
        });

        static::deleting(static function (Model $item): ?bool {
            $operation = $item->deletionOperation();

            return $item->shouldCapture($operation) && $item->requiresApprovalWhen(['__operation' => $operation->value])
                ? static::captureOperation($item, $operation)
                : null;
        });

        static::restoring(static function (Model $item): ?bool {
            return $item->shouldCapture(Operation::Restore) && $item->requiresApprovalWhen(['__operation' => Operation::Restore->value])
                ? static::captureOperation($item, Operation::Restore)
                : null;
        });
    }

    /**
     * @return list<Operation>
     */
    public function approvalOperations(): array
    {
        return Operation::cases();
    }

    public function pendingModification(): ?Modification
    {
        return $this->pendingModification;
    }

    public function wouldRequireApproval(Operation $operation): bool
    {
        return $this->shouldCapture($operation) && $this->requiresApprovalWhen(['__operation' => $operation->value]);
    }

    public static function captureOperation(Model $item, Operation $operation): bool
    {
        $existing = $item->pendingOperationRequest($operation);

        $modification = $existing ?? new Modification();
        $modification->active = true;
        $modification->operation = $operation;
        $modification->modifications = [];
        $modification->approvers_required = $item->approversRequired;
        $modification->disapprovers_required = $item->disapproversRequired;
        $modification->md5 = md5($operation->value . '|' . $item::class . '|' . $item->getKey());

        $user = Auth::user();

        if ($user !== null) {
            $modification->modifier()->associate($user);
        }

        $existing === null ? $item->modifications()->save($modification) : $modification->save();

        $item->pendingModification = $modification;
        $item->applyAuthorApproveCredit($modification);

        return false;
    }

    public function pendingOperationRequest(Operation $operation): ?Modification
    {
        return $this->modifications()->activeOnly()->where('operation', $operation->value)->first();
    }

    protected function shouldCapture(Operation $operation): bool
    {
        return ! $this->isForcedApprovalUpdate() && in_array($operation, $this->approvalOperations(), true);
    }

    protected function deletionOperation(): Operation
    {
        $soft = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($this), true)
            || in_array(\Modules\Core\SoftDeletes\SoftDeletes::class, class_uses_recursive($this), true);

        if (! $soft || (method_exists($this, 'isForceDeleting') && $this->isForceDeleting())) {
            return Operation::ForceDelete;
        }

        if (method_exists($this, 'softDeletesEnabledBySettings') && ! $this->softDeletesEnabledBySettings()) {
            return Operation::ForceDelete;
        }

        return Operation::Delete;
    }
```

`captureSave()` sets `$item->pendingModification = $modification;` before returning. `requiresApprovalWhen()` treats `['__operation' => …]` as a non-empty change set, so the existing console/superadmin/approve-credit rule decides for deletes and restores too; model rules that inspect field names (`Setting`, `Taxonomy`) must defer to the shared rule for `__operation`: add at the top of each override `if (array_key_exists('__operation', $modifications)) { return $this->requiresApprovalWhenTrait($modifications); }`. `Content` keeps its rule unchanged: drafts and expired contents are written directly, so they are also deleted and restored directly; live and scheduled contents go through the shared rule.

Laravel's `forceDelete()` fires `forceDeleting` and then `delete()`, which fires `deleting` with `isForceDeleting()` true: listening to `deleting` alone covers both. A restore rejected by `restoring` never reaches `save()`.

Restore only on a trashed record: in the `restoring` listener return `null` (let Laravel handle it) when `! $item->trashed()`.

- [ ] **Step 5: Run the capture tests and the approvals suites**

Run: `php artisan test --compact Modules/Core/tests/Integration/Approvals Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php Modules/Core/tests/Integration/Models Modules/Core/tests/Feature/Controllers Modules/CMS/tests/Feature Modules/Core/tests/Feature/Console/ModelSoftDeletesCommandsTest.php`
Expected: PASS.

- [ ] **Step 6: Pint and commit** (`feat(core): deletes, force deletes and restores go through approval`), including the three model rules in Core and CMS.

### Task 6: Applying approved deletes and restores, rejecting pending updates

**Files:**
- Modify: `Modules/Core/app/Services/ModificationVoteService.php`
- Modify: `Modules/Core/app/Models/Concerns/HasApprovals.php` (`applyModificationChanges` for non-diff operations)
- Test: `Modules/Core/tests/Integration/Approvals/OperationApplyTest.php`

**Interfaces:**
- Consumes: Task 5 capture.
- Produces: `ModificationVoteService::cast(User $user, Modification $modification, bool $approval, ?string $reason = null, ?Model $modifiable = null): bool` now runs in one transaction on the modifiable model's connection and applies every operation.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Approvals\Operation;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\ModificationVoteService;
use Modules\Core\Tests\Stubs\Approvals\SoftDeletableApprovalModel;

beforeEach(function (): void {
    Schema::create('approvals_soft_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });
    $this->record = SoftDeletableApprovalModel::query()->create(['name' => 'kept']);
    pretendHttpRequest();
    $this->author = User::factory()->create();
    $this->approver = User::factory()->create();
    $this->approver->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
});

afterEach(fn () => Schema::dropIfExists('approvals_soft_stub'));

it('soft-deletes the record once the deletion is approved and keeps the decision', function (): void {
    Auth::login($this->author);
    $this->record->delete();
    $modification = $this->record->pendingModification();

    resolve(ModificationVoteService::class)->cast($this->approver, $modification, true);

    expect($this->record->fresh()->trashed())->toBeTrue()
        ->and($modification->fresh()->active)->toBeFalse();
});

it('force-deletes the record once the force deletion is approved', function (): void {
    Auth::login($this->author);
    $this->record->forceDelete();

    resolve(ModificationVoteService::class)->cast($this->approver, $this->record->pendingModification(), true);

    expect(SoftDeletableApprovalModel::withTrashed()->whereKey($this->record->id)->exists())->toBeFalse();
});

it('restores the record once the restore is approved', function (): void {
    Auth::login($this->approver);
    $this->record->delete();
    Auth::login($this->author);
    $trashed = SoftDeletableApprovalModel::withTrashed()->find($this->record->id);
    $trashed->restore();

    resolve(ModificationVoteService::class)->cast($this->approver, $trashed->pendingModification(), true);

    expect($trashed->fresh()->trashed())->toBeFalse();
});

it('rejects the pending updates of a record whose deletion is approved', function (): void {
    Auth::login($this->author);
    $this->record->update(['name' => 'changed']);
    $update = $this->record->pendingModification();
    $this->record->delete();

    resolve(ModificationVoteService::class)->cast($this->approver, $this->record->pendingModification(), true);

    expect($update->fresh()->active)->toBeFalse()
        ->and($update->fresh()->disapprovals()->sole()->reason)->toBe('record deleted');
});

it('leaves the request pending when applying the approved operation fails', function (): void {
    Auth::login($this->author);
    $this->record->forceDelete();
    $modification = $this->record->pendingModification();
    Schema::drop('approvals_soft_stub');

    expect(fn () => resolve(ModificationVoteService::class)->cast($this->approver, $modification, true))->toThrow(Illuminate\Database\QueryException::class)
        ->and(Modification::query()->find($modification->id)->active)->toBeTrue()
        ->and(Modification::query()->find($modification->id)->approvals()->count())->toBe(0);
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --compact Modules/Core/tests/Integration/Approvals/OperationApplyTest.php`
Expected: FAIL (approving a deletion applies an empty diff and does not delete).

- [ ] **Step 3: Implement**

In `HasApprovals::applyModificationChanges()`, before the diff branch:

```php
        if ($approved && ! $modification->operation->carriesDiff()) {
            $this->setForcedApprovalUpdate(true);

            match ($modification->operation) {
                Operation::Delete => $this->delete(),
                Operation::ForceDelete => method_exists($this, 'forceDelete') ? $this->forceDelete() : $this->delete(),
                Operation::Restore => $this->restore(),
                default => null,
            };

            $this->setForcedApprovalUpdate(false);
        }
```

and set `$deleteWhenApproved = false` and `$deleteWhenDisapproved = false` as the trait defaults: decided modifications are kept. Remove the now redundant overrides in `Content::initializeHasApprovals()` and `HasApprovals::initializeHasApprovals()` (`$this->deleteWhenDisapproved = true;`).

In `ModificationVoteService::cast()` wrap the whole body in `$modification->getConnection()->transaction(function () use (…): bool { … })`, and after `$target->applyModificationChanges($modification, $approval);` add:

```php
        if ($approval && $modification->operation->isDeletion()) {
            $this->rejectPendingUpdatesOf($modification, $user);
        }
```

with

```php
    private function rejectPendingUpdatesOf(Modification $deletion, User $decider): void
    {
        $deletion->newQuery()
            ->where('modifiable_type', $deletion->modifiable_type)
            ->where('modifiable_id', $deletion->modifiable_id)
            ->where('operation', Operation::Update->value)
            ->activeOnly()
            ->each(static function (Modification $update) use ($decider): void {
                $update->disapprovals()->create([
                    'disapprover_id' => $decider->getKey(),
                    'disapprover_type' => $decider::class,
                    'reason' => 'record deleted',
                ]);
                $update->active = false;
                $update->save();
            });
    }
```

- [ ] **Step 4: Run** the new test file and the approvals suites (Task 5 Step 5 command, plus `Modules/CMS/tests/Feature/Models/ContentModificationSoftKeepTest.php`). Expected: PASS.

- [ ] **Step 5: Pint and commit** (`feat(core): approving a deletion or restore runs it, in one transaction with the vote`).

### Task 7: Pending deletion strategy — Block and Hide

**Files:**
- Create: `Modules/Core/app/Approvals/PendingDeletionStrategy.php`, `Modules/Core/app/Approvals/PendingDeletionLock.php`
- Modify: `Modules/Core/app/Models/Concerns/HasApprovals.php`
- Create: `Modules/Core/tests/Stubs/Approvals/HiddenWhilePendingDeletionModel.php` (same table and traits as `SoftDeletableApprovalModel`, `pendingDeletionStrategy()` returns `Hide`)
- Test: `Modules/Core/tests/Integration/Approvals/PendingDeletionStrategyTest.php`

**Interfaces:**
- Produces: `enum PendingDeletionStrategy { case Block; case Hide; }`, `final class PendingDeletionLock extends RuntimeException`, on models `pendingDeletionStrategy(): PendingDeletionStrategy` (default `Block`), `attributesWritableWhilePendingDeletion(): list<string>` (default `[]`), local scope `withoutPendingDeletion()`, global scope applied to `Hide` models for users without the table's `approve` or `disapprove` permission.

- [ ] **Step 1: Write the failing tests**

```php
it('refuses to save a record whose deletion is pending', function (): void {
    Auth::login($this->author);
    $this->record->delete();

    $this->record->name = 'edited';

    expect(fn () => $this->record->save())->toThrow(PendingDeletionLock::class);
});

it('still saves the attributes the model declares writable', function (): void {
    SoftDeletableApprovalModel::$writable = ['updated_at'];
    Auth::login($this->author);
    $this->record->delete();

    $this->record->touch();

    expect($this->record->fresh()->updated_at)->not->toBeNull();
});

it('hides a record whose deletion is pending from users who cannot decide on it', function (): void {
    Auth::login($this->author);
    $hidden = HiddenWhilePendingDeletionModel::query()->find($this->record->id);
    $hidden->delete();

    expect(HiddenWhilePendingDeletionModel::query()->whereKey($this->record->id)->exists())->toBeFalse();

    Auth::login($this->approver);

    expect(HiddenWhilePendingDeletionModel::query()->whereKey($this->record->id)->exists())->toBeTrue();
});

it('shows the record again once the deletion is rejected', function (): void {
    Auth::login($this->author);
    $hidden = HiddenWhilePendingDeletionModel::query()->find($this->record->id);
    $hidden->delete();
    resolve(ModificationVoteService::class)->cast($this->approver, $hidden->pendingModification(), false);

    expect(HiddenWhilePendingDeletionModel::query()->whereKey($this->record->id)->exists())->toBeTrue();
});
```

(`beforeEach`/`afterEach` as in Task 6; `SoftDeletableApprovalModel` gains `public static array $writable = [];` returned by `attributesWritableWhilePendingDeletion()`, reset in `beforeEach`.)

- [ ] **Step 2: Run to verify they fail.** Expected: FAIL (`PendingDeletionLock` not found).

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Approvals;

enum PendingDeletionStrategy
{
    case Block;
    case Hide;
}
```

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Approvals;

use RuntimeException;

final class PendingDeletionLock extends RuntimeException
{
    public static function for(string $model, int|string|null $key): self
    {
        return new self(sprintf('%s #%s has a deletion waiting for approval and cannot be changed.', class_basename($model), (string) $key));
    }
}
```

In `HasApprovals`, first thing in the `saving` listener (before capture):

```php
            if ($item->exists && ! $item->isForcedApprovalUpdate() && $item->pendingDeletionStrategy() === PendingDeletionStrategy::Block) {
                $blocked = array_diff(array_keys($item->getDirty()), $item->attributesWritableWhilePendingDeletion(), [$item->getUpdatedAtColumn()]);

                if ($blocked !== [] && $item->modifications()->activeOnly()->whereIn('operation', [Operation::Delete->value, Operation::ForceDelete->value])->exists()) {
                    throw PendingDeletionLock::for($item::class, $item->getKey());
                }
            }
```

plus

```php
    public function pendingDeletionStrategy(): PendingDeletionStrategy
    {
        return PendingDeletionStrategy::Block;
    }

    /**
     * @return list<string>
     */
    public function attributesWritableWhilePendingDeletion(): array
    {
        return [];
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withoutPendingDeletion(Builder $query): void
    {
        $query->whereDoesntHave('modifications', static fn (Builder $modifications): Builder => $modifications
            ->where('active', true)
            ->whereIn('operation', [Operation::Delete->value, Operation::ForceDelete->value]));
    }
```

and in `bootHasApprovals()`:

```php
        static::addGlobalScope('hide_pending_deletion', static function (Builder $query): void {
            $model = $query->getModel();
            $user = Auth::user();

            if ($model->pendingDeletionStrategy() !== PendingDeletionStrategy::Hide) {
                return;
            }

            if ($user instanceof User && ($user->isSuperAdmin() || $user->can(PermissionName::forModel($model, 'approve')) || $user->can(PermissionName::forModel($model, 'disapprove')))) {
                return;
            }

            $query->withoutPendingDeletion();
        });
```

- [ ] **Step 4: Run** the strategy tests and the approvals suites. Expected: PASS.

- [ ] **Step 5: Pint and commit** (`feat(core): a pending deletion blocks the record, or hides it per model`).

The global scope keys on the table's `approve`/`disapprove` permission, as the spec requires: whoever has to decide always sees the record.

---

## Phase 4 — Withdrawal and events

### Task 8: Withdrawal, `ModificationApproved`/`ModificationRejected`/`ModificationWithdrawn` fired by the service

**Files:**
- Create: `Modules/Core/app/Approvals/Events/ModificationRejected.php`, `Modules/Core/app/Approvals/Events/ModificationWithdrawn.php`
- Modify: `Modules/Core/app/Services/ModificationVoteService.php`, `Modules/CMS/app/Models/Comment.php:196` (stop firing `ModificationApproved` itself)
- Test: `Modules/Core/tests/Integration/Approvals/WithdrawalTest.php`, `Modules/Core/tests/Integration/Approvals/DecisionEventsTest.php`

**Interfaces:**
- Produces: `ModificationVoteService::withdraw(User $user, Modification $modification): void` throwing `AuthorizationException` (not the author) or `LogicException` (not active); `final readonly class ModificationRejected { public function __construct(public Modification $modification, public Model $modifiable) {} }`, same shape for `ModificationWithdrawn` (modifiable may be `null` for a create) — `ModificationApproved` stays `Modules\Core\Events\ModificationApproved` and is now fired by the service for every model.

- [ ] **Step 1: Write the failing tests**

```php
it('lets the author withdraw a request with votes already cast', function (): void {
    Auth::login($this->author);
    $this->record->delete();
    $modification = $this->record->pendingModification();
    $modification->update(['approvers_required' => 2]);
    resolve(ModificationVoteService::class)->cast($this->approver, $modification, true);

    resolve(ModificationVoteService::class)->withdraw($this->author, $modification);

    expect(Modification::query()->find($modification->id))->toBeNull()
        ->and(Approval::query()->where('modification_id', $modification->id)->exists())->toBeFalse()
        ->and($this->record->fresh()->trashed())->toBeFalse();
});

it('refuses a withdrawal from anybody but the author', function (): void {
    Auth::login($this->author);
    $this->record->delete();

    expect(fn () => resolve(ModificationVoteService::class)->withdraw($this->approver, $this->record->pendingModification()))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('refuses to withdraw a decided request', function (): void {
    Auth::login($this->author);
    $this->record->delete();
    $modification = $this->record->pendingModification();
    resolve(ModificationVoteService::class)->cast($this->approver, $modification, false);

    expect(fn () => resolve(ModificationVoteService::class)->withdraw($this->author, $modification->fresh()))
        ->toThrow(LogicException::class);
});
```

`DecisionEventsTest.php` (same `beforeEach`/`afterEach` as `OperationApplyTest.php`):

```php
it('fires one event per decision', function (): void {
    Event::fake([ModificationApproved::class, ModificationRejected::class, ModificationWithdrawn::class]);
    $service = resolve(ModificationVoteService::class);
    Auth::login($this->author);

    $this->record->update(['name' => 'first']);
    $service->cast($this->approver, $this->record->pendingModification(), true);

    $this->record->update(['name' => 'second']);
    $service->cast($this->approver, $this->record->pendingModification(), false);

    $this->record->update(['name' => 'third']);
    $service->withdraw($this->author, $this->record->pendingModification());

    Event::assertDispatchedTimes(ModificationApproved::class, 1);
    Event::assertDispatchedTimes(ModificationRejected::class, 1);
    Event::assertDispatchedTimes(ModificationWithdrawn::class, 1);
});
```

- [ ] **Step 2: Run to verify they fail.** Expected: FAIL (`withdraw` undefined).

- [ ] **Step 3: Implement**

```php
    public function withdraw(User $user, Modification $modification): void
    {
        throw_unless(
            $modification->modifier_type === $user::class && (string) $modification->modifier_id === (string) $user->getKey(),
            AuthorizationException::class,
            'Only the author can withdraw a request.',
        );
        throw_unless($modification->active, LogicException::class, 'A decided request cannot be withdrawn.');

        $modifiable = $modification->modifiable;

        $modification->getConnection()->transaction(static function () use ($modification): void {
            $modification->approvals()->delete();
            $modification->disapprovals()->delete();
            $modification->delete();
        });

        event(new ModificationWithdrawn($modification, $modifiable));
    }
```

In `cast()`, after applying: `event($approval ? new ModificationApproved($modification, $target) : new ModificationRejected($modification, $target));`. Remove `event(new ModificationApproved($modification, $this));` from `Comment::applyModificationChanges()`.

- [ ] **Step 4: Run** the new tests, `Modules/CMS/tests/Feature/CommentModerationTest.php`, `Modules/AI/tests/Feature` and the approvals suites. Expected: PASS.

- [ ] **Step 5: Pint and commit** in Core and CMS (`feat(core): authors withdraw their requests; decisions fire approved, rejected and withdrawn events`).

---

## Phase 5 — Surfaces

### Task 9: Panel — shared approval outcome for edit pages and actions, withdraw in Modifications

**Files:**
- Create: `Modules/Core/app/Filament/Concerns/ReportsApprovalOutcome.php`
- Modify: `Modules/Core/app/Filament/Resources/Settings/Pages/EditSetting.php` (use the concern, drop its own notification code), the edit pages of `ContentResource`, `CommentResource`, `PresetResource`, `FieldResource`, `SettingResource`
- Modify: `Modules/Core/app/Filament/Resources/Modifications/Tables/ModificationsTable.php` (withdraw action)
- Test: `Modules/Core/tests/Feature/Filament/ApprovalOutcomeTest.php`

**Interfaces:**
- Consumes: `pendingModification()`, `wouldRequireApproval()`, `PendingDeletionLock`, `ModificationVoteService::withdraw()`.
- Produces: trait `ReportsApprovalOutcome` for `EditRecord` pages with `handleRecordUpdate()`, `getSavedNotification()`, and a static `approvalAwareDeleteAction(): DeleteAction` / `approvalAwareForceDeleteAction(): ForceDeleteAction` / `approvalAwareRestoreAction(): RestoreAction` builder.

- [ ] **Step 1: Write the failing tests** — move `editSettingActorWithoutApproval()` from `EditSettingFormTest.php` into `Modules/Core/tests/Support/HttpContext.php` (give it a `list<string> $actions = ['select', 'update', 'delete']` parameter and grant each `PermissionName::forModel(new Setting, $action)`), then:

```php
it('reports a save blocked by a pending deletion', function (): void {
    editSettingActorWithoutApproval();
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create(['type' => 'string', 'value' => 'x', 'choices' => null]);
    $setting->delete();

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->fillForm(['value' => 'y'])
        ->call('save')
        ->assertNotified('A deletion of this record is waiting for approval');

    expect($setting->fresh()->value)->toBe('x');
});

it('hides the withdraw action from anybody but the author', function (): void {
    editSettingActorWithoutApproval();
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create(['type' => 'string', 'value' => 'x', 'choices' => null]);
    $setting->delete();
    $modification = $setting->pendingModification();

    editSettingActorWithoutApproval();

    Livewire::test(ListModifications::class)->assertTableActionHidden('withdraw', $modification);
});
```

```php
it('labels and reports a deletion that needs approval', function (): void {
    editSettingActorWithoutApproval();
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create(['type' => 'string', 'value' => 'x', 'choices' => null]);

    Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
        ->assertActionHasLabel(DeleteAction::class, 'Request deletion')
        ->callAction(DeleteAction::class)
        ->assertNotified('Deletion sent for approval');

    expect($setting->fresh())->not->toBeNull()
        ->and($setting->modifications()->activeOnly()->sole()->operation)->toBe(Operation::Delete);
});

it('offers the author a withdraw action in Modifications', function (): void {
    $author = editSettingActorWithoutApproval();
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create(['type' => 'string', 'value' => 'x', 'choices' => null]);
    $setting->delete();
    $modification = $setting->pendingModification();

    Livewire::test(ListModifications::class)
        ->assertTableActionVisible('withdraw', $modification)
        ->callTableAction('withdraw', $modification);

    expect(Modification::query()->find($modification->id))->toBeNull();
});
```

- [ ] **Step 2: Run to verify they fail.**

- [ ] **Step 3: Implement the concern**

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Concerns;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Approvals\Operation;
use Modules\Core\Approvals\PendingDeletionLock;
use Override;

/**
 * Edit pages of models with approvals: a save or delete that is captured says so instead of
 * reporting a write that did not happen.
 */
trait ReportsApprovalOutcome
{
    private bool $sentForApproval = false;

    public static function approvalAwareDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label(static fn (Model $record): string => method_exists($record, 'wouldRequireApproval') && $record->wouldRequireApproval(Operation::Delete) ? 'Request deletion' : 'Delete')
            ->action(static function (DeleteAction $action, Model $record): void {
                $deleted = $record->delete();

                if (! $deleted && method_exists($record, 'pendingModification') && $record->pendingModification() !== null) {
                    Notification::make()->warning()->title('Deletion sent for approval')->send();
                    $action->halt();
                }

                $action->success();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill($data);

        try {
            $this->sentForApproval = $record->isDirty() && ! $record->save();
        } catch (PendingDeletionLock) {
            Notification::make()->danger()->title('A deletion of this record is waiting for approval')->send();
            $this->halt();
        }

        if ($this->sentForApproval) {
            $record->refresh();
        }

        return $record;
    }

    #[Override]
    protected function getSavedNotification(): ?Notification
    {
        if (! $this->sentForApproval) {
            return parent::getSavedNotification();
        }

        return Notification::make()
            ->warning()
            ->title('Change sent for approval')
            ->body('The record keeps its current values until the change is approved in Modifications.');
    }
}
```

`approvalAwareForceDeleteAction()` and `approvalAwareRestoreAction()` follow the same shape with `Operation::ForceDelete`/`forceDelete()`/"Request permanent deletion"/"Permanent deletion sent for approval" and `Operation::Restore`/`restore()`/"Request restore"/"Restore sent for approval". Each edit page listed above uses the trait and returns these builders from `getHeaderActions()` in place of `DeleteAction::make()`, `ForceDeleteAction::make()`, `RestoreAction::make()`. `EditSetting` keeps only `use ReportsApprovalOutcome;` and the resource property.

In `ModificationsTable`, next to approve/disapprove:

```php
            Action::make('withdraw')
                ->label('Withdraw')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(static fn (Modification $record): bool => $record->active
                    && Auth::user() instanceof User
                    && $record->modifier_type === Auth::user()::class
                    && (string) $record->modifier_id === (string) Auth::id())
                ->action(static fn (Modification $record) => resolve(ModificationVoteService::class)->withdraw(Auth::user(), $record)),
```

- [ ] **Step 4: Run** the new tests and `Modules/Core/tests/Feature/Filament`, `Modules/CMS/tests/Feature/Filament`. Expected: PASS.

- [ ] **Step 5: Pint and commit** in Core and CMS (`feat(core): the panel reports captured writes and lets authors withdraw them`).

### Task 10: CRUD API — `202` for captured writes, `409` for blocked records, withdraw endpoint

**Files:**
- Modify: `Modules/Core/app/Services/Crud/CrudService.php` (`insert`, `update`, `delete`, `doActivateOperation`, new `withdraw`)
- Modify: `Modules/Core/app/Http/Controllers/CrudController.php` (`handleServiceCall` catches `PendingDeletionLock` → 409, `AuthorizationException` → 403 is already mapped; new `withdraw` action)
- Modify: `Modules/Core/routes/web.php` (`Route::patch('/withdraw/{module}/{entity}', 'withdraw')->name('withdraw');`)
- Test: `Modules/Core/tests/Feature/Controllers/CrudApprovalOutcomeTest.php`

**Interfaces:**
- Produces: a captured write answers `CrudResult(data: ['modification' => $modification->id, 'operation' => $operation->value], statusCode: Response::HTTP_ACCEPTED)`; `CrudService::withdraw(ModifyRequestData $requestData): CrudResult`.

- [ ] **Step 1: Write the failing tests** (follow the setup of `Modules/Core/tests/Feature/Controllers/CrudPendingApprovalsTest.php` for routes, `CrudApiExposure::runEnabled()` and a non-approver with `update`/`delete` on the entity):

```php
it('answers 202 with the modification when an update is captured', function (): void {
    $response = $this->patchJson(route('core.crud.update', ['module' => 'core', 'entity' => 'taxonomies']), ['id' => $this->taxonomy->id, 'name' => 'changed']);

    $response->assertStatus(202)->assertJsonPath('data.operation', 'update');
});

it('answers 202 when a delete is captured and 409 when the blocked record is then updated', function (): void {
    $this->deleteJson(route('core.crud.delete', ['module' => 'core', 'entity' => 'taxonomies']), ['id' => $this->taxonomy->id])
        ->assertStatus(202)->assertJsonPath('data.operation', 'force_delete');

    $this->patchJson(route('core.crud.update', ['module' => 'core', 'entity' => 'taxonomies']), ['id' => $this->taxonomy->id, 'name' => 'changed'])
        ->assertStatus(409);
});

it('lets only the author withdraw through the API', function (): void {
    $modification_id = $this->patchJson(route('core.crud.update', ['module' => 'core', 'entity' => 'taxonomies']), ['id' => $this->taxonomy->id, 'name' => 'changed'])
        ->assertStatus(202)->json('data.modification');

    $this->actingAs($this->other_writer)
        ->patchJson(route('core.crud.withdraw', ['module' => 'core', 'entity' => 'taxonomies']), ['id' => $this->taxonomy->id, 'modification' => $modification_id])
        ->assertStatus(403);

    $this->actingAs($this->author)
        ->patchJson(route('core.crud.withdraw', ['module' => 'core', 'entity' => 'taxonomies']), ['id' => $this->taxonomy->id, 'modification' => $modification_id])
        ->assertOk();

    expect(Modification::query()->find($modification_id))->toBeNull();
});
```

`beforeEach` creates `$this->author` and `$this->other_writer`, both with `select`, `update`, `delete` on taxonomies and without `approve`, acts as `$this->author`, and calls `pretendHttpRequest()`.

- [ ] **Step 2: Run to verify they fail.**

- [ ] **Step 3: Implement**

A private helper in `CrudService`:

```php
    private function capturedResult(Model $model): ?CrudResult
    {
        $modification = method_exists($model, 'pendingModification') ? $model->pendingModification() : null;

        return $modification === null ? null : new CrudResult(
            data: ['modification' => $modification->getKey(), 'operation' => $modification->operation->value],
            statusCode: Response::HTTP_ACCEPTED,
        );
    }
```

`insert()`: build with `$created = $model->newInstance($changes); $created->save();` then `if ($captured = $this->capturedResult($created)) { return $captured; }`. `update()`, `delete()`, `doActivateOperation()`: after each single-record write, when the write returned false, return the captured result for that record (for multi-record requests collect the captured modifications into `data['modifications']`, status 202 when at least one was captured). `withdraw()`: same lookup as `doApproveOperation()` for the modification, then `resolve(ModificationVoteService::class)->withdraw($user, $modification)`, returning `new CrudResult(data: ['withdrawn' => $modification->getKey()])`. In `CrudController::handleServiceCall()` add `catch (PendingDeletionLock $ex)` → `Response::HTTP_CONFLICT`, and `catch (LogicException $ex)` for "A decided request cannot be withdrawn." → `HTTP_CONFLICT` (catch it in `withdraw()` only, not globally).

- [ ] **Step 4: Run** the new test and `Modules/Core/tests/Feature/Controllers`, `Modules/Core/tests/Integration/Services/CrudServiceRequestScenariosTest.php`. Expected: PASS.

- [ ] **Step 5: Pint and commit** (`feat(core): the CRUD API answers 202 for writes sent to approval`).

### Task 11: AI tools report writes sent for approval

**Files:**
- Modify: `Modules/AI/app/Services/Tools/CrudToolProvider.php:757,775,793` (`present()` carries the status)
- Test: `Modules/AI/tests/Feature/Tools/CrudToolProviderApprovalTest.php`

- [ ] **Step 1: Write the failing test** (setup as in `Modules/AI/tests/Feature/Tools/CrudToolProviderTest.php` for building the provider and exposing `core.taxonomy` with `update`; the acting user has `select` and `update` on taxonomies, not `approve`; call `pretendHttpRequest()`):

```php
it('reports an update sent for approval instead of the updated record', function (): void {
    $result = $this->tools['update']($this->taxonomy->id, ['name' => 'changed']);

    expect($result['status'])->toBe('pending_approval')
        ->and($result['operation'])->toBe('update')
        ->and($result['modification'])->toBeInt()
        ->and($result)->not->toHaveKey('data')
        ->and($this->taxonomy->fresh()->name)->not->toBe('changed');
});
```

If the existing test file names the tool closure differently, use its name; the assertions stay the same.

- [ ] **Step 2: Run to verify it fails.**

- [ ] **Step 3: Implement** in `present()`:

```php
        if ($result->statusCode === Response::HTTP_ACCEPTED && is_array($result->data)) {
            return ['request' => $request, 'status' => 'pending_approval', ...$result->data];
        }
```

and update the tool descriptions for `create`/`update`/`delete` (`CrudToolProvider.php:264` area) with: "When the entity requires approval the change is not applied: the result has status pending_approval and the id of the request."

- [ ] **Step 4: Run** the new test and `Modules/AI/tests/Feature/Tools`. Expected: PASS.

- [ ] **Step 5: Pint and commit** in AI (`feat(ai): CRUD tools report writes sent for approval`).

---

## Phase 6 — Dependency removal

### Task 12: Remove every remaining reference to the package

**Files:**
- Delete: `Modules/Core/config/approval.php`
- Modify: any file still importing `Approval\` (`command grep -rn "Approval\\\\" Modules app config --include=*.php | command grep -v "Modules\\\\Core\\\\Approvals"` must print nothing)
- Test: `Modules/Core/tests/Unit/Architecture/NoApprovalPackageTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('references nothing from the laravel-approval package', function (): void {
    $offenders = [];

    foreach ((new Finder)->files()->in(base_path('Modules'))->name('*.php')->exclude(['vendor', 'node_modules']) as $file) {
        if (preg_match('/\\bApproval\\\\(Models|Traits)\\\\/', $file->getContents()) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
```

- [ ] **Step 2: Run, fix what it lists, delete `config/approval.php`, rerun.** Expected: PASS.

- [ ] **Step 3: Commit** (`chore(core): nothing references the laravel-approval package any more`).

### Task 13: Remove the Composer dependency — ask first

- [ ] **Step 1: Ask the user** for explicit approval to run `composer remove stephenlake/laravel-approval`. Stop here until they say yes.
- [ ] **Step 2:** `composer remove stephenlake/laravel-approval --no-interaction`
- [ ] **Step 3: Run the full suites** of Core, CMS, AI and the root `tests` (`php artisan test --compact Modules/Core/tests` etc.). Expected: only the failures already known before this plan (`RouteServiceProviderTest`, `ElasticsearchServiceTest`, `DatabaseConnectionAffinityTest`).
- [ ] **Step 4: Commit** `composer.json`, `composer.lock` in the root repository (`chore: drop stephenlake/laravel-approval, now part of Core`).

---

## Phase 7 — Documentation and closure

### Task 14: Module documentation and plan closure

**Files:**
- Modify: `Modules/Core/docs/rag/MODULE.md` (approvals paragraph), `Modules/Core/docs/rag/EVENT_ORCHESTRATION.md` and `Modules/Core/docs/EVENT_ORCHESTRATION.md` (new events), `Modules/CMS/docs/rag/COMMENT_MODERATION.md` (comment author deletion), `Modules/AI/docs/MODERATION.md` (events), `docs/rag/` user guide page on approvals if one exists (`command grep -rli approval docs/rag`)
- Modify: `docs/superpowers/specs/INDEX.md` (status **Implemented**), `docs/superpowers/plans/INDEX.md` (entry), this plan (`## Delivery status (YYYY-MM-DD): …` and `**Documented in:**`)
- Test: `tests/Unit/ClosedPlansPointToDocumentationTest.php` (existing)

- [ ] **Step 1: Write the module documentation** — what each operation does when captured, Block/Hide, writable attributes, withdrawal, the outcome on panel/API/tools, and the documented limit (mass query updates bypass approvals).
- [ ] **Step 2: Close the plan** with the delivery status (record any divergence from this plan there) and the `**Documented in:**` line naming the files of Step 1.
- [ ] **Step 3: Run** `php artisan test --compact tests/Unit/ClosedPlansPointToDocumentationTest.php`. Expected: PASS.
- [ ] **Step 4: Commit** docs in each module and the root (`docs: approvals cover deletes and restores`).
