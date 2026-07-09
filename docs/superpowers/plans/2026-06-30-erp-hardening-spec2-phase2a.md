# ERP Spec 2 — Phase 2A Implementation Plan

> **Navigation:** Implements backlog IDs `2A-01`…`2A-09` from
> [`specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md`](../specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md).
> Closes partial items PART-01…PART-04. PART-05 stays in Phase 2B (`2B-11`).

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Ready for implementation  
**Goal:** Wire existing ERP domain services into Filament actions, seed missing domain permissions, and make `ERPModelPolicy` state-aware so invalid transitions are denied even for superadmin.

**Architecture:** Nine independent workstreams on `Modules/ERP` only. Each task is test-first (Pest + `RefreshDatabase`, manual model setup). Action classes follow `InvoicePostingActions`: static factories returning Filament `Action`, authorize via `auth()->user()?->can(...)`, delegate to existing services/observers. Policy checks **state first**, then permission; superadmin skips permission only.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Spatie Permission, Pest, nwidart modules.

**Conventions (verified):**
- Run ERP tests from repo root: `php artisan test --compact Modules/ERP/tests/Feature/<File>.php`
- ERP Pest auto-loads `tests/Feature` and `tests/Integration` only — put new tests under `Feature` unless exercising a service in isolation warrants `Integration`.
- `Modules/ERP` is a **git submodule** — commit each task on ERP `master` from inside `Modules/ERP`.
- Superadmin role: `config('permission.roles.superadmin')`; `Gate::before` in Core bypasses policies — state guards inside `ERPModelPolicy` must return `false` before any superadmin shortcut.
- Format after every task: `vendor/bin/pint --dirty` (from repo root).
- Local/private variables: `snake_case`; methods/properties: `camelCase`.

---

## File map (created / modified)

| File | Responsibility |
|------|----------------|
| `database/seeders/ERPDatabaseSeeder.php` | Seed `close`, `reopen`, `reverse`, `amend`, `force_post` |
| `app/Policies/ERPModelPolicy.php` | State guards + `reverse`, `amend`, `forcePost` |
| `app/Providers/ERPServiceProvider.php` | Register `FiscalYear` policy |
| `app/Filament/Resources/Invoices/Actions/InvoicePostingActions.php` | `force_post` checkbox visibility |
| `app/Filament/Resources/Invoices/Pages/EditInvoice.php` | Same `force_post` gate on inline post action |
| `app/Filament/Resources/FiscalPeriods/Actions/FiscalPeriodActions.php` | Close/reopen period |
| `app/Filament/Resources/FiscalPeriods/Pages/EditFiscalPeriod.php` | Wire header actions |
| `app/Filament/Resources/FiscalPeriods/Schemas/FiscalPeriodForm.php` | Read-only `is_closed` |
| `app/Filament/Resources/FiscalYears/Actions/FiscalYearActions.php` | Close year |
| `app/Filament/Resources/FiscalYears/Pages/EditFiscalYear.php` | Wire header action |
| `app/Filament/Resources/FiscalYears/Schemas/FiscalYearForm.php` | Read-only `is_closed` |
| `app/Filament/Resources/DeliveryNotes/Actions/DeliveryNotePostingActions.php` | Post/unpost DDT |
| `app/Filament/Resources/DeliveryNotes/Pages/EditDeliveryNote.php` | Wire header actions |
| `app/Filament/Resources/JournalEntries/Actions/JournalEntryActions.php` | Reverse journal |
| `app/Filament/Resources/JournalEntries/Pages/ViewJournalEntry.php` | Wire header action |
| `app/Filament/Resources/SalesOrders/Actions/SalesOrderAmendmentActions.php` | Amend SO |
| `app/Filament/Resources/SalesOrders/Pages/EditSalesOrder.php` | Wire header action |
| `tests/Feature/ErpDomainPermissionsSeederTest.php` | Extend for new permissions |
| `tests/Feature/ErpModelPolicyStateTest.php` | State-aware policy regression |
| `tests/Feature/Filament/ErpDomainActionsSmokeTest.php` | Action class + page wiring smoke |

---

## State guard matrix (2A-02)

Superadmin must **not** bypass these. Permission check runs only when state allows.

| Policy method | Model | State allows when |
|---------------|-------|-------------------|
| `post` | `Invoice` | `journal_entry_id === null` |
| `unpost` | `Invoice` | `journal_entry_id !== null` |
| `forcePost` | `Invoice` | `direction === Purchase` **and** `journal_entry_id === null` |
| `post` | `DeliveryNote` | `posted_at === null` |
| `unpost` | `DeliveryNote` | `posted_at !== null` |
| `close` | `FiscalPeriod` | `is_closed === false` |
| `reopen` | `FiscalPeriod` | `is_closed === true` |
| `close` | `FiscalYear` | `is_closed === false` |
| `reverse` | `JournalEntry` | `posted_at !== null` **and** no `reversal_voucher` |
| `amend` | `SalesOrder` | `status` in `Confirmed`, `PartiallyEvased` |

`submitEInvoice` / `refreshEInvoice` stay permission-only (no new state rules in 2A).

---

## Task 1: Seed domain permissions (2A-01)

**Files:**
- Modify: `Modules/ERP/database/seeders/ERPDatabaseSeeder.php` — method `domainPermissions()`
- Modify: `Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `ErpDomainPermissionsSeederTest.php`:

```php
it('seeds Phase 2A domain permissions for fiscal and commercial models', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    expect(Permission::query()->where('name', 'default.erp_fiscal_periods.close')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_fiscal_periods.reopen')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_fiscal_years.close')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_journal_entries.reverse')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_sales_orders.amend')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_invoices.force_post')->exists())->toBeTrue();
});

it('does not seed force_post on non-invoice models', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    expect(Permission::query()->where('name', 'default.erp_sales_orders.force_post')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'default.erp_delivery_notes.force_post')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php`  
Expected: FAIL — `default.erp_fiscal_periods.close` does not exist.

- [ ] **Step 3: Extend `domainPermissions()`**

Replace the method body with:

```php
    private function domainPermissions(): array
    {
        $entities = [
            DeliveryNote::class,
            FiscalPeriod::class,
            Invoice::class,
            JournalEntry::class,
            SalesOrder::class,
        ];
        $permissions = [];

        foreach ($entities as $model) {
            $instance = new ReflectionClass($model)->newInstanceWithoutConstructor();

            $connection = $instance->getConnectionName() ?? 'default';
            $table = $instance->getTable();

            $permissions[] = "{$connection}.{$table}.post";
            $permissions[] = "{$connection}.{$table}.unpost";

            if ($model === Invoice::class) {
                $permissions[] = "{$connection}.{$table}.submitEInvoice";
                $permissions[] = "{$connection}.{$table}.refreshEInvoice";
                $permissions[] = "{$connection}.{$table}.force_post";
            }

            if ($model === FiscalPeriod::class) {
                $permissions[] = "{$connection}.{$table}.close";
                $permissions[] = "{$connection}.{$table}.reopen";
            }

            if ($model === JournalEntry::class) {
                $permissions[] = "{$connection}.{$table}.reverse";
            }

            if ($model === SalesOrder::class) {
                $permissions[] = "{$connection}.{$table}.amend";
            }
        }

        $fiscal_year = new ReflectionClass(FiscalYear::class)->newInstanceWithoutConstructor();
        $fy_connection = $fiscal_year->getConnectionName() ?? 'default';
        $fy_table = $fiscal_year->getTable();
        $permissions[] = "{$fy_connection}.{$fy_table}.close";

        return $permissions;
    }
```

Add `use Modules\ERP\Models\FiscalYear;` at the top of the seeder if missing.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php`  
Expected: PASS (4 tests).

- [ ] **Step 5: Format and commit (ERP submodule)**

```bash
vendor/bin/pint --dirty
cd Modules/ERP && git add database/seeders/ERPDatabaseSeeder.php tests/Feature/ErpDomainPermissionsSeederTest.php
git commit -m "feat(erp): seed Phase 2A domain permissions"
```

---

## Task 2: State-aware ERPModelPolicy (2A-02)

**Files:**
- Modify: `Modules/ERP/app/Policies/ERPModelPolicy.php`
- Create: `Modules/ERP/tests/Feature/ErpModelPolicyStateTest.php`

- [ ] **Step 1: Write the failing tests**

Create `Modules/ERP/tests/Feature/ErpModelPolicyStateTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Policies\ERPModelPolicy;

uses(RefreshDatabase::class);

function statePolicyCompany(): Company
{
    return Company::query()->create([
        'slug' => 'state-policy-' . uniqid(),
        'name' => 'State Policy Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

function grantPermission(User $user, object $record, string $operation): void
{
    $permission = sprintf(
        '%s.%s.%s',
        $record->getConnectionName() ?? 'default',
        $record->getTable(),
        $operation,
    );
    Permission::findOrCreate($permission, 'web');
    $user->givePermissionTo($permission);
}

it('denies invoice post when already posted even for superadmin', function (): void {
    $company = statePolicyCompany();
    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'P', 'is_customer' => true]);
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => Modules\ERP\Casts\InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'journal_entry_id' => 99,
    ]);

    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    expect(app(ERPModelPolicy::class)->post($user, $invoice))->toBeFalse();
});

it('allows invoice post when draft and user has permission', function (): void {
    $company = statePolicyCompany();
    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'P', 'is_customer' => true]);
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => Modules\ERP\Casts\InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'journal_entry_id' => null,
    ]);

    $user = User::factory()->create();
    grantPermission($user, $invoice, 'post');

    expect(app(ERPModelPolicy::class)->post($user, $invoice))->toBeTrue();
});

it('denies fiscal period close when already closed', function (): void {
    FiscalPeriod::disableVersioning();
    $company = statePolicyCompany();
    $year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_closed' => false,
    ]);
    $period = FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->id,
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_closed' => true,
    ]);
    FiscalPeriod::enableVersioning();

    $user = User::factory()->create();
    grantPermission($user, $period, 'close');

    expect(app(ERPModelPolicy::class)->close($user, $period))->toBeFalse();
});

it('allows fiscal period reopen only when closed', function (): void {
    FiscalPeriod::disableVersioning();
    $company = statePolicyCompany();
    $year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_closed' => false,
    ]);
    $open = FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->id,
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_closed' => false,
    ]);
    $closed = FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->id,
        'period_no' => 2,
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
        'is_closed' => true,
    ]);
    FiscalPeriod::enableVersioning();

    $user = User::factory()->create();
    grantPermission($user, $closed, 'reopen');

    $policy = app(ERPModelPolicy::class);

    expect($policy->reopen($user, $open))->toBeFalse()
        ->and($policy->reopen($user, $closed))->toBeTrue();
});

it('denies journal reverse when entry is not posted', function (): void {
    $company = statePolicyCompany();
    $entry = JournalEntry::query()->create([
        'company_id' => $company->id,
        'posted_at' => null,
    ]);

    $user = User::factory()->create();
    grantPermission($user, $entry, 'reverse');

    expect(app(ERPModelPolicy::class)->reverse($user, $entry))->toBeFalse();
});

it('denies sales order amend when status is draft', function (): void {
    $company = statePolicyCompany();
    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'P', 'is_customer' => true]);
    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Draft,
    ]);

    $user = User::factory()->create();
    grantPermission($user, $order, 'amend');

    expect(app(ERPModelPolicy::class)->amend($user, $order))->toBeFalse();
});

it('allows forcePost only on draft purchase invoices', function (): void {
    $company = statePolicyCompany();
    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'P', 'is_supplier' => true]);
    $purchase = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => Modules\ERP\Casts\InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'journal_entry_id' => null,
    ]);
    $sale = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => Modules\ERP\Casts\InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'journal_entry_id' => null,
    ]);

    $user = User::factory()->create();
    grantPermission($user, $purchase, 'force_post');

    $policy = app(ERPModelPolicy::class);

    expect($policy->forcePost($user, $purchase))->toBeTrue()
        ->and($policy->forcePost($user, $sale))->toBeFalse();
});

it('denies delivery note post when already posted', function (): void {
    $company = statePolicyCompany();
    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'direction' => Modules\ERP\Casts\DeliveryNoteDirection::Outbound,
        'posted_at' => now(),
    ]);

    $user = User::factory()->create();
    grantPermission($user, $note, 'post');

    expect(app(ERPModelPolicy::class)->post($user, $note))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact Modules/ERP/tests/Feature/ErpModelPolicyStateTest.php`  
Expected: FAIL — superadmin can post already-posted invoice (no state guard yet).

- [ ] **Step 3: Implement state-aware policy**

Refactor `ERPModelPolicy.php`:

1. Add imports: `Invoice`, `DeliveryNote`, `FiscalPeriod`, `FiscalYear`, `JournalEntry`, `SalesOrder`, `InvoiceDirection`, `SalesOrderStatus`.
2. Add public methods `reverse()`, `amend()`, `forcePost()` delegating to `allowsDomainAction()`.
3. Change `post`, `unpost`, `close`, `reopen` to use `allowsDomainAction()` instead of `allows()`.
4. Extract `allowsDomainAction(User $user, Model $record, string $operation, callable $state_allows): bool`:

```php
    private function allowsDomainAction(User $user, Model $record, string $operation, callable $state_allows): bool
    {
        if (! $state_allows($record)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->hasPermission($user, $record, $operation);
    }
```

5. Rename current `allows()` body (without superadmin early return) to `hasPermission()`.
6. Keep `view`/`update`/`delete`/`restore`/`forceDelete`/`submitEInvoice`/`refreshEInvoice` on `allows()` (permission-only) unchanged.

State callables (examples):

```php
    public function post(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'post', function (Model $record): bool {
            if ($record instanceof Invoice) {
                return $record->journal_entry_id === null;
            }

            if ($record instanceof DeliveryNote) {
                return $record->posted_at === null;
            }

            return true;
        });
    }

    public function reverse(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'reverse', function (Model $record): bool {
            if (! $record instanceof JournalEntry) {
                return false;
            }

            if ($record->posted_at === null) {
                return false;
            }

            return ! $record->reversal_voucher()->exists();
        });
    }

    public function forcePost(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'force_post', function (Model $record): bool {
            if (! $record instanceof Invoice) {
                return false;
            }

            return $record->direction === InvoiceDirection::Purchase
                && $record->journal_entry_id === null;
        });
    }
```

Implement analogous guards for `unpost`, `close`, `reopen`, `amend` per the matrix above.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact Modules/ERP/tests/Feature/ErpModelPolicyStateTest.php Modules/ERP/tests/Feature/ErpModelPolicyTest.php`  
Expected: all PASS.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty
cd Modules/ERP && git add app/Policies/ERPModelPolicy.php tests/Feature/ErpModelPolicyStateTest.php
git commit -m "feat(erp): add state-aware domain policy guards"
```

---

## Task 3: `force_post` gate on invoice post checkbox (2A-03)

**Files:**
- Modify: `Modules/ERP/app/Filament/Resources/Invoices/Actions/InvoicePostingActions.php`
- Modify: `Modules/ERP/app/Filament/Resources/Invoices/Pages/EditInvoice.php`
- Modify: `Modules/ERP/tests/Feature/ErpModelPolicyStateTest.php` (optional assertion that unauthorized user cannot `forcePost`)

- [ ] **Step 1: Write failing test** — covered by Task 2 `forcePost` tests; add one Filament-oriented test in `ErpDomainActionsSmokeTest.php` (Task 9) for checkbox visibility. For Task 3, manual verification: user without `force_post` must not see checkbox.

- [ ] **Step 2: Gate checkbox in `InvoicePostingActions::post()`**

Change the purchase-invoice form closure:

```php
            ->form(static fn (Invoice $record): array => $record->direction === InvoiceDirection::Purchase
                && (auth()->user()?->can('forcePost', $record) ?? false)
                ? [
                    Checkbox::make('force_three_way_match')
                        ->label('Force three-way match')
                        ->helperText('Post even when PO/GR price or quantity discrepancies exceed configured tolerances.'),
                ]
                : [])
```

- [ ] **Step 3: Mirror the same gate in `EditInvoice::getHeaderActions()`** — the page duplicates the post action inline (lines 106–128); apply the identical `form(fn (): array => ...)` condition.

- [ ] **Step 4: Run regression**

Run: `php artisan test --compact Modules/ERP/tests/Feature/ErpModelPolicyStateTest.php`  
Expected: PASS.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty
cd Modules/ERP && git add app/Filament/Resources/Invoices/Actions/InvoicePostingActions.php app/Filament/Resources/Invoices/Pages/EditInvoice.php
git commit -m "feat(erp): gate force 3-way match checkbox on force_post permission"
```

---

## Task 4: Fiscal period close/reopen actions + form hardening (2A-05)

**Files:**
- Create: `Modules/ERP/app/Filament/Resources/FiscalPeriods/Actions/FiscalPeriodActions.php`
- Modify: `Modules/ERP/app/Filament/Resources/FiscalPeriods/Pages/EditFiscalPeriod.php`
- Modify: `Modules/ERP/app/Filament/Resources/FiscalPeriods/Schemas/FiscalPeriodForm.php`
- Modify: `Modules/ERP/tests/Integration/Services/Accounting/FiscalPeriodCloserTest.php` (add reopen test if missing)

- [ ] **Step 1: Add reopen integration test (if absent)**

Append to `FiscalPeriodCloserTest.php`:

```php
it('reopens a closed fiscal period', function (): void {
    [, , $period] = fiscalPeriodCloserFixtures();
    $period->is_closed = true;
    $period->setSkipValidation(true);
    $period->save();

    (new FiscalPeriodCloser())->reopenPeriod($period);

    expect($period->fresh()->is_closed)->toBeFalse();
});
```

Run: `php artisan test --compact Modules/ERP/tests/Integration/Services/Accounting/FiscalPeriodCloserTest.php`

- [ ] **Step 2: Create `FiscalPeriodActions`**

```php
<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalPeriods\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Services\Accounting\FiscalPeriodCloser;

final class FiscalPeriodActions
{
    public static function close(): Action
    {
        return Action::make('close_period')
            ->label('Close period')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('warning')
            ->requiresConfirmation()
            ->authorize(static fn (FiscalPeriod $record): bool => auth()->user()?->can('close', $record) ?? false)
            ->visible(static fn (FiscalPeriod $record): bool => ! $record->is_closed)
            ->action(static function (FiscalPeriod $record): void {
                resolve(FiscalPeriodCloser::class)->closePeriod($record);

                Notification::make()->title('Fiscal period closed')->success()->send();
            });
    }

    public static function reopen(): Action
    {
        return Action::make('reopen_period')
            ->label('Reopen period')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('gray')
            ->requiresConfirmation()
            ->authorize(static fn (FiscalPeriod $record): bool => auth()->user()?->can('reopen', $record) ?? false)
            ->visible(static fn (FiscalPeriod $record): bool => $record->is_closed)
            ->action(static function (FiscalPeriod $record): void {
                resolve(FiscalPeriodCloser::class)->reopenPeriod($record);

                Notification::make()->title('Fiscal period reopened')->success()->send();
            });
    }
}
```

- [ ] **Step 3: Wire `EditFiscalPeriod`**

```php
    protected function getHeaderActions(): array
    {
        return [
            FiscalPeriodActions::close(),
            FiscalPeriodActions::reopen(),
            DeleteAction::make(),
        ];
    }
```

Add required imports (`DeleteAction`, `FiscalPeriodActions`).

- [ ] **Step 4: Harden form — read-only `is_closed`**

In `FiscalPeriodForm.php`, replace the toggle:

```php
                Toggle::make('is_closed')
                    ->label('Closed')
                    ->disabled()
                    ->dehydrated(false),
```

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty
cd Modules/ERP && git add app/Filament/Resources/FiscalPeriods/ tests/Integration/Services/Accounting/FiscalPeriodCloserTest.php
git commit -m "feat(erp): fiscal period close/reopen Filament actions"
```

---

## Task 5: Fiscal year close + policy registration (2A-06)

**Files:**
- Create: `Modules/ERP/app/Filament/Resources/FiscalYears/Actions/FiscalYearActions.php`
- Modify: `Modules/ERP/app/Filament/Resources/FiscalYears/Pages/EditFiscalYear.php`
- Modify: `Modules/ERP/app/Filament/Resources/FiscalYears/Schemas/FiscalYearForm.php`
- Modify: `Modules/ERP/app/Providers/ERPServiceProvider.php`
- Modify: `Modules/ERP/app/Policies/ERPModelPolicy.php` — `close()` must handle `FiscalYear`

- [ ] **Step 1: Register `FiscalYear` on policy map**

In `ERPServiceProvider::policyModels()` add `FiscalYear::class` and import.

Ensure `ERPModelPolicy::close()` state guard includes:

```php
            if ($record instanceof FiscalYear) {
                return ! $record->is_closed;
            }
```

- [ ] **Step 2: Create `FiscalYearActions`**

```php
<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalYears\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Services\Accounting\FiscalPeriodCloser;

final class FiscalYearActions
{
    public static function close(): Action
    {
        return Action::make('close_year')
            ->label('Close fiscal year')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Closes all open periods in this year and locks the fiscal year.')
            ->authorize(static fn (FiscalYear $record): bool => auth()->user()?->can('close', $record) ?? false)
            ->visible(static fn (FiscalYear $record): bool => ! $record->is_closed)
            ->action(static function (FiscalYear $record): void {
                resolve(FiscalPeriodCloser::class)->closeYear($record);

                Notification::make()->title('Fiscal year closed')->success()->send();
            });
    }
}
```

- [ ] **Step 3: Wire `EditFiscalYear` + read-only `is_closed` on form** (same `disabled()->dehydrated(false)` pattern as Task 4).

- [ ] **Step 4: Run policy tests**

Run: `php artisan test --compact Modules/ERP/tests/Feature/ErpModelPolicyStateTest.php`

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty
cd Modules/ERP && git add app/Filament/Resources/FiscalYears/ app/Providers/ERPServiceProvider.php app/Policies/ERPModelPolicy.php
git commit -m "feat(erp): fiscal year close Filament action and policy registration"
```

---

## Task 6: Delivery note post/unpost actions (2A-04)

**Files:**
- Create: `Modules/ERP/app/Filament/Resources/DeliveryNotes/Actions/DeliveryNotePostingActions.php`
- Modify: `Modules/ERP/app/Filament/Resources/DeliveryNotes/Pages/EditDeliveryNote.php`

- [ ] **Step 1: Create `DeliveryNotePostingActions`**

Mirror `InvoicePostingActions` but set/clear `posted_at` (observer handles inventory):

```php
<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DeliveryNotes\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\DeliveryNote;

final class DeliveryNotePostingActions
{
    public static function post(): Action
    {
        return Action::make('post')
            ->label('Post inventory')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->authorize(static fn (DeliveryNote $record): bool => auth()->user()?->can('post', $record) ?? false)
            ->visible(static fn (DeliveryNote $record): bool => $record->posted_at === null)
            ->action(static function (DeliveryNote $record): void {
                $record->update(['posted_at' => now()]);

                Notification::make()->title('Delivery note posted')->success()->send();
            });
    }

    public static function unpost(): Action
    {
        return Action::make('unpost')
            ->label('Unpost inventory')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->authorize(static fn (DeliveryNote $record): bool => auth()->user()?->can('unpost', $record) ?? false)
            ->visible(static fn (DeliveryNote $record): bool => $record->posted_at !== null)
            ->action(static function (DeliveryNote $record): void {
                $record->update(['posted_at' => null]);

                Notification::make()->title('Delivery note unposted')->success()->send();
            });
    }
}
```

- [ ] **Step 2: Wire `EditDeliveryNote::getHeaderActions()`** — prepend `DeliveryNotePostingActions::post()` and `::unpost()` before delete actions.

- [ ] **Step 3: Format and commit**

```bash
vendor/bin/pint --dirty
cd Modules/ERP && git add app/Filament/Resources/DeliveryNotes/
git commit -m "feat(erp): delivery note post/unpost Filament actions"
```

---

## Task 7: Journal reverse action (2A-07)

**Files:**
- Create: `Modules/ERP/app/Filament/Resources/JournalEntries/Actions/JournalEntryActions.php`
- Modify: `Modules/ERP/app/Filament/Resources/JournalEntries/Pages/ViewJournalEntry.php`

- [ ] **Step 1: Create `JournalEntryActions::reverse()`**

```php
<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Filament\Resources\JournalEntries\JournalEntryResource;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Services\Accounting\JournalPostingService;

final class JournalEntryActions
{
    public static function reverse(): Action
    {
        return Action::make('reverse')
            ->label('Reverse')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->requiresConfirmation()
            ->authorize(static fn (JournalEntry $record): bool => auth()->user()?->can('reverse', $record) ?? false)
            ->visible(static fn (JournalEntry $record): bool => $record->posted_at !== null
                && ! $record->reversal_voucher()->exists())
            ->form([
                Textarea::make('reversal_reason')
                    ->label('Reversal reason')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(static function (JournalEntry $record, array $data): void {
                $company = $record->company;
                $reversal = resolve(JournalPostingService::class)->reverse(
                    $record,
                    $company,
                    (string) $data['reversal_reason'],
                    auth()->id(),
                );

                Notification::make()
                    ->title('Journal reversed')
                    ->body('Reversal voucher #' . $reversal->id)
                    ->success()
                    ->send();
            })
            ->successRedirectUrl(static fn (JournalEntry $record): string => JournalEntryResource::getUrl('view', ['record' => $record]));
    }
}
```

- [ ] **Step 2: Add `getHeaderActions()` to `ViewJournalEntry`**

```php
    protected function getHeaderActions(): array
    {
        return [
            JournalEntryActions::reverse(),
        ];
    }
```

- [ ] **Step 3: Format and commit**

```bash
vendor/bin/pint --dirty
cd Modules/ERP && git add app/Filament/Resources/JournalEntries/
git commit -m "feat(erp): journal reverse Filament action"
```

---

## Task 8: Sales order amend action (2A-08)

**Files:**
- Create: `Modules/ERP/app/Filament/Resources/SalesOrders/Actions/SalesOrderAmendmentActions.php`
- Modify: `Modules/ERP/app/Filament/Resources/SalesOrders/Pages/EditSalesOrder.php`

- [ ] **Step 1: Create `SalesOrderAmendmentActions`**

```php
<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SalesOrders\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Filament\Resources\SalesOrders\SalesOrderResource;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Services\SalesOrders\SalesOrderAmendmentService;

final class SalesOrderAmendmentActions
{
    public static function amend(): Action
    {
        return Action::make('amend')
            ->label('Create amendment')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('Creates a new draft sales order with remaining quantities from this order.')
            ->authorize(static fn (SalesOrder $record): bool => auth()->user()?->can('amend', $record) ?? false)
            ->visible(static fn (SalesOrder $record): bool => in_array($record->status, [
                \Modules\ERP\Casts\SalesOrderStatus::Confirmed,
                \Modules\ERP\Casts\SalesOrderStatus::PartiallyEvased,
            ], true))
            ->action(static function (SalesOrder $record, Action $action): void {
                $amendment = resolve(SalesOrderAmendmentService::class)->amend($record);

                Notification::make()
                    ->title('Amendment created')
                    ->body('Reference: ' . ($amendment->reference ?? '—'))
                    ->success()
                    ->send();

                $action->redirect(SalesOrderResource::getUrl('edit', ['record' => $amendment]));
            });
    }
}
```

Add `use Filament\Actions\Action;` import.

- [ ] **Step 2: Wire `EditSalesOrder::getHeaderActions()`** — prepend `SalesOrderAmendmentActions::amend()` before any delete actions.

- [ ] **Step 3: Format and commit**

```bash
vendor/bin/pint --dirty
cd Modules/ERP && git add app/Filament/Resources/SalesOrders/
git commit -m "feat(erp): sales order amend Filament action"
```

---

## Task 9: Wiring smoke tests + full verification (2A-09)

**Files:**
- Create: `Modules/ERP/tests/Feature/Filament/ErpDomainActionsSmokeTest.php`
- Modify: `docs/superpowers/specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md` — move `2A-01`…`2A-09` to § Completed with commit SHAs

- [ ] **Step 1: Create smoke test**

```php
<?php

declare(strict_types=1);

use Modules\ERP\Filament\Resources\DeliveryNotes\Actions\DeliveryNotePostingActions;
use Modules\ERP\Filament\Resources\FiscalPeriods\Actions\FiscalPeriodActions;
use Modules\ERP\Filament\Resources\FiscalYears\Actions\FiscalYearActions;
use Modules\ERP\Filament\Resources\JournalEntries\Actions\JournalEntryActions;
use Modules\ERP\Filament\Resources\SalesOrders\Actions\SalesOrderAmendmentActions;

it('exposes Phase 2A Filament domain action factories', function (): void {
    expect(class_exists(FiscalPeriodActions::class))->toBeTrue()
        ->and(class_exists(FiscalYearActions::class))->toBeTrue()
        ->and(class_exists(DeliveryNotePostingActions::class))->toBeTrue()
        ->and(class_exists(JournalEntryActions::class))->toBeTrue()
        ->and(class_exists(SalesOrderAmendmentActions::class))->toBeTrue()
        ->and(FiscalPeriodActions::close()->getName())->toBe('close_period')
        ->and(JournalEntryActions::reverse()->getName())->toBe('reverse');
});
```

- [ ] **Step 2: Run full Phase 2A test subset**

```bash
php artisan test --compact \
  Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php \
  Modules/ERP/tests/Feature/ErpModelPolicyTest.php \
  Modules/ERP/tests/Feature/ErpModelPolicyStateTest.php \
  Modules/ERP/tests/Feature/Filament/ErpDomainActionsSmokeTest.php \
  Modules/ERP/tests/Integration/Services/Accounting/FiscalPeriodCloserTest.php
```

Expected: all PASS.

- [ ] **Step 3: Run full ERP feature suite**

```bash
php artisan test --compact Modules/ERP/tests/Feature
```

Expected: no regressions (baseline `299 passed, 1 skipped`).

- [ ] **Step 4: Format, commit, update spec backlog**

```bash
vendor/bin/pint --dirty
cd Modules/ERP && git add tests/Feature/Filament/ErpDomainActionsSmokeTest.php
git commit -m "test(erp): Phase 2A domain action wiring smoke"
```

In parent repo, update Spec 2 § Open → § Completed for `2A-01`…`2A-09` and PART-01…04.

- [ ] **Step 5: Version bump (ask user)**

Per `08-versioning.mdc`: propose `composer version:patch` inside `Modules/ERP` after user confirmation.

---

## Self-review checklist

| Spec requirement | Task |
|------------------|------|
| 2A-01 seed permissions | Task 1 |
| 2A-02 state-aware policy | Task 2 |
| 2A-03 force_post gate | Task 3 |
| 2A-04 DDT post/unpost | Task 6 |
| 2A-05 fiscal period actions + form | Task 4 |
| 2A-06 fiscal year close + policy | Task 5 |
| 2A-07 journal reverse | Task 7 |
| 2A-08 sales order amend | Task 8 |
| 2A-09 tests | Tasks 1–2, 9 |
| PART-01…04 closed | All tasks |
| PART-05 / 2B-11 untouched | — |
| quotation unlock / sequence reset deferred 2B | — |

---

## Execution handoff

Plan saved to `docs/superpowers/plans/2026-06-30-erp-hardening-spec2-phase2a.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline Execution** — implement tasks in this session with checkpoints

Which approach?
