# Domain Action HTTP Routes Implementation Plan

**Status:** Completed 2026-08-01. All task checkboxes describe executed implementation history; external `/api/v1` exposure remains outside this plan.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose ERP domain actions over HTTP on the internal `/app` surface through one generic Core route backed by a per-entity registry.

**Architecture:** A single catch-all route `POST /app/crud/{action}/{module}/{entity}`, registered last in Core's `crud` group, resolves against a registry each module populates at boot. Authorization runs through `Gate` so state guards already in `ERPModelPolicy` apply. Models declare which generic Core verbs they override, and a boot-time guard rejects the contradiction between an overridden verb and the trait giving that verb its generic meaning.

**Tech Stack:** PHP 8.5, Laravel 12, Pest, Spatie Permission, Core CRUD (`CrudController` / `CrudService` / `ResponseBuilder`).

**Spec:** [`specs/2026-07-31-domain-action-http-routes-design.md`](../specs/2026-07-31-domain-action-http-routes-design.md) — read D1–D7 before starting.

**Backlog:** `3-04` (Tasks 1–2), `3-01` (Tasks 3–9), `3-06` (Task 10).

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`.
- Explicit parameter and return types everywhere; `#[Override]` when overriding.
- Local variables `snake_case`; methods and properties `camelCase`.
- Code, comments and PHPDoc in English.
- Tests are Pest feature tests inside the owning module's `tests/`.
- Never declare classes, traits, interfaces or enums inside test files — they go in that module's `tests/Stubs/` with PSR-4 registered in `composer.json` `autoload-dev`.
- Run `vendor/bin/pint --dirty` before every commit.
- One commit per task, in the owning submodule (`Modules/Core`, `Modules/ERP`).
- Do **not** add dependencies.

## Preconditions

Core already defers its web/API route registration to `$this->app->booted()`, so Core registers after every module and module-declared routes win. Confirm before starting:

```bash
php artisan route:check app/crud/detail/ai/conversations/5 --method=GET
```

Expected: resolves to `ai.crud.conversations.detail`, not `core.crud.detail`.

---

## File structure

| File | Responsibility |
|------|----------------|
| `Modules/Core/app/Support/PermissionName.php` | the single place a `{connection}.{table}.{operation}` name is built |
| `Modules/Core/app/Contracts/ExposesDomainActions.php` | marker: a model exposes domain actions |
| `Modules/Core/app/Contracts/OverridesGenericCrudActions.php` | a model declares which generic verbs it redefines |
| `Modules/Core/app/Services/Crud/DomainActionRegistry.php` | `{model, action}` → handler, plus the collision guard |
| `Modules/Core/app/Services/Crud/DomainActionDispatcher.php` | resolve → authorize → invoke |
| `Modules/Core/app/Http/Requests/DomainActionRequest.php` | resolves module/entity/id, isolates the action payload |
| `Modules/ERP/app/Services/DomainActions/ErpDomainActionRegistrar.php` | maps ERP actions onto existing services |

`CrudController` gains one method; `routes/web.php` gains one route; `ERPServiceProvider` gains one boot call.

---

## Task 1: `PermissionName` helper (3-04)

**Files:**
- Create: `Modules/Core/app/Support/PermissionName.php`
- Test: `Modules/Core/tests/Feature/Support/PermissionNameTest.php`

**Interfaces:**
- Produces: `PermissionName::build(string $connection, string $table, string $operation): string`, `PermissionName::forModel(Model $model, string $operation): string`, `PermissionName::forClass(string $model_class, string $operation): string`

`forClass` exists because `ERPDatabaseSeeder` builds names from class strings without instantiating models.

- [x] **Step 1: Write the failing test**

Create `Modules/Core/tests/Feature/Support/PermissionNameTest.php`:

```php
<?php

declare(strict_types=1);

use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;

it('builds a name from an explicit connection, table and operation', function (): void {
    expect(PermissionName::build('tenant_a', 'erp_invoices', 'update'))
        ->toBe('tenant_a.erp_invoices.update');
});

it('falls back to the default connection for a model without one', function (): void {
    $user = new User();

    expect(PermissionName::forModel($user, 'select'))
        ->toBe(PermissionName::build($user->getConnectionName() ?? 'default', $user->getTable(), 'select'));
});

it('builds a name from a class string without instantiating the model', function (): void {
    expect(PermissionName::forClass(User::class, 'delete'))
        ->toBe(PermissionName::forModel(new User(), 'delete'));
});
```

- [x] **Step 2: Run the test and watch it fail**

```bash
php artisan test --compact Modules/Core/tests/Feature/Support/PermissionNameTest.php
```

Expected: `Class "Modules\Core\Support\PermissionName" not found`.

- [x] **Step 3: Write the implementation**

Create `Modules/Core/app/Support/PermissionName.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * Single source of truth for permission names.
 *
 * The `{connection}.{table}.{operation}` convention was previously rebuilt by
 * hand in the authorization service, the ERP policy and the ERP seeder. Three
 * copies of one convention is three chances to drift.
 */
final class PermissionName
{
    public static function build(string $connection, string $table, string $operation): string
    {
        return sprintf('%s.%s.%s', $connection, $table, $operation);
    }

    public static function forModel(Model $model, string $operation): string
    {
        return self::build(
            $model->getConnectionName() ?? 'default',
            $model->getTable(),
            $operation,
        );
    }

    /**
     * @param  class-string<Model>  $model_class
     */
    public static function forClass(string $model_class, string $operation): string
    {
        /** @var Model $instance */
        $instance = new ReflectionClass($model_class)->newInstanceWithoutConstructor();

        return self::forModel($instance, $operation);
    }
}
```

- [x] **Step 4: Run the test and watch it pass**

```bash
php artisan test --compact Modules/Core/tests/Feature/Support/PermissionNameTest.php
```

Expected: `3 passed`.

- [x] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Support/PermissionName.php tests/Feature/Support/PermissionNameTest.php
git -C Modules/Core commit -m "feat(core): centralize permission name construction"
```

---

## Task 2: Route every caller through `PermissionName` (3-04)

**Files:**
- Modify: `Modules/Core/app/Services/Authorization/AuthorizationService.php:185-193` — `buildPermissionName()`
- Modify: `Modules/ERP/app/Policies/ERPModelPolicy.php:206-222` — `hasPermission()`
- Modify: `Modules/ERP/database/seeders/ERPDatabaseSeeder.php:237-296` — `domainPermissions()`
- Test: `Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php` (existing, must stay green)

**Interfaces:**
- Consumes: `PermissionName::build()`, `PermissionName::forModel()`, `PermissionName::forClass()` from Task 1

This task changes no behaviour. The existing tests are the proof: if a name changes, `ErpModelPolicyTest` and `ErpDomainPermissionsSeederTest` fail.

- [x] **Step 1: Record the current baseline**

```bash
php artisan test --compact Modules/ERP/tests/Feature/ErpModelPolicyTest.php Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php Modules/ERP/tests/Feature/ErpModelPolicyStateTest.php
```

Write the passing counts down. They must be identical at Step 5.

- [x] **Step 2: Replace the Core copy**

In `AuthorizationService::buildPermissionName()`, replace the body:

```php
    public function buildPermissionName(
        string $entity,
        ?string $operation = null,
        ?string $connection = null,
    ): string {
        return PermissionName::build($connection ?? 'default', $entity, (string) $operation);
    }
```

Add `use Modules\Core\Support\PermissionName;`.

- [x] **Step 3: Replace the ERP policy copy**

In `ERPModelPolicy::hasPermission()`, replace the `sprintf(...)` block:

```php
        $permission = PermissionName::forModel($record, $operation);
```

Add `use Modules\Core\Support\PermissionName;`. Leave the rest of the method untouched.

- [x] **Step 4: Replace the ERP seeder copy**

In `ERPDatabaseSeeder::domainPermissions()`, replace every hand-built string. The per-model loop becomes:

```php
        foreach ($entities as $model) {
            $permissions[] = PermissionName::forClass($model, 'post');
            $permissions[] = PermissionName::forClass($model, 'unpost');

            if ($model === Invoice::class) {
                $permissions[] = PermissionName::forClass($model, 'submitEInvoice');
                $permissions[] = PermissionName::forClass($model, 'refreshEInvoice');
                $permissions[] = PermissionName::forClass($model, 'force_post');
            }

            if ($model === FiscalPeriod::class) {
                $permissions[] = PermissionName::forClass($model, 'close');
                $permissions[] = PermissionName::forClass($model, 'reopen');
            }

            if ($model === JournalEntry::class) {
                $permissions[] = PermissionName::forClass($model, 'reverse');
            }

            if ($model === SalesOrder::class) {
                $permissions[] = PermissionName::forClass($model, 'amend');
            }

            if ($model === Quotation::class) {
                $permissions[] = PermissionName::forClass($model, 'unlock');
            }

            if ($model === DocumentSequence::class) {
                $permissions[] = PermissionName::forClass($model, 'reset');
                $permissions[] = PermissionName::forClass($model, 'reserve');
            }
        }

        $permissions[] = PermissionName::forClass(FiscalYear::class, 'close');
        $permissions[] = PermissionName::forClass(Company::class, 'switch_context');
        $permissions[] = PermissionName::forClass(TaxCode::class, 'supersede');

        return $permissions;
```

Add `use Modules\Core\Support\PermissionName;` and drop the now-unused `ReflectionClass` import **only if** nothing else in the file uses it.

- [x] **Step 5: Run the same tests and compare**

```bash
php artisan test --compact Modules/ERP/tests/Feature/ErpModelPolicyTest.php Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php Modules/ERP/tests/Feature/ErpModelPolicyStateTest.php
php artisan test --compact Modules/Core/tests/Feature/Api/CrudApiTest.php Modules/Core/tests/Feature/Controllers
```

Expected: identical counts to Step 1, everything green. Any change means a permission name moved — fix the code, not the test.

- [x] **Step 6: Commit both modules**

```bash
vendor/bin/pint --dirty
git -C Modules/Core commit -am "refactor(core): build permission names through PermissionName"
git -C Modules/ERP commit -am "refactor(erp): build permission names through PermissionName"
```

---

## Task 3: Registry and contracts (3-01)

**Files:**
- Create: `Modules/Core/app/Contracts/ExposesDomainActions.php`
- Create: `Modules/Core/app/Contracts/OverridesGenericCrudActions.php`
- Create: `Modules/Core/app/Services/Crud/DomainActionRegistry.php`
- Test: `Modules/Core/tests/Feature/Services/DomainActionRegistryTest.php`
- Create: `Modules/Core/tests/Stubs/DomainActions/PlainActionModel.php`

**Interfaces:**
- Produces: `DomainActionRegistry::register(string $model_class, string $action, callable $handler): void`, `::resolve(string $model_class, string $action): ?callable`, `::has(string $model_class, string $action): bool`, `::actionsFor(string $model_class): list<string>`

Handler signature, relied on by Tasks 5 and 7–9:

```php
fn (Model $record, array $payload, User $user): mixed
```

- [x] **Step 1: Add the test stub model**

Create `Modules/Core/tests/Stubs/DomainActions/PlainActionModel.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\DomainActions;

use Illuminate\Database\Eloquent\Model;

/**
 * A model with no Core cross-cutting traits, used to exercise registration of
 * a domain verb that collides with nothing.
 */
final class PlainActionModel extends Model
{
    protected $table = 'plain_action_models';
}
```

Confirm `Modules\Core\Tests\Stubs\` is already mapped in `Modules/Core/composer.json` `autoload-dev`; the module already ships `tests/Stubs/Locking/…`, so it is.

- [x] **Step 2: Write the failing test**

Create `Modules/Core/tests/Feature/Services/DomainActionRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\Core\Tests\Stubs\DomainActions\PlainActionModel;

it('resolves a registered handler for a model and action', function (): void {
    $registry = new DomainActionRegistry();
    $registry->register(PlainActionModel::class, 'archive', fn (Model $record, array $payload, User $user): string => 'archived');

    $handler = $registry->resolve(PlainActionModel::class, 'archive');

    expect($handler)->not->toBeNull()
        ->and($handler(new PlainActionModel(), [], new User()))->toBe('archived');
});

it('returns null for an action that was never registered', function (): void {
    $registry = new DomainActionRegistry();

    expect($registry->resolve(PlainActionModel::class, 'nope'))->toBeNull();
});

it('lists the actions registered for a model', function (): void {
    $registry = new DomainActionRegistry();
    $registry->register(PlainActionModel::class, 'archive', fn (): null => null);
    $registry->register(PlainActionModel::class, 'restore_from_archive', fn (): null => null);

    expect($registry->actionsFor(PlainActionModel::class))
        ->toBe(['archive', 'restore_from_archive']);
});

it('rejects registering the same action twice for one model', function (): void {
    $registry = new DomainActionRegistry();
    $registry->register(PlainActionModel::class, 'archive', fn (): null => null);

    $registry->register(PlainActionModel::class, 'archive', fn (): null => null);
})->throws(LogicException::class, 'already registered');
```

- [x] **Step 3: Run the test and watch it fail**

```bash
php artisan test --compact Modules/Core/tests/Feature/Services/DomainActionRegistryTest.php
```

Expected: `Class "Modules\Core\Services\Crud\DomainActionRegistry" not found`.

- [x] **Step 4: Write the contracts**

Create `Modules/Core/app/Contracts/ExposesDomainActions.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A model that answers to domain actions over HTTP.
 */
interface ExposesDomainActions
{
    /**
     * @return list<string>
     */
    public static function exposedDomainActions(): array;
}
```

Create `Modules/Core/app/Contracts/OverridesGenericCrudActions.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A model that redefines one of Core's generic CRUD verbs.
 *
 * Core's generic verbs act on structures attached to a record — a pending
 * Modification, the lock columns, the soft-delete state. A module may need the
 * same verb to act on the record itself; `ReturnOrder::approve` transitions a
 * document lifecycle rather than voting on a pending edit. Declaring it here is
 * what lets the dispatcher pick the module implementation and what lets the
 * registry reject the combination that would be ambiguous.
 */
interface OverridesGenericCrudActions
{
    /**
     * @return list<string>
     */
    public static function overriddenCrudActions(): array;
}
```

- [x] **Step 5: Write the registry**

Create `Modules/Core/app/Services/Crud/DomainActionRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Maps {model, action} to a handler. The registry, not the route table, decides
 * which domain actions exist: one generic route serves every module.
 *
 * Handlers have the shape fn (Model $record, array $payload, User $user): mixed.
 */
final class DomainActionRegistry
{
    /** @var array<class-string<Model>, array<string, callable>> */
    private array $handlers = [];

    public function register(string $model_class, string $action, callable $handler): void
    {
        throw_if(
            isset($this->handlers[$model_class][$action]),
            LogicException::class,
            sprintf('Domain action [%s] is already registered for [%s].', $action, $model_class),
        );

        $this->handlers[$model_class][$action] = $handler;
    }

    public function resolve(string $model_class, string $action): ?callable
    {
        return $this->handlers[$model_class][$action] ?? null;
    }

    public function has(string $model_class, string $action): bool
    {
        return isset($this->handlers[$model_class][$action]);
    }

    /**
     * @return list<string>
     */
    public function actionsFor(string $model_class): array
    {
        return array_keys($this->handlers[$model_class] ?? []);
    }
}
```

- [x] **Step 6: Run the test and watch it pass**

```bash
php artisan test --compact Modules/Core/tests/Feature/Services/DomainActionRegistryTest.php
```

Expected: `4 passed`.

- [x] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Contracts/ExposesDomainActions.php app/Contracts/OverridesGenericCrudActions.php app/Services/Crud/DomainActionRegistry.php tests/
git -C Modules/Core commit -m "feat(core): add domain action registry and contracts"
```

---

## Task 4: Boot-time collision guard (3-01)

**Files:**
- Modify: `Modules/Core/app/Services/Crud/DomainActionRegistry.php` — guard inside `register()`
- Test: `Modules/Core/tests/Feature/Services/DomainActionRegistryTest.php` (extend)
- Create: `Modules/Core/tests/Stubs/DomainActions/ApprovableActionModel.php`
- Create: `Modules/Core/tests/Stubs/DomainActions/OverridingActionModel.php`

**Interfaces:**
- Consumes: `DomainActionRegistry::register()` from Task 3, `OverridesGenericCrudActions` from Task 3

The rule: registering a **generic** verb requires the model to declare it in `overriddenCrudActions()`, and forbids the model from also using the trait that gives that verb its generic meaning. Registration happens at application boot, so a contradiction stops the app immediately in every environment rather than surfacing when one record is first touched.

- [x] **Step 1: Add the two stub models**

Create `Modules/Core/tests/Stubs/DomainActions/OverridingActionModel.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\DomainActions;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\OverridesGenericCrudActions;
use Override;

/**
 * Declares an override of the generic `approve` verb without taking the trait
 * that gives it its generic meaning — the legal combination.
 */
final class OverridingActionModel extends Model implements OverridesGenericCrudActions
{
    protected $table = 'overriding_action_models';

    /**
     * @return list<string>
     */
    #[Override]
    public static function overriddenCrudActions(): array
    {
        return ['approve'];
    }
}
```

Create `Modules/Core/tests/Stubs/DomainActions/ApprovableActionModel.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\DomainActions;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\OverridesGenericCrudActions;
use Modules\Core\Models\Concerns\HasApprovals;
use Override;

/**
 * Declares an override of `approve` *and* uses HasApprovals — the contradiction
 * the guard must reject, because `approve` would mean two things at once.
 */
final class ApprovableActionModel extends Model implements OverridesGenericCrudActions
{
    use HasApprovals;

    protected $table = 'approvable_action_models';

    /**
     * @return list<string>
     */
    #[Override]
    public static function overriddenCrudActions(): array
    {
        return ['approve'];
    }
}
```

- [x] **Step 2: Write the failing tests**

Append to `Modules/Core/tests/Feature/Services/DomainActionRegistryTest.php`:

```php
it('allows registering a generic verb when the model declares the override', function (): void {
    $registry = new DomainActionRegistry();

    $registry->register(OverridingActionModel::class, 'approve', fn (): null => null);

    expect($registry->has(OverridingActionModel::class, 'approve'))->toBeTrue();
});

it('rejects a generic verb the model did not declare as overridden', function (): void {
    $registry = new DomainActionRegistry();

    $registry->register(PlainActionModel::class, 'approve', fn (): null => null);
})->throws(LogicException::class, 'must declare it in overriddenCrudActions()');

it('rejects overriding approve on a model that also uses HasApprovals', function (): void {
    $registry = new DomainActionRegistry();

    $registry->register(ApprovableActionModel::class, 'approve', fn (): null => null);
})->throws(LogicException::class, 'already means');
```

Add the two stub imports at the top of the file.

- [x] **Step 3: Run the tests and watch them fail**

```bash
php artisan test --compact Modules/Core/tests/Feature/Services/DomainActionRegistryTest.php
```

Expected: the two rejection tests fail because no exception is thrown; the permissive one passes.

- [x] **Step 4: Implement the guard**

In `DomainActionRegistry`, add the verb map and the guard, and call it at the top of `register()`:

```php
    /**
     * Core's generic verbs and the trait that gives each its generic meaning.
     * `cache-clear` has no trait: it is generic for every model, so a module can
     * never redefine it.
     *
     * @var array<string, ?class-string>
     */
    private const array GENERIC_VERBS = [
        'approve' => HasApprovals::class,
        'disapprove' => HasApprovals::class,
        'lock' => HasLocks::class,
        'unlock' => HasLocks::class,
        'activate' => SoftDeletes::class,
        'inactivate' => SoftDeletes::class,
        'cache-clear' => null,
    ];

    private function assertMayOverride(string $model_class, string $action): void
    {
        if (! array_key_exists($action, self::GENERIC_VERBS)) {
            return;
        }

        $declared = is_a($model_class, OverridesGenericCrudActions::class, true)
            ? $model_class::overriddenCrudActions()
            : [];

        throw_unless(
            in_array($action, $declared, true),
            LogicException::class,
            sprintf(
                '[%s] is a generic CRUD verb; to redefine it for [%s] the model must declare it in overriddenCrudActions().',
                $action,
                $model_class,
            ),
        );

        $trait = self::GENERIC_VERBS[$action];

        throw_if(
            $trait !== null && class_uses_trait($model_class, $trait),
            LogicException::class,
            sprintf(
                '[%s] already means something on [%s] through [%s]; one entity cannot carry both meanings of the same verb.',
                $action,
                $model_class,
                $trait,
            ),
        );
    }
```

Add the imports: `Modules\Core\Contracts\OverridesGenericCrudActions`, `Modules\Core\Models\Concerns\HasApprovals`, `Modules\Core\Locking\Traits\HasLocks`, `Modules\Core\SoftDeletes\SoftDeletes`.

Call it first in `register()`:

```php
    public function register(string $model_class, string $action, callable $handler): void
    {
        $this->assertMayOverride($model_class, $action);

        throw_if(
```

- [x] **Step 5: Run the tests and watch them pass**

```bash
php artisan test --compact Modules/Core/tests/Feature/Services/DomainActionRegistryTest.php
```

Expected: `7 passed`.

- [x] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Services/Crud/DomainActionRegistry.php tests/
git -C Modules/Core commit -m "feat(core): reject domain actions colliding with generic CRUD verbs"
```

---

## Task 5: Dispatcher (3-01)

**Files:**
- Create: `Modules/Core/app/Services/Crud/DomainActionDispatcher.php`
- Test: `Modules/Core/tests/Feature/Services/DomainActionDispatcherTest.php`

**Interfaces:**
- Consumes: `DomainActionRegistry::resolve()` from Task 3
- Produces: `DomainActionDispatcher::dispatch(Model $record, string $action, User $user, array $payload = []): mixed`

Resolution happens **before** authorization: an unknown action is a 404, not a 403. Leaking "this action exists but you may not use it" for actions that do not exist would be wrong and noisier.

- [x] **Step 1: Write the failing test**

Create `Modules/Core/tests/Feature/Services/DomainActionDispatcherTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\DomainActionDispatcher;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\Core\Tests\Stubs\DomainActions\PlainActionModel;

it('invokes the handler with record, payload and user when authorized', function (): void {
    Gate::define('archive', fn (): bool => true);

    $registry = new DomainActionRegistry();
    $registry->register(
        PlainActionModel::class,
        'archive',
        fn (Model $record, array $payload, User $user): array => $payload,
    );

    $result = new DomainActionDispatcher($registry)
        ->dispatch(new PlainActionModel(), 'archive', new User(), ['reason' => 'obsolete']);

    expect($result)->toBe(['reason' => 'obsolete']);
});

it('raises a not-found error for an unregistered action', function (): void {
    new DomainActionDispatcher(new DomainActionRegistry())
        ->dispatch(new PlainActionModel(), 'nope', new User());
})->throws(ModelNotFoundException::class);

it('refuses when the gate denies the action', function (): void {
    Gate::define('archive', fn (): bool => false);

    $registry = new DomainActionRegistry();
    $registry->register(PlainActionModel::class, 'archive', fn (): null => null);

    new DomainActionDispatcher($registry)
        ->dispatch(new PlainActionModel(), 'archive', new User());
})->throws(AuthorizationException::class);

it('maps a snake_case action onto its camelCase policy method', function (): void {
    $seen = null;
    Gate::define('forcePost', function () use (&$seen): bool {
        $seen = 'forcePost';

        return true;
    });

    $registry = new DomainActionRegistry();
    $registry->register(PlainActionModel::class, 'force_post', fn (): null => null);

    new DomainActionDispatcher($registry)
        ->dispatch(new PlainActionModel(), 'force_post', new User());

    expect($seen)->toBe('forcePost');
});
```

- [x] **Step 2: Run the test and watch it fail**

```bash
php artisan test --compact Modules/Core/tests/Feature/Services/DomainActionDispatcherTest.php
```

Expected: `Class "Modules\Core\Services\Crud\DomainActionDispatcher" not found`.

- [x] **Step 3: Write the implementation**

Create `Modules/Core/app/Services/Crud/DomainActionDispatcher.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Modules\Core\Models\User;

/**
 * Resolves a domain action, authorizes it, then invokes it.
 *
 * Authorization goes through the Gate rather than a bare permission check: a
 * domain action's guard is intrinsic to it — posting an already-posted invoice
 * is not a permission problem — and the module policies already combine the
 * state predicate with the permission.
 */
final class DomainActionDispatcher
{
    public function __construct(private readonly DomainActionRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(Model $record, string $action, User $user, array $payload = []): mixed
    {
        $handler = $this->registry->resolve($record::class, $action);

        throw_if(
            $handler === null,
            ModelNotFoundException::class,
            sprintf('No domain action [%s] is registered for [%s].', $action, $record::class),
        );

        throw_unless(
            $user->can(self::policyMethodFor($action), $record),
            AuthorizationException::class,
            'User not allowed to perform this action',
        );

        return $handler($record, $payload, $user);
    }

    /**
     * `force_post` is seeded and registered in snake_case; the policy method is
     * `forcePost`. Names already in camelCase pass through unchanged.
     */
    private static function policyMethodFor(string $action): string
    {
        return Str::camel($action);
    }
}
```

- [x] **Step 4: Run the test and watch it pass**

```bash
php artisan test --compact Modules/Core/tests/Feature/Services/DomainActionDispatcherTest.php
```

Expected: `4 passed`.

- [x] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Services/Crud/DomainActionDispatcher.php tests/Feature/Services/DomainActionDispatcherTest.php
git -C Modules/Core commit -m "feat(core): add domain action dispatcher"
```

---

## Task 6: Request, controller action and route (3-01)

**Files:**
- Create: `Modules/Core/app/Http/Requests/DomainActionRequest.php`
- Modify: `Modules/Core/app/Http/Controllers/CrudController.php` — add `domainAction()`, extend `handleServiceCall()` mappings
- Modify: `Modules/Core/routes/web.php` — one route, last in the `crud` group
- Test: `Modules/Core/tests/Feature/Http/DomainActionRouteTest.php`

**Interfaces:**
- Consumes: `DomainActionDispatcher::dispatch()` from Task 5
- Produces: route name `core.crud.domain-action`, URI `app/crud/{action}/{module}/{entity}`

`CrudController::__construct()` is **not** changed: `Modules/CMS/app/Http/Controllers/ContentsController.php:18` calls `parent::__construct($crudService)`, so an extra constructor parameter would break CMS. The dispatcher arrives through method injection instead.

- [x] **Step 1: Write the failing test**

Create `Modules/Core/tests/Feature/Http/DomainActionRouteTest.php`:

```php
<?php

declare(strict_types=1);

use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\DomainActionRegistry;

beforeEach(function (): void {
    $this->actor = User::factory()->create();
    $this->actor->assignRole(Role::findOrCreate('superadmin', 'web'));
    $this->actingAs($this->actor);
});

it('routes a registered domain action to its handler', function (): void {
    $target = User::factory()->create();

    app(DomainActionRegistry::class)->register(
        $target::class,
        'archive',
        fn (Modules\Core\Models\User $record, array $payload): array => ['id' => $record->id, 'payload' => $payload],
    );

    $response = $this->postJson(
        route('core.crud.domain-action', ['action' => 'archive', 'module' => 'core', 'entity' => 'users']),
        ['id' => $target->id, 'reason' => 'obsolete'],
    );

    $response->assertOk()
        ->assertJsonPath('data.payload.reason', 'obsolete');
});

it('returns 404 for an action nobody registered', function (): void {
    $target = User::factory()->create();

    $response = $this->postJson(
        route('core.crud.domain-action', ['action' => 'nope', 'module' => 'core', 'entity' => 'users']),
        ['id' => $target->id],
    );

    $response->assertNotFound();
});

it('passes a streamed response through untouched', function (): void {
    $target = User::factory()->create();

    app(DomainActionRegistry::class)->register(
        $target::class,
        'export_something',
        fn (): Symfony\Component\HttpFoundation\Response => response('col_a,col_b', 200, ['Content-Type' => 'text/csv']),
    );

    $response = $this->post(
        route('core.crud.domain-action', ['action' => 'export_something', 'module' => 'core', 'entity' => 'users']),
        ['id' => $target->id],
    );

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->getContent())->toBe('col_a,col_b');
});
```

- [x] **Step 2: Run the test and watch it fail**

```bash
php artisan test --compact Modules/Core/tests/Feature/Http/DomainActionRouteTest.php
```

Expected: `Route [core.crud.domain-action] not defined`.

- [x] **Step 3: Write the request**

Create `Modules/Core/app/Http/Requests/DomainActionRequest.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\IParsableRequest;
use Override;

/**
 * Resolves {module}/{entity} like every CRUD request and isolates the action
 * payload. Model validation rules are deliberately not applied: a domain action
 * is an operation invocation, not a write of the record's own fields.
 */
final class DomainActionRequest extends CrudRequest implements IParsableRequest
{
    /**
     * @var list<string>
     */
    private const array RESERVED = ['id', 'module', 'entity', 'action', 'connection'];

    #[Override]
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'id' => ['required'],
        ]);
    }

    public function action(): string
    {
        return (string) $this->route('action');
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        /** @var array<string, mixed> $all */
        $all = $this->all();

        return array_diff_key($all, array_flip(self::RESERVED));
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $id = $this->route('id') ?? $this->input('id');

        if ($id !== null && $id !== '') {
            $this->merge(['id' => $id]);
        }
    }
}
```

- [x] **Step 4: Add the controller action**

In `Modules/Core/app/Http/Controllers/CrudController.php`, add these imports:

```php
use Modules\Core\Http\Requests\DomainActionRequest;
use Modules\Core\Services\Crud\DomainActionDispatcher;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
```

and this method, after `clearModelCache()`:

```php
    /**
     * Invoke a module-registered domain action on one record.
     *
     * The dispatcher arrives by method injection: adding it to the constructor
     * would break every subclass that calls parent::__construct($crudService).
     */
    final public function domainAction(DomainActionRequest $request, DomainActionDispatcher $dispatcher): Response
    {
        $request_data = $request->parsed();
        $model = $request_data->model;

        try {
            $record = $model->newQuery()->findOrFail($request->input('id'));

            /** @var \Modules\Core\Models\User $user */
            $user = $request->user();

            $result = $dispatcher->dispatch($record, $request->action(), $user, $request->payload());

            if ($result instanceof SymfonyResponse) {
                return $result;
            }

            Cache::clearByEntity($model);

            return $this->buildResponse(new CrudResult(data: $result), $request);
        } catch (Throwable $ex) {
            return $this->handleServiceCall(fn () => throw $ex, $request, $model, shouldCache: false);
        }
    }
```

- [x] **Step 5: Extend the error mapping**

Domain services raise `ValidationException` and `DomainException`; without mappings both become 500. In `handleServiceCall()`, insert these two catches **before** the final `catch (Throwable $ex)`:

```php
        } catch (\Illuminate\Validation\ValidationException $ex) {
            return $this->buildResponse(
                new CrudResult(
                    data: null,
                    error: $ex->getMessage(),
                    statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                ),
                $request,
            );
        } catch (\DomainException $ex) {
            return $this->buildResponse(
                new CrudResult(
                    data: null,
                    error: $ex->getMessage(),
                    statusCode: Response::HTTP_CONFLICT,
                ),
                $request,
            );
```

`LogicException` is already caught and mapped to 304. `DomainException` extends `LogicException`, so its catch **must** come first or it will never be reached.

- [x] **Step 6: Add the route**

In `Modules/Core/routes/web.php`, inside the `Route::name('crud.')->prefix('/crud')` group, **after** the `GridsController` group so every literal verb wins the match:

```php
    // Last in the group on purpose: this is the catch-all for module-registered
    // domain verbs, and Laravel matches in registration order. Every literal verb
    // above must be tried first. The grid and graph groups carry an extra path
    // segment, so they never reach this route.
    Route::post('/{action}/{module}/{entity}', [CrudController::class, 'domainAction'])
        ->name('domain-action');
```

- [x] **Step 7: Run the test and watch it pass**

```bash
php artisan test --compact Modules/Core/tests/Feature/Http/DomainActionRouteTest.php
```

Expected: `3 passed`.

- [x] **Step 8: Prove the literal verbs still win**

```bash
php artisan route:check app/crud/select/erp/invoices --method=GET
php artisan route:check app/crud/detail/ai/conversations/5 --method=GET
php artisan test --compact Modules/Core/tests/Feature/Api/CrudApiTest.php Modules/Core/tests/Feature/Controllers Modules/AI/tests/Feature/ChatTest.php
```

Expected: `core.crud.list`, then `ai.crud.conversations.detail`, then all green.

- [x] **Step 9: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core add app/Http/Requests/DomainActionRequest.php app/Http/Controllers/CrudController.php routes/web.php tests/Feature/Http/
git -C Modules/Core commit -m "feat(core): expose domain actions on the /app surface"
```

---

## Task 7: ERP registrar — accounting and document actions (3-01)

**Files:**
- Create: `Modules/ERP/app/Services/DomainActions/ErpDomainActionRegistrar.php`
- Modify: `Modules/ERP/app/Providers/ERPServiceProvider.php` — call the registrar in `boot()`
- Test: `Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php`

**Interfaces:**
- Consumes: `DomainActionRegistry::register()` from Task 3
- Produces: `ErpDomainActionRegistrar::register(DomainActionRegistry $registry): void`

- [x] **Step 1: Write the failing test**

Create `Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;

uses(RefreshDatabase::class);

it('registers the accounting domain actions at boot', function (): void {
    $registry = app(DomainActionRegistry::class);

    expect($registry->has(Invoice::class, 'post'))->toBeTrue()
        ->and($registry->has(Invoice::class, 'unpost'))->toBeTrue()
        ->and($registry->has(JournalEntry::class, 'reverse'))->toBeTrue();
});

it('does not register force_post as an action of its own', function (): void {
    // Forcing the three-way match is a flag on `post` guarded by the `forcePost`
    // permission, mirroring the Filament form. Registering it separately would
    // give it a URL that bypasses the normal post path.
    expect(app(DomainActionRegistry::class)->has(Invoice::class, 'force_post'))->toBeFalse();
});
```

- [x] **Step 2: Run the test and watch it fail**

```bash
php artisan test --compact Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php
```

Expected: fails on the first `toBeTrue()`.

- [x] **Step 3: Write the registrar with the accounting slice**

Create `Modules/ERP/app/Services/DomainActions/ErpDomainActionRegistrar.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\ERP\Services\DomainActions;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Services\Accounting\FiscalPeriodCloser;
use Modules\ERP\Services\Accounting\JournalPostingService;
use Modules\ERP\Services\Sales\SalesOrderAmendmentService;

/**
 * Maps ERP domain actions onto the services that already implement them.
 *
 * Handlers stay thin on purpose: business rules, locking and state guards live
 * in the services and the policy, exactly as they do for the Filament actions
 * that call the same code.
 */
final class ErpDomainActionRegistrar
{
    public function register(DomainActionRegistry $registry): void
    {
        $this->registerInvoices($registry);
        $this->registerAccounting($registry);
    }

    private function registerInvoices(DomainActionRegistry $registry): void
    {
        /**
         * Posting is driven by the observer on `posted_at`, exactly as
         * InvoicePostingActions::postInvoice() does. `force_post` is not a separate
         * action but a payload flag on `post`, carrying its own permission — this
         * mirrors the Filament form, where the checkbox only appears for a purchase
         * invoice when the user holds `forcePost`.
         */
        $registry->register(Invoice::class, 'post', static function (Model $record, array $payload, User $user): Model {
            $force = (bool) ($payload['force_three_way_match'] ?? false);

            throw_if(
                $force && ! $user->can('forcePost', $record),
                AuthorizationException::class,
                'User not allowed to force the three-way match',
            );

            $record->forceThreeWayMatchOnPosting = $force;
            $record->update(['posted_at' => now()]);

            return $record->fresh();
        });

        $registry->register(Invoice::class, 'unpost', static function (Model $record, array $payload, User $user): Model {
            $record->update(['posted_at' => null]);

            return $record->fresh();
        });

        $registry->register(Invoice::class, 'submitEInvoice', static fn (Model $record, array $payload, User $user): Model => resolve(EInvoiceSubmissionService::class)->submit($record));
    }

    private function registerAccounting(DomainActionRegistry $registry): void
    {
        /**
         * reverse() needs the owning company and an explicit reason; the reason is
         * required because a reversal is an auditable accounting event.
         */
        $registry->register(JournalEntry::class, 'reverse', static function (Model $record, array $payload, User $user): Model {
            $reason = trim((string) ($payload['reversal_reason'] ?? ''));

            throw_if(
                $reason === '',
                ValidationException::withMessages(['reversal_reason' => ['A reversal reason is required.']]),
            );

            return resolve(JournalPostingService::class)->reverse($record, $record->company, $reason, $user->id);
        });

        // FiscalPeriodCloser and the delivery-note path return void; hand back the
        // refreshed record so the response carries the new state.
        $registry->register(FiscalPeriod::class, 'close', static function (Model $record, array $payload, User $user): Model {
            resolve(FiscalPeriodCloser::class)->closePeriod($record);

            return $record->fresh();
        });

        $registry->register(FiscalPeriod::class, 'reopen', static function (Model $record, array $payload, User $user): Model {
            resolve(FiscalPeriodCloser::class)->reopenPeriod($record);

            return $record->fresh();
        });

        $registry->register(FiscalYear::class, 'close', static function (Model $record, array $payload, User $user): Model {
            resolve(FiscalPeriodCloser::class)->closeYear($record);

            return $record->fresh();
        });

        $registry->register(SalesOrder::class, 'amend', static function (Model $record, array $payload, User $user): Model {
            resolve(SalesOrderAmendmentService::class)->amend($record);

            return $record->fresh();
        });

        $registry->register(DeliveryNote::class, 'post', static function (Model $record, array $payload, User $user): Model {
            $record->update(['posted_at' => now()]);

            return $record->fresh();
        });

        $registry->register(DeliveryNote::class, 'unpost', static function (Model $record, array $payload, User $user): Model {
            $record->update(['posted_at' => null]);

            return $record->fresh();
        });
    }
}
```

The handlers above were derived by reading the Filament action classes, which are
the authority — several do **not** call the service you would expect:

| Action | What actually happens | Do not assume |
|--------|----------------------|---------------|
| `Invoice::post` | sets `posted_at`; an observer does the work | `InvoicePostingService::post()` returns `void` and is not the UI path |
| `force_post` | a payload flag on `post` guarded by the `forcePost` permission | it is **not** a separate action |
| `JournalEntry::reverse` | `reverse(JournalEntry, Company, string $reason, ?int $user_id)` | not `reverse($record)` |
| `FiscalPeriodCloser::*` | all return `void` | they do not return the record |
| `SalesOrder::amend` | returns `void` | same |

Confirm each one against `InvoicePostingActions`, `JournalEntryActions`,
`FiscalPeriodActions`, `FiscalYearActions`, `SalesOrderAmendmentActions` and
`DeliveryNotePostingActions` before writing, and correct the registrar to match
the code — never the reverse.

Add the imports these handlers need: `Illuminate\Auth\Access\AuthorizationException`,
`Illuminate\Validation\ValidationException`,
`Modules\ERP\Services\EInvoice\EInvoiceSubmissionService`.

- [x] **Step 4: Call the registrar at boot**

In `ERPServiceProvider::boot()`, after the policy loop:

```php
        resolve(ErpDomainActionRegistrar::class)->register(resolve(DomainActionRegistry::class));
```

Add the imports for `ErpDomainActionRegistrar` and `DomainActionRegistry`. Bind `DomainActionRegistry` as a singleton in `Modules/Core/app/Providers/CoreServiceProvider.php` `register()` so every module writes into the same instance:

```php
        $this->app->singleton(DomainActionRegistry::class);
```

- [x] **Step 5: Run the test and watch it pass**

```bash
php artisan test --compact Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php
```

Expected: `1 passed`.

- [x] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core commit -am "feat(core): bind the domain action registry as a singleton"
git -C Modules/ERP add app/Services/DomainActions/ app/Providers/ERPServiceProvider.php tests/Feature/Http/
git -C Modules/ERP commit -m "feat(erp): register accounting domain actions"
```

---

## Task 8: ERP registrar — returns and the `approve` override (3-01)

**Files:**
- Modify: `Modules/ERP/app/Models/ReturnOrder.php` — implement `OverridesGenericCrudActions`
- Modify: `Modules/ERP/app/Models/SupplierReturn.php` — implement `OverridesGenericCrudActions`
- Modify: `Modules/ERP/app/Services/DomainActions/ErpDomainActionRegistrar.php`
- Modify: `Modules/ERP/app/Providers/ERPServiceProvider.php` — add both returns to `policyModels()`
- Test: `Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php` (extend)

**Interfaces:**
- Consumes: `OverridesGenericCrudActions` from Task 3, the guard from Task 4

`approve` on a return transitions `Draft → Approved`; Core's `approve` votes on a pending `Modification`. Neither return uses `HasApprovals`, so the guard from Task 4 lets the override through — and will stop the app if either ever takes the trait.

- [x] **Step 1: Write the failing test**

Append to `Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php`:

```php
it('declares approve as an overridden generic verb on both returns', function (): void {
    expect(Modules\ERP\Models\ReturnOrder::overriddenCrudActions())->toBe(['approve'])
        ->and(Modules\ERP\Models\SupplierReturn::overriddenCrudActions())->toBe(['approve']);
});

it('registers the return lifecycle actions', function (): void {
    $registry = app(DomainActionRegistry::class);

    expect($registry->has(Modules\ERP\Models\ReturnOrder::class, 'approve'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\ReturnOrder::class, 'complete'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\ReturnOrder::class, 'cancel'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\ReturnOrder::class, 'reverse_processed'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\SupplierReturn::class, 'approve'))->toBeTrue();
});
```

- [x] **Step 2: Run the tests and watch them fail**

```bash
php artisan test --compact Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php
```

Expected: `Call to undefined method … overriddenCrudActions()`.

- [x] **Step 3: Declare the override on both models**

In `Modules/ERP/app/Models/ReturnOrder.php`, add `implements OverridesGenericCrudActions` to the class declaration and:

```php
    /**
     * Core's `approve` votes on a pending Modification; here it advances the
     * return from Draft to Approved. Because this model claims the verb, it must
     * never take HasApprovals — DomainActionRegistry refuses that combination at
     * boot rather than letting `approve` mean two things at once.
     *
     * @return list<string>
     */
    #[Override]
    public static function overriddenCrudActions(): array
    {
        return ['approve'];
    }
```

Repeat verbatim in `Modules/ERP/app/Models/SupplierReturn.php`. Add the `Modules\Core\Contracts\OverridesGenericCrudActions` and `Override` imports to both.

- [x] **Step 4: Register the return actions**

Add to `ErpDomainActionRegistrar::register()` a `$this->registerReturns($registry);` call and the method:

```php
    private function registerReturns(DomainActionRegistry $registry): void
    {
        $registry->register(ReturnOrder::class, 'approve', static fn (Model $record, array $payload, User $user): Model => resolve(ReturnOrderService::class)->approve($record));
        $registry->register(ReturnOrder::class, 'complete', static fn (Model $record, array $payload, User $user): Model => resolve(ReturnOrderService::class)->complete($record));
        $registry->register(ReturnOrder::class, 'cancel', static fn (Model $record, array $payload, User $user): Model => resolve(ReturnOrderService::class)->cancel($record));
        $registry->register(ReturnOrder::class, 'reverse_processed', static fn (Model $record, array $payload, User $user): Model => resolve(ReturnOrderService::class)->reverseProcessed($record));
        $registry->register(ReturnOrder::class, 'create_credit_note', static fn (Model $record, array $payload, User $user): Model => resolve(ReturnOrderService::class)->createCreditNote($record));

        $registry->register(SupplierReturn::class, 'approve', static fn (Model $record, array $payload, User $user): Model => resolve(SupplierReturnService::class)->approve($record));
        $registry->register(SupplierReturn::class, 'complete', static fn (Model $record, array $payload, User $user): Model => resolve(SupplierReturnService::class)->complete($record));
        $registry->register(SupplierReturn::class, 'cancel', static fn (Model $record, array $payload, User $user): Model => resolve(SupplierReturnService::class)->cancel($record));
        $registry->register(SupplierReturn::class, 'reverse_processed', static fn (Model $record, array $payload, User $user): Model => resolve(SupplierReturnService::class)->reverseProcessed($record));
        $registry->register(SupplierReturn::class, 'create_debit_note', static fn (Model $record, array $payload, User $user): Model => resolve(SupplierReturnService::class)->createDebitNote($record));
    }
```

Verify each method name against `ReturnOrderService` and `SupplierReturnService` before writing; correct the registrar to match the services, never the reverse.

- [x] **Step 5: Add both returns to `policyModels()`**

`ERPModelPolicy` needs to govern them, otherwise `Gate` finds no policy and every action is denied. Add `ReturnOrder::class` and `SupplierReturn::class` to `ERPServiceProvider::policyModels()`.

`ERPModelPolicy` has no `approve`, `complete`, `cancel` or `reverseProcessed` method yet — add them following the existing `allowsDomainAction()` pattern, each with the state predicate the corresponding Filament action already uses for `->visible()`:

```php
    public function approve(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'approve', static function (Model $record): bool {
            if (! $record instanceof ReturnOrder && ! $record instanceof SupplierReturn) {
                return false;
            }

            return $record->status === ReturnStatus::Draft;
        });
    }
```

Seed the matching permissions in `ERPDatabaseSeeder::domainPermissions()` using `PermissionName::forClass()` from Task 1.

- [x] **Step 6: Run the tests and watch them pass**

```bash
php artisan test --compact Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php Modules/ERP/tests/Feature/Services/ReturnOrderServiceTest.php Modules/ERP/tests/Feature/Services/SupplierReturnServiceTest.php
```

Expected: all green.

- [x] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/ERP commit -am "feat(erp): register return lifecycle actions and declare the approve override"
```

---

## Task 9: ERP registrar — remaining actions including file streaming (3-01)

**Files:**
- Modify: `Modules/ERP/app/Services/DomainActions/ErpDomainActionRegistrar.php`
- Test: `Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php` (extend)

**Interfaces:**
- Consumes: the binary response passthrough from Task 6

File actions return a `Symfony\Component\HttpFoundation\Response`, which Task 6's controller returns unchanged. Authorization and state guards run before the first byte, so a refusal is still a JSON error.

- [x] **Step 1: Write the failing test**

Append to `Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php`:

```php
it('registers the remaining domain actions', function (): void {
    $registry = app(DomainActionRegistry::class);

    expect($registry->has(Modules\ERP\Models\Invoice::class, 'submitEInvoice'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\Invoice::class, 'refreshEInvoice'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\DocumentSequence::class, 'reset'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\DocumentSequence::class, 'reserve'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\TaxCode::class, 'supersede'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\Company::class, 'switch_context'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\Quotation::class, 'create_revision'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\PartnerPool::class, 'allocate_expense'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\PartnerPool::class, 'settle_up'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\PaymentRequest::class, 'send'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\VatSettlement::class, 'compute_settlement'))->toBeTrue();
});

it('registers the file actions', function (): void {
    $registry = app(DomainActionRegistry::class);

    expect($registry->has(Modules\ERP\Models\PaymentRun::class, 'export_sepa'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\PaymentRun::class, 'export_cbi_bonifici'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\Task::class, 'export_ics'))->toBeTrue()
        ->and($registry->has(Modules\ERP\Models\BankStatement::class, 'import_file'))->toBeTrue();
});
```

- [x] **Step 2: Run the tests and watch them fail**

```bash
php artisan test --compact Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php
```

Expected: fails on the first `toBeTrue()` of each new test.

- [x] **Step 3: Register the remaining non-file actions**

Add `$this->registerAdministration($registry);` and `$this->registerCommercial($registry);` to `register()`:

```php
    private function registerAdministration(DomainActionRegistry $registry): void
    {
        $registry->register(Invoice::class, 'refreshEInvoice', static function (Model $record, array $payload, User $user): Model {
            $submission = $record->einvoice_submissions()->latest()->firstOrFail();

            return resolve(EInvoiceSubmissionService::class)->refresh($submission);
        });

        $registry->register(DocumentSequence::class, 'reset', static function (Model $record, array $payload, User $user): Model {
            resolve(DocumentSequenceResetService::class)->reset($record, (int) ($payload['last_number'] ?? 0));

            return $record->fresh();
        });
    }
```

`refreshEInvoice` needs the relation name and `reserve`, `supersede` and
`switch_context` need their service entry points — none of the three has a
Filament action today, so read the policy and the service before registering
them. Register only what a service already implements; a domain action must not
introduce new business logic.

Each model reached here must also be in `ERPServiceProvider::policyModels()` with
its policy method — `PartnerPool`, `PaymentRequest`, `VatSettlement`, `PaymentRun`,
`Task` and `BankStatement` are not there yet, so `Gate` currently finds no policy
and would deny every action. Add them, give each policy method the state predicate
its Filament action uses in `->visible()`, and seed the permissions with
`PermissionName::forClass()`.

- [x] **Step 4: Register the file actions**

```php
    private function registerFileActions(DomainActionRegistry $registry): void
    {
        $registry->register(PaymentRun::class, 'export_sepa', static function (Model $record, array $payload, User $user): StreamedResponse {
            $xml = resolve(SepaPain001Exporter::class)->export($record);

            return response()->streamDownload(
                static fn () => print ($xml),
                sprintf('payment-run-%d-sepa.xml', $record->getKey()),
                ['Content-Type' => 'application/xml'],
            );
        });
    }
```

Follow the same shape for `export_cbi_bonifici` (`CbiBonificiExporter`) and `export_ics` (`TaskIcsExporter`). For `import_file`, read the uploaded file from `$payload` and delegate to `BankStatementImportService`, returning the line count as a normal value so it is wrapped in a `CrudResult`:

```php
        $registry->register(BankStatement::class, 'import_file', static function (Model $record, array $payload, User $user): array {
            $file = request()->file('file');

            throw_if($file === null, ValidationException::withMessages(['file' => ['An uploaded file is required.']]));

            return ['imported_lines' => resolve(BankStatementImportService::class)->importFile($record, $file->getRealPath())];
        });
```

Verify `BankStatementImportService::importFile()`'s real signature before writing this.

- [x] **Step 5: Run the tests and watch them pass**

```bash
php artisan test --compact Modules/ERP/tests/Feature/Http/ErpDomainActionRouteTest.php
```

Expected: all green.

- [x] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/ERP commit -am "feat(erp): register administration, commercial and file domain actions"
```

---

## Task 10: HTTP behaviour matrix (3-06)

**Files:**
- Create: `Modules/ERP/tests/Feature/Http/ErpDomainActionsHttpTest.php`

**Interfaces:**
- Consumes: the route from Task 6 and every registration from Tasks 7–9

Tasks 7–9 proved actions are *registered*. This one proves they *behave*: the same action must answer differently to an authorized user, an unauthorized one, and a record in the wrong state.

- [x] **Step 1: Write the matrix test**

Create `Modules/ERP/tests/Feature/Http/ErpDomainActionsHttpTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\ERP\Models\Invoice;

uses(RefreshDatabase::class);

function domainActionUrl(string $action, string $entity): string
{
    return route('core.crud.domain-action', ['action' => $action, 'module' => 'erp', 'entity' => $entity]);
}

it('posts a draft invoice for a user holding the permission', function (): void {
    $invoice = policyInvoice();
    $user = User::factory()->create();
    Permission::findOrCreate(PermissionName::forModel($invoice, 'post'), 'web');
    $user->givePermissionTo(PermissionName::forModel($invoice, 'post'));

    $response = $this->actingAs($user)->postJson(domainActionUrl('post', 'invoices'), ['id' => $invoice->id]);

    $response->assertOk();
    expect($invoice->fresh()?->journal_entry_id)->not->toBeNull();
});

it('refuses to post for a user without the permission', function (): void {
    $invoice = policyInvoice();

    $response = $this->actingAs(User::factory()->create())
        ->postJson(domainActionUrl('post', 'invoices'), ['id' => $invoice->id]);

    $response->assertUnauthorized();
    expect($invoice->fresh()?->journal_entry_id)->toBeNull();
});

it('refuses to post an invoice that is already posted', function (): void {
    $invoice = policyInvoice();
    $user = User::factory()->create();
    Permission::findOrCreate(PermissionName::forModel($invoice, 'post'), 'web');
    $user->givePermissionTo(PermissionName::forModel($invoice, 'post'));

    $this->actingAs($user)->postJson(domainActionUrl('post', 'invoices'), ['id' => $invoice->id])->assertOk();

    $this->actingAs($user)
        ->postJson(domainActionUrl('post', 'invoices'), ['id' => $invoice->id])
        ->assertUnauthorized();
});

it('returns 404 for an action that is not registered on the entity', function (): void {
    $invoice = policyInvoice();

    $this->actingAs(User::factory()->create())
        ->postJson(domainActionUrl('settle_up', 'invoices'), ['id' => $invoice->id])
        ->assertNotFound();
});

it('returns 404 for a record that does not exist', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(domainActionUrl('post', 'invoices'), ['id' => 999999])
        ->assertNotFound();
});
```

`policyInvoice()` is the existing helper in `Modules/ERP/tests/Feature/ErpModelPolicyTest.php`. Pest helpers are file-scoped: copy it into this file rather than importing it, and rename it `httpDomainActionInvoice()` to avoid a redeclaration clash when both files load in one process.

- [x] **Step 2: Run the tests**

```bash
php artisan test --compact Modules/ERP/tests/Feature/Http/
```

Fix the implementation, not the tests, until green. An invalid-state refusal arriving as 500 rather than 401 means Task 6 Step 5's exception mapping is wrong or ordered wrong.

- [x] **Step 3: Run the full regression**

```bash
php artisan test --compact Modules/ERP/tests/Feature
php artisan test --compact Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php
php artisan test --compact Modules/Core/tests/Feature/Services Modules/Core/tests/Feature/Http Modules/Core/tests/Feature/Controllers Modules/Core/tests/Feature/Api
```

The accounting golden master must stay green: domain actions now reach posting services over HTTP, and that suite is what proves the ledger did not move.

**Known baseline:** the full Core feature suite is not green and was not before this work — `handle_test_overrides.php` shadows helpers for the whole `Modules\Core\Console` namespace once loaded, so results vary with test ordering. Compare against a baseline run on `HEAD~`, not against zero.

- [x] **Step 4: Update module docs**

Document the new surface in `Modules/Core/docs/CRUD_SYSTEM.md` next to the "Internal-only Operations" table: the route, the registry, the override contract and the boot-time guard. Add the ERP action inventory to the ERP developer RAG docs.

- [x] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git -C Modules/Core commit -am "docs(core): document the domain action surface"
git -C Modules/ERP add tests/Feature/Http/ErpDomainActionsHttpTest.php
git -C Modules/ERP commit -m "test(erp): cover the domain action HTTP matrix"
```

---

## Final verification

- [x] `php artisan route:check app/crud/select/erp/invoices --method=GET` → `core.crud.list`
- [x] `php artisan route:check app/crud/post/erp/invoices --method=POST` → `core.crud.domain-action`
- [x] `php artisan route:check app/crud/detail/ai/conversations/5 --method=GET` → `ai.crud.conversations.detail`
- [x] No domain-action route appears under `/api/v1`: `php artisan route:list --path=api/v1 | grep -c domain-action` returns `0`
- [x] Update the master spec: move `3-01`, `3-04`, `3-06` to § Completed with commit SHAs
- [x] Evaluate Core + ERP version bumps per `08-versioning.mdc`; ask before `composer version:*`

## Self-review notes

Spec coverage: D1 → Task 6 Step 6; D2 → Task 3; D3 → Tasks 4 and 8; D4 → Tasks 1, 2, 5; D5 → Task 6 Steps 4–5 and Task 9; D6 → no task, `unlock` is deliberately not registered; D7 → Final verification, asserted rather than assumed.

Two places where this plan tells the implementer to check reality before writing: the exact service method names in Tasks 7–9, and `BankStatementImportService::importFile()`'s signature. Both are cases where the plan's table comes from the design document and the code is the authority.
