# Core Approvals Subsystem Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Approvals cover deletes, force deletes and restores as well as creates and updates, with the approval mechanism moved from `stephenlake/laravel-approval` into Core.

**Architecture:** `Modules\Core\Models\Concerns\HasApprovals` becomes the only trait: it listens to `saving`, `deleting` and `restoring`, captures a `Modification` carrying an `Operation`, and cancels the Eloquent operation. `ModificationVoteService` is the single place that votes, applies an approved operation, rejects and withdraws. Panel, CRUD API and AI tools read the captured outcome from the model instead of assuming the write happened.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest, nwidart modules (`Modules/Core`, `Modules/CMS`, `Modules/AI`).

**Spec:** `docs/superpowers/specs/2026-09-27-core-approvals-subsystem-design.md`

## Settled decisions (2026-09-28)

A review of this plan against the working tree found four open design questions. They are settled
here and the tasks below already reflect them; the spec records them too.

1. **The author's automatic `approve` credit applies through `ModificationVoteService`, never on its
   own.** `applyAuthorApproveCredit()` currently calls `applyModificationChanges()` directly, so that
   path gets no transaction, fires no event, and would run `$this->delete()` from inside the
   `deleting` listener. The service gains `applyAuthorCredit()` and becomes the only place an
   approved operation is applied (Task 6).
2. **The operation reaches a model rule through its own hook, not through a fake diff.**
   `requiresApprovalForOperation(Operation $operation): bool` replaces the `['__operation' => …]`
   sentinel the first draft injected into `requiresApprovalWhen()`. No existing override receives an
   array that is not a diff (Task 5).
3. **`Hide` does not filter when nobody is authenticated.** Console, queues, jobs, search indexing
   and exports see every record, exactly as capture is skipped in console. A pending deletion must
   not silently change what background work sees (Task 7).
4. **Deleting a comment stays outside approvals, and says so.** `Comment::approvalOperations()`
   returns `[Operation::Create, Operation::Update]`. Today a comment delete escapes only because
   `Comment::requiresApprovalWhen()` returns false when `body` is not dirty, which is a side effect,
   not a decision (Task 5).
5. **The three tables carry the Core prefix.** Delivered ahead of Phase 1 by Core commit `c759afe4`:
   `CoreTables::Modifications`, `Approvals` and `Disapprovals` moved out of the "generic or vendors
   models" block and now read `core_modifications`, `core_approvals`, `core_disapprovals`. The spec's
   Data section already used those names while the enum still said `vend_*`; the migrations derive
   every name from the enum, so nothing else in the schema had to change. The developer database was
   renamed in place, indexes and foreign-key constraint names included.
6. **One diff hook, not two.** The spec's `enrichModificationDiff(array $diff): array` is not built:
   `CommentApprovalCapture` stays where it is, and the only thing `Comment` actually needed from a
   diff hook — folding the pending translated `body` and `rating_score` into the change set — is
   served by `getDirtyForApproval()`, which the trait's listener now calls (Task 2). The spec is
   amended to match.

Minor corrections folded into the tasks: the three lifecycle events all live in
`Modules\Core\Events`; `deleteWhenApproved`/`deleteWhenDisapproved` are removed rather than
defaulted to false; the panel action tests use `Content`, because `SettingResource` denies delete and
force delete; `composer remove` targets `Modules/Core/composer.json`; the architecture test resolves
paths with `dirname(__DIR__, 5)` like its siblings.

## Known pre-existing failures

Measured 2026-09-28, per module, after Task 3: **5783 passed, 28 skipped, 2 failed**, neither caused by
this plan.

| Suite | Result |
|---|---|
| `tests` (root) | 23 passed |
| `Modules/Core/tests` | green |
| `Modules/CMS/tests` | 644 passed, 1 skipped |
| `Modules/AI/tests` | **3 failed**, 755 passed — `ChatTest` answers 404 where 201/422/200 are expected. Routing, nothing to do with approvals |
| `Modules/ERP/tests` | **1 failed**, 644 passed — `ERPFilamentRouteSmokeTest` answers 500 |
| `Modules/MES/tests` | 127 passed |
| `Modules/SAO/tests` | 675 passed, 1 skipped |

**The whole suite does not fit in one process.** `php artisan test` with no argument dies with
`Allowed memory size of 2147483648 bytes exhausted` inside `vendor/dg/bypass-finals/src/NativeWrapper.php`,
around `CMS/tests/Feature/Models/CategoryTest`, after some 1400 tests. Run it per module, one process
each, or there is no full result to read. This matters for Task 13, whose expected outcome is phrased
as if a single full run existed.

Closed while executing Tasks 1 to 3, all pre-existing, none of them regressions:

- **six** Mockery `User` partials that never stubbed `isSuperAdmin()`, so the superadmin rule commit
  `83e0795b` added resolved the `roles` relation on a never-persisted model:
  `HasApprovalsTest` (3), `ApproveQuorumOnWriteTest` (1), `FieldApprovalTest` (2). The house pattern was
  already there to copy — `HasValidationsBehaviorTest` and `EnvironmentIndicatorPluginTest` stub it;
- the `HasTable` argument type error, fixed at its cause (a Core module importing `App\Models\User`);
- two `NOT NULL constraint failed: core_settings.description`, in `ListAndSelectRequestDataTest` and
  `CheckPendingApprovalsCommandTest`. `Setting::$attributes` defaults five of the six NOT NULL columns
  and not `description`, so any `Setting::create()` that omits it fails. Fixed in the tests, which now
  pass one. **The model-level gap is left open on purpose**: giving `description` a default there is a
  decision about settings, not about approvals.

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`; explicit parameter and return types; `#[Override]` on overrides; code, comments and PHPDoc in English.
- No new migrations for existing data: change `Modules/Core/database/migrations/2024_03_30_161448_create_modifications_table.php` in place and rebuild the developer database with `php artisan migrate:fresh --seed`. The project has no other installation and pending modifications are development data. Do not convert a live database by hand: that is what left `core_settings` without `is_internal` until 2026-09-28, because the create migrations are the only description of the schema.
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
| `Modules/Core/app/Events/ModificationRejected.php`, `ModificationWithdrawn.php` | new events, next to the existing `ModificationApproved` |
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

**Delivered 2026-09-28.** Three things had to come up from Task 2, because the moment the Core models
stop extending the package's the suite cannot be green without them:

- `HasApprovals::applyModificationChanges()`. The package's version type-hints
  `\Approval\Models\Modification`, and `ModificationVoteService` passes a Core one. Brought over
  faithfully (with `settleModification()` extracted for the delete-or-deactivate branch); the
  `deleteWhen*` behaviour is unchanged here and goes in Task 6.
- `User::approve()` and `User::disapprove()`, delegating to `ModificationVoteService`. Same type-hint
  problem in `ApprovesChanges`, reached from `ApproveModificationJob::castApproval()` and
  `castDisapproval()`. `use ApprovesChanges;` itself stays until Task 2, now shadowed.
- `HasApprovals::modifications()` drops the `config('approval.models.modification')` lookup. It
  guarded the configured class with `is_a(…, ApprovalModification::class)`, which is now false, so it
  would have silently fallen back to the package model and its non-existent `modifications` table.

Two test updates the plan did not foresee: the stub in `CrudServiceRequestScenariosTest` type-hinted
the package model in its own `applyModificationChanges()`, and two assertions there read
`Modification::query()->…->value('active')` expecting `1`. Eloquent's `value()` hydrates the model, so
the new `active` boolean cast makes that `true`; the raw query-builder assertions next to them
correctly still expect `1`.

**Files:**
- Create: `Modules/Core/LICENSES/laravel-approval.md`
- Modify: `Modules/Core/app/Models/Modification.php`, `Modules/Core/app/Models/Approval.php`, `Modules/Core/app/Models/Disapproval.php`
- Modify: `Modules/Core/README.md` (section "Third-party code")
- Test: `Modules/Core/tests/Integration/Models/ModificationModelTest.php`

**Interfaces:**
- Produces: `Modification::approvals(): HasMany`, `Modification::disapprovals(): HasMany`, `Modification::modifiable(): MorphTo`, `Modification::modifier(): MorphTo`, accessors `approversRemaining`, `disapproversRemaining`, scopes `activeOnly()`, `inactiveOnly()`; `Approval::modification()`, `Approval::approver()`; `Disapproval::modification()`, `Disapproval::disapprover()`. None of them extends an `Approval\Models\*` class any more.

- [x] **Step 1: Write the failing test**

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

- [x] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Models/ModificationModelTest.php`
Expected: FAIL on the first test (`get_parent_class` is `Approval\Models\Modification`).

- [x] **Step 3: Make the three models stand alone**

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

- [x] **Step 4: Write the attribution**

Partly delivered ahead of this task by Core commit `d6874436`: `LICENSES/laravel-approval.md` and the
README entry exist. What remains here is the per-file header line, which only makes sense once Step 3
has made the models stand alone. The README entry carries a note saying the package is still
required; remove that note in Task 13, not before.

`Modules/Core/LICENSES/laravel-approval.md`, same layout as `LICENSES/laravel-locked.md`: title `# cloudcake/laravel-approval (published as stephenlake/laravel-approval)`, source `https://github.com/cloudcake/laravel-approval`, "Derived into Core from version 1.1.4, upstream commit e7527e1.", the list of derived files (`app/Models/Modification.php`, `app/Models/Approval.php`, `app/Models/Disapproval.php`, `app/Models/Concerns/HasApprovals.php`, `app/Services/ModificationVoteService.php`), then the license text copied verbatim from `vendor/stephenlake/laravel-approval/LICENSE.md` inside a fenced block. Add the header line from the Global Constraints to each derived file's class PHPDoc. In `Modules/Core/README.md`, add to the "derived code" list:

```markdown
-   [cloudcake/laravel-approval](https://github.com/cloudcake/laravel-approval): approvals (`HasApprovals`, `Modification`, `ModificationVoteService`), see [`LICENSES/laravel-approval.md`](LICENSES/laravel-approval.md)
```

- [x] **Step 5: Run the new test and the approvals suite**

Run: `php artisan test --compact Modules/Core/tests/Integration/Models/ModificationModelTest.php Modules/Core/tests/Integration/Models Modules/Core/tests/Feature/Filament/ModificationResourceTest.php Modules/Core/tests/Feature/Controllers/CrudPendingApprovalsTest.php Modules/CMS/tests/Feature/CommentModerationTest.php Modules/AI/tests/Feature/Jobs/ApproveModificationJobTest.php`
Expected: PASS.

- [x] **Step 6: Pint and commit**

```bash
vendor/bin/pint --format agent Modules/Core/app/Models/Modification.php Modules/Core/app/Models/Approval.php Modules/Core/app/Models/Disapproval.php Modules/Core/tests/Integration/Models/ModificationModelTest.php
git -C Modules/Core add LICENSES/laravel-approval.md README.md app/Models tests/Integration/Models/ModificationModelTest.php
git -C Modules/Core commit -m "refactor(core): approval models stand alone, attributed to laravel-approval"
```

### Task 2: `HasApprovals` without the package trait, `User` without `ApprovesChanges`

**Files:**
- Modify: `Modules/Core/app/Models/Concerns/HasApprovals.php`
- Modify: `Modules/Core/app/Models/User.php` (drop `use ApprovesChanges;`)
- Modify: `Modules/CMS/app/Models/Comment.php` (delete its own `bootRequiresApproval()`, `getDirtyForApproval()` becomes `protected`)
- Modify: `Modules/Core/app/Console/PermissionsRefreshCommand.php:12,199` and `Modules/Core/app/Services/Crud/CrudService.php:7,2171` (the trait both of them test for), plus the stubs in `Modules/Core/tests/Integration/Services/CrudServiceRequestScenariosTest.php:6` and `CrudServiceTest.php:5`
- Modify: `Modules/AI/app/Jobs/ApproveModificationJob.php:198,216` (nothing to do: Task 1 made `User::approve()`/`disapprove()` delegate to the service, so the job already votes through it — check and move on)
- Test: `Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php` (add cases), `Modules/CMS/tests/Feature/CommentModerationTest.php` (must stay green)

**Interfaces:**
- Consumes: Task 1 models.
- Produces on every `HasApprovals` model: `protected int $approversRequired = 1`, `protected int $disapproversRequired = 1`, `protected bool $updateWhenApproved = true`, `isForcedApprovalUpdate(): bool`, `setForcedApprovalUpdate(bool $forced = true): void`, `modifications(): MorphMany<Modification, $this>`, `applyModificationChanges(Modification $modification, bool $approved): void`, `static captureSave(Model $item): bool`, `protected requiresApprovalWhen(array $modifications): bool` (superadmin rule included), `protected getDirtyForApproval(): array` (default `getDirty()`).

- [x] **Step 1: Write the failing test** (append to `HasApprovalsTest.php`)

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

**Delivered 2026-09-28 as a PSR-4 class, not a global function.** `Modules/Core/tests/Support/` is
already `Modules\Core\Tests\Support\` in Core's `autoload-dev`, which the merge plugin makes
reachable from the CMS and AI suites too, so `HttpContext::pretendHttpRequest()` needs no `require` in
any module's `Pest.php` and adds no global. Later tasks call it as
`Modules\Core\Tests\Support\HttpContext::pretendHttpRequest()`, not `pretendHttpRequest()`.

The body is the one the plan drafted:

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

- [x] **Step 2: Run to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php`
Expected: FAIL on "no longer relies on the laravel-approval trait".

- [x] **Step 3: Bring the package behaviour into `HasApprovals`**

Remove `use RequiresApproval;` and the `Approval\...` imports; the trait now declares what the package trait provided:

```php
    protected int $approversRequired = 1;

    protected int $disapproversRequired = 1;

    protected bool $updateWhenApproved = true;

    protected bool $deleteWhenApproved = true;

    protected bool $deleteWhenDisapproved = false;

    private bool $forcedApprovalUpdate = false;

    public static function bootHasApprovals(): void
    {
        static::saving(static function (Model $item): ?bool {
            if (! $item->isForcedApprovalUpdate() && $item->requiresApprovalWhen($item->getDirtyForApproval()) === true) {
                return static::captureSave($item);
            }

            $item->setForcedApprovalUpdate(false);

            return null;
        });
    }

    /**
     * The change set a capture decides on. `getDirty()` for most models; a model whose
     * write goes through a staging area (translations, pending scores) folds it in here.
     *
     * @return array<string, mixed>
     */
    protected function getDirtyForApproval(): array
    {
        return $this->getDirty();
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

`captureSave()` keeps its body, with `getDirtyForApproval()` in place of `getDirty()` when it builds the diff; the modifier becomes `Auth::user()` (it was the package's `modifier()`), and the `config('approval.models.modification', …)` lookups become `Modification::class`. `requiresApprovalWhen()` keeps the superadmin rule already in the working tree. Change every `applyModificationChanges(\Approval\Models\Modification …)` signature in models (`Comment`) to `Modification`.

**Two runtime checks ask whether a model has approvals by naming the package trait, and both go
silently false in this step.** `class_uses_trait()` (`Modules/Core/app/Helpers/helpers.php:646`) is
recursive by default, so today a model using `HasApprovals` matches `RequiresApproval::class` because
`HasApprovals` uses it. Remove `use RequiresApproval;` and nothing matches any more:

- `Modules/Core/app/Console/PermissionsRefreshCommand.php:199` stops registering the `approve`
  permission for every model with approvals, so the permission vocabulary silently loses it;
- `Modules/Core/app/Services/Crud/CrudService.php:2171` stops recognising a model as approval-bearing,
  so the CRUD API stops treating captured writes as captured.

Both must switch to `HasApprovals::class`. Neither failure raises anything: no exception, no type
error, just permissions that stop existing and an API that reports writes that did not happen. Convert
the two anonymous stub models that use `RequiresApproval` directly in the same step
(`CrudServiceRequestScenariosTest.php:6`, `CrudServiceTest.php:5`), or the switch changes what those
tests exercise without failing them.

**`Comment` must lose its own boot listener in this step, or it silently stops working.** The `saving` listener comes from the package's `bootRequiresApproval()`, and `Modules/CMS/app/Models/Comment.php:252-269` overrides that method to compute `getDirtyForApproval()` (pending translated `body`, pending `rating_score`) before capturing. Renaming the trait hook to `bootHasApprovals()` leaves `Comment::bootRequiresApproval()` with nothing calling it: Laravel boots `boot{TraitName}`, and `RequiresApproval` is gone. The comment would then be captured on the bare `getDirty()`, losing the pending body — a green suite would hide it, so do not rely on the rename alone. Delete `Comment::bootRequiresApproval()` and make `getDirtyForApproval()` `protected` so it overrides the trait's version; the trait's listener then does the same work for every model. `Comment::captureSave()` keeps delegating to `CommentApprovalCapture::capture()`.

Two things found while doing it (2026-09-28):

- `Comment::booted()` called `self::bootRequiresApproval()` **by hand**, on top of the automatic call
  Laravel makes for every `boot{TraitName}`, so the capture listener was registered twice. Deleting the
  override without deleting `booted()` is a fatal call to an undefined method; deleting both is also
  the fix for the double registration. `Comment` now has no `booted()`.
- `#[Override]` does not belong on `getDirtyForApproval()`. PHP validates that attribute against parent
  classes and interfaces only, never against a trait the same class uses, so it is a compile-time
  fatal there. The house style agrees: `Comment::requiresApprovalWhen()` and
  `Content::requiresApprovalWhen()` override trait methods and carry no `#[Override]`.

`Modules/Core/tests/Integration/Models/UserTest.php` asserted `ApprovesChanges` among the user's
traits. It now asserts the two methods that replaced it, `approve()` and `disapprove()`.

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

- [x] **Step 4: Run the approvals suites**

Run: `php artisan test --compact Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php Modules/Core/tests/Integration/Models Modules/Core/tests/Feature/Models Modules/Core/tests/Feature/Controllers Modules/Core/tests/Feature/Filament/ModificationResourceTest.php Modules/CMS/tests/Feature Modules/AI/tests/Feature/Jobs Modules/AI/tests/Feature/ApproveModificationJobTest.php Modules/AI/tests/Feature/ModificationModerationListenerTest.php`
Expected: PASS.

- [x] **Step 5: Pint and commit** (Core, then CMS for `Comment`, then AI if the job changed)

```bash
vendor/bin/pint --format agent Modules/Core/app/Models/Concerns/HasApprovals.php Modules/Core/app/Models/User.php Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php Modules/Core/tests/Support/HttpContext.php Modules/CMS/app/Models/Comment.php
git -C Modules/Core add -A app tests && git -C Modules/Core commit -m "refactor(core): HasApprovals carries the approval behaviour itself"
git -C Modules/CMS add app/Models/Comment.php && git -C Modules/CMS commit -m "refactor(cms): comment approvals use the Core modification type"
```

### Task 3: Settings save through the shared write rule

**Steps 2 and 3 were done on 2026-09-28, before Task 2**, to get a green baseline: Task 2 removes the
package trait and the superadmin rule these very tests exercise, and with a red baseline a new break
would be indistinguishable from the old one. Step 1 followed Task 2, since it needs the helper that
task introduces. The whole task is closed: the two local helpers
(`settingWrittenOverHttp()` in `SettingTest`, `settingFormOverHttp()` in `EditSettingFormTest`) are
gone, replaced by `HttpContext::pretendHttpRequest()`, and the now-unused `App` facade imports went
with them. 33 passed, 2 skipped.

This closes Core commit `83e0795b`, committed as `wip` with one red test named in its message, not work left in a working tree: `Setting::requiresApprovalWhen()` checks its guarded fields, then defers to the trait; `EditSetting` reports a change sent for approval. Close it on top of Task 2. Nothing here needs to be written from scratch — read the commit first, then finish the two loose ends below.

**Files:**
- Modify: `Modules/Core/app/Models/Setting.php`, `Modules/Core/app/Filament/Resources/Settings/Pages/EditSetting.php` (already committed in `83e0795b`)
- Test: `Modules/Core/tests/Integration/Models/SettingTest.php`, `Modules/Core/tests/Feature/Filament/EditSettingFormTest.php` (already committed in `83e0795b`)

- [x] **Step 1: Switch the tests to `pretendHttpRequest()`**

Replace the local helpers `settingWrittenOverHttp()` (SettingTest) and `settingFormOverHttp()` (EditSettingFormTest) with calls to `pretendHttpRequest()` and delete the two local functions. In "sends an is_public change from the form to approval", replace `editSettingActor();` with `editSettingActorWithoutApproval();` and add `->assertNotified('Change sent for approval')` after `->assertHasNoFormErrors()`: a superadmin is never captured, so only a non-approver can reach approval.

- [x] **Step 2: Diagnose the red tests the wip left behind**

The commit message names one: `EditSettingFormTest` "offers no create or delete actions on settings".
There are four more, and they come from the same change. `Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php`
fails three times, and `Modules/Core/tests/Integration/Models/ApproveQuorumOnWriteTest.php` once ("it
still requires approval when writer lacks approve credit even if…"), all with
`RelationNotFoundException: Call to undefined relationship [roles] on model
[Mockery_N_Modules_Core_Models_User]`, through `Modules/Core/app/Models/User.php:233` and
`Modules/Core/app/Authorization/ResolvingAuthorization.php:38`. The superadmin rule the wip added to
`requiresApprovalWhen()` calls `isSuperAdmin()`, which reads the `roles` relation, on partial mocks
that never stubbed it. Verified as pre-existing on 2026-09-28: the same four fail with the code at
`83e0795b`.

**Done.** Each mock now declares `$user->shouldReceive('isSuperAdmin')->andReturn(false);` next to its
`can` expectation. That is what each of those tests means — an actor who is not a superadmin and whose
approve credit is the thing under test — and it keeps them unit tests of `requiresApprovalWhen()`
instead of tests of role resolution, which a Mockery partial of a never-persisted Eloquent model
cannot do. The superadmin rule gets its own test, on a real user with a real role, in Task 2.

- [x] **Step 3: Diagnose "offers no create or delete actions on settings"**

It fails with `SettingsTable::loadUserPermissionsForTable(): Argument #1 ($user) must be of type ?App\Models\User, Modules\Core\Models\User given`. Use superpowers:systematic-debugging. Start from `Modules/Core/app/Filament/Utils/HasTable.php:139-142` (`Auth::user()` typed as `App\Models\User`) and find which guard or provider hands back a `Modules\Core\Models\User` in that test (`Filament::setCurrentPanel('admin')` switches the guard to `admin`; compare `config('auth.guards.admin.provider')`, `config('auth.providers.*.model')` and `LockAwareUserProvider`). Fix the cause, not the test. If the cause is `HasTable` typing the application user class while guards may return the Core base class, type the parameter as `?Modules\Core\Models\User` there.

**Done, and that was the cause.** `Modules/Core/app/Filament/Utils/HasTable.php` imported
`App\Models\User`: a Core module requiring the application's user class, while `Auth::user()` can
hand back the Core base class it extends. The import now points at `Modules\Core\Models\User`, which
accepts both and removes the Core to App dependency; no test was touched for this one.

`EditSettingFormTest` had a second red the wip message mentions only in passing: "sends an is_public
change from the form to approval" used `editSettingActor()`, a superadmin, whose writes are never
captured, so `modifications()->activeOnly()->sole()` found nothing. It now uses
`editSettingActorWithoutApproval()` (which already existed and already fakes an HTTP request), asserts
the "Change sent for approval" notification, and checks the setting kept its old value.

- [x] **Step 4: Run**

Run: `php artisan test --compact Modules/Core/tests/Integration/Models/SettingTest.php Modules/Core/tests/Feature/Filament/EditSettingFormTest.php Modules/Core/tests/Feature/Filament/SettingResourceTest.php Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php`
Expected: PASS.

- [x] **Step 5: Pint and commit**

```bash
vendor/bin/pint --format agent Modules/Core/app/Models/Setting.php Modules/Core/app/Filament/Resources/Settings/Pages/EditSetting.php Modules/Core/tests/Integration/Models/SettingTest.php Modules/Core/tests/Feature/Filament/EditSettingFormTest.php
git -C Modules/Core add -A app tests && git -C Modules/Core commit -m "fix(core): settings follow the shared approval write rule and say when a change waits for approval"
```

---

## Phase 2 — `operation` replaces `is_update`

### Task 4: `Operation` enum and column

**Delivered 2026-09-28.** Two things the plan could not have known:

- **`migrate:fresh` was already broken, project-wide.**
  `Modules/SAO/database/migrations/2026_08_16_100100_create_sao_signal_occurrences_table.php`
  declares a foreign key on `sao_releases`, created two hours later in migration order:
  `SQLSTATE[HY000]: General error: 1824 Failed to open the referenced table 'sao_releases'`. Nothing
  could be migrated from scratch, which is how a developer database drifts from the create migrations
  in the first place — the missing `is_internal` column of the same morning was the visible symptom.
  Fixed by moving `create_sao_releases_table` to `2026_08_16_100050`, between the signals table and
  the occurrences that reference both (SAO commit `96a3abe`). It only ever depended on `sao_projects`.
  **Run `migrate:fresh` on a throwaway database before running it on a developer one**: this one was
  left wiped and half-migrated between the two attempts.
- **The enum lives in `app/Approvals/`, not where `module:make-enum` puts it.** The generator writes
  `app/Enums/<path>` with a `Modules\Core\Enums\…` namespace; the spec asks for
  `Modules\Core\Approvals\Operation`, which is also how the module is actually laid out — Core keeps a
  subsystem's types inside it (`app/Locking/Exceptions/`, `app/Versioning/Data/`,
  `app/Inspector/Types/DoctrineTypeEnum.php`, itself an enum).

After the rebuild: `core_modifications` carries `operation` and no `is_update`, 322 settings seeded,
321 of them internal. The one that is not is `modules.active`, written by `ModuleDatabaseActivator`
through `DB::table()` so it works during boot — it bypasses the seeder, so `module` is null and
`is_internal` false, which is what those two columns are documented to mean.


**Files:**
- Create: `Modules/Core/app/Approvals/Operation.php`
- Modify: `Modules/Core/database/migrations/2024_03_30_161448_create_modifications_table.php`
- Modify: `Modules/Core/app/Models/Modification.php` (cast, hidden list), `Modules/Core/app/Models/Concerns/HasApprovals.php` (`captureSave`), `Modules/CMS/app/Services/CommentApprovalCapture.php:116`, `Modules/Core/app/Filament/Resources/Modifications/Schemas/ModificationForm.php:48-50`, `Modules/Core/app/Filament/Resources/Modifications/Tables/ModificationsTable.php` (operation column)
- Modify: every test creating a `Modification` with `'is_update' => …` (find them with `command grep -rln "'is_update'" Modules/*/tests`)
- Test: `Modules/Core/tests/Integration/Models/ModificationModelTest.php`

**Interfaces:**
- Produces: `enum Operation: string { Create = 'create'; Update = 'update'; Delete = 'delete'; ForceDelete = 'force_delete'; Restore = 'restore' }` with `isDeletion(): bool` (Delete, ForceDelete) and `carriesDiff(): bool` (Create, Update); `Modification::$operation` cast to `Operation`.

- [x] **Step 1: Write the failing test** (append to `ModificationModelTest.php`)

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

- [x] **Step 2: Run to verify it fails**

Run: `php artisan test --compact Modules/Core/tests/Integration/Models/ModificationModelTest.php`
Expected: FAIL (`Operation` class not found).

- [x] **Step 3: Implement**

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

- [x] **Step 4: Rebuild the developer database**

```bash
php artisan config:clear   # a cached config points the suite at the real MySQL database
php artisan migrate:fresh --seed
```

Expected: `core_modifications` has `operation` and no `is_update`. Pending modifications are not
preserved, and that is the decision: they are development data, and the create migrations are the
only description of the schema. Converting the live database by hand instead is what left
`core_settings` without `is_internal` until 2026-09-28.

If a pending modification in the developer database genuinely has to survive, convert it by hand
*before* rebuilding and re-create it after; do not make the hand conversion the normal path.

- [x] **Step 5: Run the approvals suites** (same command as Task 2 Step 4). Expected: PASS.

- [x] **Step 6: Pint and commit** in Core and CMS, message `refactor(core): modifications carry an operation instead of is_update`.

---

## Phase 3 — Deletes, force deletes and restores go through approval

### Task 5: Capture of every operation, outcome and read-only check

**Files:**
- Modify: `Modules/Core/app/Models/Concerns/HasApprovals.php`
- Modify: `Modules/CMS/app/Models/Content.php` (`requiresApprovalForOperation()` override), `Modules/CMS/app/Models/Comment.php` (`approvalOperations()`)
- Create: `Modules/Core/tests/Stubs/Approvals/SoftDeletableApprovalModel.php` (Core `SoftDeletes` + `HasApprovals`, table `approvals_soft_stub`, fillable `name`)
- Test: `Modules/Core/tests/Integration/Approvals/OperationCaptureTest.php`, `Modules/CMS/tests/Feature/CommentModerationTest.php` (a comment delete is not captured), `Modules/CMS/tests/Feature/Models/ContentModificationSoftKeepTest.php` (a draft is deleted directly, a live one is captured)

**Interfaces:**
- Consumes: `Operation` (Task 4).
- Produces: `approvalOperations(): list<Operation>` (overridable, default `Operation::cases()`), `pendingModification(): ?Modification`, `wouldRequireApproval(Operation $operation): bool`, `static captureOperation(Model $item, Operation $operation): bool`, `pendingOperationRequest(Operation $operation): ?Modification`, `protected requiresApprovalForOperation(Operation $operation): bool` (overridable per model), `protected approvalGate(): bool` (the shared console/superadmin/approve-credit rule, extracted so both entry points share it without either faking the other's argument).

- [x] **Step 1: Write the stub**

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

- [x] **Step 2: Write the failing tests**

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

- [x] **Step 3: Run to verify they fail**

Run: `php artisan test --compact Modules/Core/tests/Integration/Approvals/OperationCaptureTest.php`
Expected: FAIL (`pendingModification` undefined, deletes run).

- [x] **Step 4: Implement in `HasApprovals`**

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

            return $item->shouldCapture($operation) && $item->requiresApprovalForOperation($operation)
                ? static::captureOperation($item, $operation)
                : null;
        });

        static::restoring(static function (Model $item): ?bool {
            if (! $item->trashed()) {
                return null;
            }

            return $item->shouldCapture(Operation::Restore) && $item->requiresApprovalForOperation(Operation::Restore)
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
        return $this->shouldCapture($operation) && $this->requiresApprovalForOperation($operation);
    }

    /**
     * Whether this operation needs approval. Default: the shared rule, which knows nothing
     * about fields, so an operation without a diff needs no special case. A model overrides
     * this to exempt some states, as Content does for drafts.
     */
    protected function requiresApprovalForOperation(Operation $operation): bool
    {
        return $this->approvalGate();
    }

    /**
     * The shared write rule, with no change set in it: console never needs approval, a
     * superadmin never does, and a writer holding `approve` does not when one approval is
     * enough. Extracted so requiresApprovalWhen() and requiresApprovalForOperation() share
     * it without either having to fake the other's argument.
     */
    protected function approvalGate(): bool
    {
        if (App::runningInConsole()) {
            return false;
        }

        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isSuperAdmin()) {
            return false;
        }

        return ! ($user instanceof User && $this->writerHasApproveCredit($user) && $this->approversRequired <= 1);
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

`captureSave()` sets `$item->pendingModification = $modification;` before returning, and `requiresApprovalWhen()` becomes `$modifications === [] ? false : $this->approvalGate()`: the two entry points now share one rule and neither invents an argument for the other.

**No model override has to change for deletes and restores, which is the point of the hook.**
`Setting` exempts presentation-only fields: an operation carries no fields, so the default gate
decides and the override is not consulted. `Taxonomy` requires approval only when a validity date
moves: same reasoning, default gate. Only `Content` genuinely has per-state rules, so only `Content`
overrides the new hook, with the aliasing pattern it already uses for `requiresApprovalWhen`:

```php
        HasApprovals::requiresApprovalForOperation as private requiresApprovalForOperationTrait;
```

```php
    #[Override]
    protected function requiresApprovalForOperation(Operation $operation): bool
    {
        // Unpublished (draft) and expired: write-through, deletes and restores included.
        if (! $this->isPublished() && ! $this->isScheduled()) {
            return false;
        }

        return $this->requiresApprovalForOperationTrait($operation);
    }
```

`Comment` declares the exclusion instead of inheriting it by accident (settled decision 4):

```php
    /**
     * A comment author deletes their own comment; only its text goes through moderation.
     *
     * @return list<Operation>
     */
    #[Override]
    public function approvalOperations(): array
    {
        return [Operation::Create, Operation::Update];
    }
```

**Delete the `$item->applyAuthorApproveCredit($modification);` line from the `captureOperation()` body
above: in this task the credit must not run for an operation.** It ends by calling
`applyModificationChanges()` itself when it completes the quorum, so on a deletion it would run
`$this->delete()` from inside the `deleting` listener — a re-entrant delete whose outer call still
returns `false`, telling the caller a record was sent for approval when it is already gone. `captureSave()`
keeps calling it, unchanged: creates and updates have always gone through it. Task 6 routes the credit
through `ModificationVoteService::applyAuthorCredit()` and adds the call here, once it is safe.

Laravel's `forceDelete()` fires `forceDeleting` and then `delete()`, which fires `deleting` with `isForceDeleting()` true: listening to `deleting` alone covers both. A restore rejected by `restoring` never reaches `save()`. A restore on a record that is not trashed is left to Laravel (the `restoring` listener returns `null` first thing).

- [x] **Step 5: Run the capture tests and the approvals suites**

Run: `php artisan test --compact Modules/Core/tests/Integration/Approvals Modules/Core/tests/Integration/Helpers/HasApprovalsTest.php Modules/Core/tests/Integration/Models Modules/Core/tests/Feature/Controllers Modules/CMS/tests/Feature Modules/Core/tests/Feature/Console/ModelSoftDeletesCommandsTest.php`
Expected: PASS.

- [x] **Step 6: Pint and commit** (`feat(core): deletes, force deletes and restores go through approval`). Two commits: Core for the trait, CMS for `Content::requiresApprovalForOperation()` and `Comment::approvalOperations()`. Neither `Setting` nor `Taxonomy` is touched. Add one test per CMS model before committing: a draft `Content` is deleted directly while a live one is captured, and a comment delete runs without a modification.

**Executed 2026-09-28. Differences from the steps above, all in the tests or in details the steps did not pin:**

- The helper is `Modules\Core\Tests\Support\HttpContext::pretendHttpRequest()` (a static method, since Task 3), not a global `pretendHttpRequest()`. The same holds for every later task that calls it.
- The stub table declares `is_deleted` as `storedAs('deleted_at IS NOT NULL')`, as `MigrateUtils` does: a plain `default(false)` column makes Core `SoftDeletes::trashed()` read `false` after a soft delete. Every later task creating `approvals_soft_stub` needs the same line.
- "captures a restore of a trashed record" trashes the record as a superadmin, not after `Auth::logout()`: outside the console an anonymous writer cannot approve, so the setup delete was itself captured and the record never trashed.
- The `saving` listener checks `shouldCapture()` against `Create` for a new model and `Update` for an existing one, so a model can exclude creations too; the step's code checked `Update` only.
- `Content::requiresApprovalForOperation()` and `Comment::approvalOperations()` carry no `#[Override]`: PHP rejects the attribute on a method that replaces a trait method, since there is no parent method.
- The credit line is not in `captureOperation()`, as the step requires; the CMS tests the step asked for are in `CommentModerationTest` and `ContentModificationSoftKeepTest`.
- The `restoring` listener is registered only when the model has a `restoring()` method: a model with `HasApprovals` and no soft deletes (the Core stubs, for one) has no restore, and calling `static::restoring()` on it throws `BadMethodCallException`.

### Task 6: Applying approved deletes and restores, rejecting pending updates

**Files:**
- Modify: `Modules/Core/app/Services/ModificationVoteService.php`
- Modify: `Modules/Core/app/Models/Concerns/HasApprovals.php` (`applyModificationChanges` for non-diff operations, `deleteWhen*` removed, author credit delegated)
- Modify: `Modules/CMS/app/Models/Content.php` (`initializeHasApprovals()` loses the `deleteWhen*` overrides)
- Test: `Modules/Core/tests/Integration/Approvals/OperationApplyTest.php`, plus a case for the author credit completing the quorum on a deletion

**Interfaces:**
- Consumes: Task 5 capture.
- Produces: `ModificationVoteService::cast(User $user, Modification $modification, bool $approval, ?string $reason = null, ?Model $modifiable = null): bool` now runs in one transaction on the modifiable model's connection and applies every operation; `ModificationVoteService::applyAuthorCredit(Modification $modification, Model $modifiable): void`, the one other way an approved operation is applied, so nothing applies one outside this service.

- [x] **Step 1: Write the failing tests**

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

- [x] **Step 2: Run to verify they fail**

Run: `php artisan test --compact Modules/Core/tests/Integration/Approvals/OperationApplyTest.php`
Expected: FAIL (approving a deletion applies an empty diff and does not delete).

- [x] **Step 3: Implement**

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

and **remove** `$deleteWhenApproved` and `$deleteWhenDisapproved` entirely, with the branch that read them: a decided modification is deactivated and kept, for every model, as the spec requires. The properties leave three call sites behind, all of which go: `HasApprovals::initializeHasApprovals()` (`$this->deleteWhenDisapproved = true;`), and `Content::initializeHasApprovals()` at `Modules/CMS/app/Models/Content.php:605-613`, which sets both to false and whose PHPDoc documents the default it was fighting. `$updateWhenApproved` stays: `Comment` reads it at `Modules/CMS/app/Models/Comment.php:154`.

**The author's automatic `approve` credit routes through the service** (settled decision 1). `applyAuthorApproveCredit()` ends with `applyModificationChanges($modification, true)` when the credit completes the quorum, which skips the transaction, fires no event and, for a deletion, would delete from inside the `deleting` listener. Give the service the last word:

```php
    /**
     * Apply a modification whose quorum was completed by the author's own approve credit.
     * Not a vote: cast() would refuse it, because the author never votes on their own request.
     */
    public function applyAuthorCredit(Modification $modification, Model $modifiable): void
    {
        $modification->getConnection()->transaction(function () use ($modification, $modifiable): void {
            $modifiable->applyModificationChanges($modification, true);

            if ($modification->operation->isDeletion()) {
                $this->rejectPendingUpdatesOf($modification, $modification->modifier);
            }
        });

        event(new ModificationApproved($modification, $modifiable));
    }
```

and in the trait replace the direct `applyModificationChanges($modification, true)` at the end of `applyAuthorApproveCredit()` with `resolve(ModificationVoteService::class)->applyAuthorCredit($modification, $this);`. Then add `$item->applyAuthorApproveCredit($modification);` back into `captureOperation()`, which Task 5 deliberately left out: with the application inside the service it is safe for every operation.

In `ModificationVoteService::cast()` wrap the whole body in `$modification->getConnection()->transaction(function () use (…): bool { … })`, and after `$target->applyModificationChanges($modification, $approval);` add:

```php
        if ($approval && $modification->operation->isDeletion()) {
            $this->rejectPendingUpdatesOf($modification, $user);
        }
```

with

```php
    private function rejectPendingUpdatesOf(Modification $deletion, Model $decider): void
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

- [x] **Step 4: Run** the new test file and the approvals suites (Task 5 Step 5 command, plus `Modules/CMS/tests/Feature/Models/ContentModificationSoftKeepTest.php`). Expected: PASS.

- [x] **Step 5: Pint and commit** (`feat(core): approving a deletion or restore runs it, in one transaction with the vote`).

**Executed 2026-09-28. Differences from the steps above:**

- The test file follows the Task 5 deviations (`HttpContext::pretendHttpRequest()`, `is_deleted` as `storedAs`), and adds the sixth case the Files list asks for: the author credit completing the quorum of a force deletion. `SoftDeletableApprovalModel` gained a static `$approvers` to raise the quorum to 2.
- `cast()` resolves the target of a `Restore` with `modifiable()->withTrashed()`: the relation's soft-delete scope hides a trashed record, and `$modification->refresh()` after the vote reloads the relation, so a target passed in or set earlier does not survive either.
- The non-diff branch resets the forced flag in a `finally`, so a failed application leaves the instance able to capture again.
- `pendingModification()` is `null` after a capture whose author credit completed the quorum at once: the operation ran, and the panel and API tasks read a non-null value as "sent for approval". `captureSave()` does the same.
- `ContentModificationSoftKeepTest`'s first case read the removed `deleteWhen*` properties by reflection; it now checks the behaviour instead (an approved modification is kept, inactive). `Content::initializeHasApprovals()` is gone entirely: without the two flags it repeated the trait's.

### Task 7: Pending deletion strategy — Block and Hide

**Files:**
- Create: `Modules/Core/app/Approvals/PendingDeletionStrategy.php`, `Modules/Core/app/Approvals/PendingDeletionLock.php`
- Modify: `Modules/Core/app/Models/Concerns/HasApprovals.php`
- Create: `Modules/Core/tests/Stubs/Approvals/HiddenWhilePendingDeletionModel.php` (same table and traits as `SoftDeletableApprovalModel`, `pendingDeletionStrategy()` returns `Hide`)
- Test: `Modules/Core/tests/Integration/Approvals/PendingDeletionStrategyTest.php`

**Interfaces:**
- Produces: `enum PendingDeletionStrategy { case Block; case Hide; }`, `final class PendingDeletionLock extends RuntimeException`, on models `pendingDeletionStrategy(): PendingDeletionStrategy` (default `Block`), `attributesWritableWhilePendingDeletion(): list<string>` (default `[]`), local scope `withoutPendingDeletion()`, global scope applied to `Hide` models for users without the table's `approve` or `disapprove` permission.

- [x] **Step 1: Write the failing tests**

```php
it('refuses to save a record whose deletion is pending', function (): void {
    Auth::login($this->author);
    $this->record->delete();

    $this->record->name = 'edited';

    expect(fn () => $this->record->save())->toThrow(PendingDeletionLock::class);
});

it('still saves the attributes the model declares writable', function (): void {
    SoftDeletableApprovalModel::$writable = ['name'];
    Auth::login($this->author);
    $this->record->delete();

    $this->record->name = 'system write';
    $this->record->save();

    expect($this->record->fresh()->name)->toBe('system write');
});

it('blocks the same attribute when the model does not declare it writable', function (): void {
    SoftDeletableApprovalModel::$writable = [];
    Auth::login($this->author);
    $this->record->delete();

    $this->record->name = 'system write';

    expect(fn () => $this->record->save())->toThrow(PendingDeletionLock::class);
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

```php
it('does not hide a pending deletion when nobody is authenticated', function (): void {
    Auth::login($this->author);
    $hidden = HiddenWhilePendingDeletionModel::query()->find($this->record->id);
    $hidden->delete();
    Auth::logout();

    expect(HiddenWhilePendingDeletionModel::query()->whereKey($this->record->id)->exists())->toBeTrue();
});
```

(`beforeEach`/`afterEach` as in Task 6; `SoftDeletableApprovalModel` gains `public static array $writable = [];` returned by `attributesWritableWhilePendingDeletion()`, reset in `beforeEach`.)

Test the writable list on a real attribute, not on `updated_at`: the `Block` check subtracts `getUpdatedAtColumn()` unconditionally, so a bare `touch()` passes whatever the model declares and would prove nothing. The pair of tests above — same attribute, declared and not declared — is what pins the behaviour.

- [x] **Step 2: Run to verify they fail.** Expected: FAIL (`PendingDeletionLock` not found).

- [x] **Step 3: Implement**

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

            if ($model->pendingDeletionStrategy() !== PendingDeletionStrategy::Hide) {
                return;
            }

            $user = Auth::user();

            // Nobody authenticated: console, queues, jobs, search indexing, exports. They see
            // everything, exactly as capture is skipped in console. Hide answers "who may not
            // see a record they cannot decide on", which is a question about a person; a
            // pending deletion must not quietly change what background work reads.
            if (! $user instanceof User) {
                return;
            }

            if ($user->isSuperAdmin() || $user->can(PermissionName::forModel($model, 'approve')) || $user->can(PermissionName::forModel($model, 'disapprove'))) {
                return;
            }

            $query->withoutPendingDeletion();
        });
```

- [x] **Step 4: Run** the strategy tests and the approvals suites. Expected: PASS.

- [x] **Step 5: Pint and commit** (`feat(core): a pending deletion blocks the record, or hides it per model`).

The global scope keys on the table's `approve`/`disapprove` permission, as the spec requires: whoever has to decide always sees the record.

**Executed 2026-09-28. Differences from the steps above:**

- The two writable-list tests make the "system write" as the superadmin approver, after the author's delete. As the author, the save passes the `Block` check and is then captured as an update, so the attribute never reaches the row and the declared case failed for the wrong reason. The superadmin is still blocked on an undeclared attribute: `Block` exempts nobody.
- The scope name is `PendingDeletionStrategy::HIDE_SCOPE`, since two places need it.
- `ModificationVoteService::cast()` resolves its target with `withoutGlobalScope(PendingDeletionStrategy::HIDE_SCOPE)`, plus `withTrashed()` for a restore. The scope reads the authenticated user, who is not always the voter (a job, or a test casting for another user), and the voter must always reach the record they decide on. "shows the record again once the deletion is rejected" failed without it.
- Test setup follows the Task 5 deviations; `beforeEach` also resets `SoftDeletableApprovalModel::$writable`.


---

## Phase 4 — Withdrawal and events

### Task 8: Withdrawal, `ModificationApproved`/`ModificationRejected`/`ModificationWithdrawn` fired by the service

**Files:**
- Create: `Modules/Core/app/Events/ModificationRejected.php`, `Modules/Core/app/Events/ModificationWithdrawn.php` (next to the existing `Modules/Core/app/Events/ModificationApproved.php`: one lifecycle, one namespace)
- Modify: `Modules/Core/app/Services/ModificationVoteService.php`, `Modules/CMS/app/Models/Comment.php:196` (stop firing `ModificationApproved` itself)
- Test: `Modules/Core/tests/Integration/Approvals/WithdrawalTest.php`, `Modules/Core/tests/Integration/Approvals/DecisionEventsTest.php`

**Interfaces:**
- Produces: `ModificationVoteService::withdraw(User $user, Modification $modification): void` throwing `AuthorizationException` (not the author) or `LogicException` (not active); `final readonly class ModificationRejected { public function __construct(public Modification $modification, public ?Model $modifiable) {} }` in `Modules\Core\Events`, same shape for `ModificationWithdrawn` (`modifiable` is null for a create) — `ModificationApproved` stays `Modules\Core\Events\ModificationApproved` and is now fired by the service for every model, from `cast()` and from `applyAuthorCredit()`.

- [x] **Step 1: Write the failing tests**

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

- [x] **Step 2: Run to verify they fail.** Expected: FAIL (`withdraw` undefined).

- [x] **Step 3: Implement**

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

- [x] **Step 4: Run** the new tests, `Modules/CMS/tests/Feature/CommentModerationTest.php`, `Modules/AI/tests/Feature` and the approvals suites. Expected: PASS.

- [x] **Step 5: Pint and commit** in Core and CMS (`feat(core): authors withdraw their requests; decisions fire approved, rejected and withdrawn events`).

**Executed 2026-09-28. Differences from the steps above:**

- Every decision event fires through `afterCommit()` on the modification's connection, not inline: `cast()` now runs inside a transaction, and a listener such as AI's translation one dispatches a job that must not see the record before the commit. Outside a transaction `afterCommit()` runs the callback at once.
- `ModificationRejected` carries the record, or `null` for a rejected create, whose target is only a blank instance; `ModificationApproved` keeps its non-null `Model`.
- `withdraw()` and `cast()` find the record through one private `recordOf()`, past the soft-delete scope for a restore and past `PendingDeletionStrategy::HIDE_SCOPE` always.
- `Comment.php` also loses its `ModificationApproved` import.

---

## Phase 5 — Surfaces

### Task 9: Panel — shared approval outcome for edit pages and actions, withdraw in Modifications

**Files:**
- Create: `Modules/Core/app/Filament/Concerns/ReportsApprovalOutcome.php`
- Modify: `Modules/Core/app/Filament/Resources/Settings/Pages/EditSetting.php` (use the concern, drop its own notification code), the edit pages of `ContentResource`, `CommentResource`, `PresetResource`, `FieldResource`, `SettingResource`
- Modify: `Modules/Core/app/Filament/Resources/Modifications/Tables/ModificationsTable.php` (withdraw action)
- Test: `Modules/Core/tests/Feature/Filament/ApprovalOutcomeTest.php` (save path, `Block`, withdraw action), `Modules/CMS/tests/Feature/Filament/ContentApprovalOutcomeTest.php` (delete, force delete and restore actions)

`EditSetting` gets the trait and the save notification only: it has no delete action to make approval-aware, because `SettingResource` denies delete and force delete outright. The action builders go on the edit pages of `ContentResource`, `CommentResource`, `PresetResource` and `FieldResource`.

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

The delete, force delete and restore actions cannot be tested on `Setting`: `SettingResource`
answers `Response::deny(…)` to `getDeleteAuthorizationResponse()`, `getDeleteAnyAuthorizationResponse()`,
`getForceDeleteAuthorizationResponse()` and `getForceDeleteAnyAuthorizationResponse()` (settings
belong to seeders), and `getPages()` exposes only index and edit. Use `Content`, which has approvals,
soft deletes and a per-state rule, and keep `Setting` for the save path and the `Block` case above.
Put this one in `Modules/CMS/tests/Feature/Filament/ContentApprovalOutcomeTest.php`, with a live
content (a draft is written through and would not be captured):

```php
it('labels and reports a deletion that needs approval', function (): void {
    $actor = contentActorWithoutApproval();
    $content = Content::factory()->published()->create();

    Livewire::test(EditContent::class, ['record' => $content->getKey()])
        ->assertActionHasLabel(DeleteAction::class, 'Request deletion')
        ->callAction(DeleteAction::class)
        ->assertNotified('Deletion sent for approval');

    expect($content->fresh()->trashed())->toBeFalse()
        ->and($content->modifications()->activeOnly()->sole()->operation)->toBe(Operation::Delete);
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

- [x] **Step 1: Write the failing tests** (follow the setup of `Modules/Core/tests/Feature/Controllers/CrudPendingApprovalsTest.php` for routes, `CrudApiExposure::runEnabled()` and a non-approver with `update`/`delete` on the entity):

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

- [x] **Step 2: Run to verify they fail.**

- [x] **Step 3: Implement**

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

- [x] **Step 4: Run** the new test and `Modules/Core/tests/Feature/Controllers`, `Modules/Core/tests/Integration/Services/CrudServiceRequestScenariosTest.php`. Expected: PASS.

- [x] **Step 5: Pint and commit** (`feat(core): the CRUD API answers 202 for writes sent to approval`).

**Executed 2026-09-28. Differences from the steps above:**

- The tests use `core/settings`, not `core/taxonomies`: `Taxonomy` is abstract and its concrete subclasses live in CMS and ERP. The update route is named `core.crud.replace`, not `core.crud.update`. The field written is `is_public`: `Setting::$value` has no validation rule, so the API drops it from `validated()` and the update changes nothing.
- A refused withdrawal answers 401, not 403: `CrudController::handleServiceCall()` already maps every `AuthorizationException` to 401, and this task does not change that mapping. The author and the other writer are two tests: switching users between two requests of one test trips the session middleware (`AuthenticateSession`), so the non-author case captures the request through the model and makes a single HTTP call.
- `ModifyRequest` validates `modification` as `required|integer` on `/withdraw/` and skips the model's own rules there, as it does on `/delete/`: without it the request was refused for the model's required fields, and `modification` never reached `changes`.
- `CrudService::withdraw()` requires `select` on the entity and finds the modification by type and key only, so the author can also withdraw a create, which has no record. `capturedResult()` takes the list of captured requests: one request answers `{modification, operation}`, several answer `{modifications, applied}`. `doActivateOperation()` answers 202 instead of throwing when `restore()` or `delete()` is captured.
- The controller's `withdraw()` maps only a bare `LogicException` (the decided-request refusal) to 409; its subclasses keep the answers `handleServiceCall()` gives them.
- Added case: withdrawing a decided request answers 409.

### Task 11: AI tools report writes sent for approval

**Files:**
- Modify: `Modules/AI/app/Services/Tools/CrudToolProvider.php:757,775,793` (`present()` carries the status)
- Test: `Modules/AI/tests/Feature/Tools/CrudToolProviderApprovalTest.php`

- [x] **Step 1: Write the failing test** (setup as in `Modules/AI/tests/Feature/Tools/CrudToolProviderTest.php` for building the provider and exposing `core.taxonomy` with `update`; the acting user has `select` and `update` on taxonomies, not `approve`; call `pretendHttpRequest()`):

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

- [x] **Step 2: Run to verify it fails.**

- [x] **Step 3: Implement** in `present()`:

```php
        if ($result->statusCode === Response::HTTP_ACCEPTED && is_array($result->data)) {
            return ['request' => $request, 'status' => 'pending_approval', ...$result->data];
        }
```

and update the tool descriptions for `create`/`update`/`delete` (`CrudToolProvider.php:264` area) with: "When the entity requires approval the change is not applied: the result has status pending_approval and the id of the request."

- [x] **Step 4: Run** the new test and `Modules/AI/tests/Feature/Tools`. Expected: PASS.

- [x] **Step 5: Pint and commit** in AI (`feat(ai): CRUD tools report writes sent for approval`).

**Executed 2026-09-28. Differences from the steps above:**

- The test exposes `core.setting`, as `CrudToolProviderTest` does, and writes `is_public` (see Task 10: `Taxonomy` is abstract, `Setting::$value` has no rule). Its helpers are inlined under other names: the ones in `CrudToolProviderTest` are global functions defined only when that file loads.
- The three descriptions replace their old "captured for approval instead of applied immediately" sentence with the step's, rather than carrying both.

---

## Phase 6 — Dependency removal

### Task 12: Remove every remaining reference to the package

**Files:**
- Delete: `Modules/Core/config/approval.php`
- Modify: any file still importing `Approval\` (`command grep -rn "Approval\\\\" Modules app config --include=*.php | command grep -v "Modules\\\\Core\\\\Approvals"` must print nothing)
- Test: `Modules/Core/tests/Unit/Architecture/NoApprovalPackageTest.php`. Resolve the path with `dirname(__DIR__, 5)`, as `ModelFinalClassTest` and `LongLivedWorkerSafetyTest` do: `Modules/Core/tests/Pest.php` binds `tests/Unit` to `Modules\Core\Tests\TestCase`, a plain PHPUnit case with a minimal environment, so `base_path()` is not available there.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('references nothing from the laravel-approval package', function (): void {
    $offenders = [];
    $project_root = dirname(__DIR__, 5);

    foreach ((new Finder)->files()->in($project_root . '/Modules')->name('*.php')->exclude(['vendor', 'node_modules']) as $file) {
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

The requirement lives in **`Modules/Core/composer.json:45`**, not in the root `composer.json`, which never mentions it: the root merges `Modules/*/composer.json` through `wikimedia/composer-merge-plugin` (`extra.merge-plugin.include`). So `composer remove` at the root does nothing, and the edit belongs to the Core submodule.

- [ ] **Step 1: Ask the user** for explicit approval to drop the requirement. Stop here until they say yes.
- [ ] **Step 2:** remove the `"stephenlake/laravel-approval": "^1.1.4"` line from `Modules/Core/composer.json`, then refresh the root lock: `composer update --lock --no-interaction` (a plain `composer remove` cannot target a merged requirement). Check that `vendor/stephenlake` is gone.
- [ ] **Step 3: Run the full suites** of Core, CMS, AI and the root `tests` (`php artisan test --compact Modules/Core/tests` etc.). Expected: only the failures already known before this plan (`RouteServiceProviderTest`, `ElasticsearchServiceTest`, `DatabaseConnectionAffinityTest`).
- [ ] **Step 4: Commit** `composer.json` in the Core submodule (`chore(core): drop the laravel-approval requirement, the code is ours`), then `composer.lock` in the root repository (`chore: drop stephenlake/laravel-approval, now part of Core`), then bump the submodule pointer. Remove the "still required" note from the README's third-party entry in the Core commit.

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
