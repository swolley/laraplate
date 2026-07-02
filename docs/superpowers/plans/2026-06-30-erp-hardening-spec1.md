# ERP Hardening (Spec 1) Implementation Plan

> **Navigation:** **Completed.** Open follow-ups →
> [`specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md`](../specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md).

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Completed (2026-06-30). Post-implementation code review completed same day.

**Goal:** Make the existing ERP accounting/operational flows correct and decimal-exact, with regression tests proving each fix, plus a minimal Core guard that blocks generic-CRUD writes on immutable/derived models.

**Architecture:** Six independent fixes on `Modules/ERP` (plus a small Core contract for Fix 6). Each fix is test-first (Pest + `RefreshDatabase`, manual model setup, no factories). A shared `Modules\ERP\Support\Decimal` (Brick Math, scale 4, HALF_UP) replaces the duplicated float helpers, and per-line tax flows through a single `TaxLineCalculator::lineTax()` method.

**Tech Stack:** PHP 8.5, Laravel 12, Pest, Spatie Permissions, `brick/math` (already in the lock file), nwidart modules.

**Source spec:** `docs/superpowers/specs/2026-06-30-erp-hardening-bugs-money-math-design.md`

**Conventions (verified):**
- Run a single ERP test file: `php artisan test --compact Modules/ERP/tests/Feature/<File>.php`
- ERP Pest only auto-loads `tests/Integration` and `tests/Feature` (NOT `tests/Unit`) — put all new tests under `Feature`.
- Invoice posting is triggered by setting `posted_at` (`$invoice->update(['posted_at' => now()])`), which runs `InvoicePostingService::post()` via an observer.
- Superadmin is granted via the role `config('permission.roles.superadmin')` and bypasses Gate-based policies through `Gate::before` in `CoreServiceProvider`.
- Format at the end of every task: `vendor/bin/pint --dirty`.

---

## Implementation Status (completed 2026-06-30)

| Task | Fix | Submodule | Commit | Result |
|------|-----|-----------|--------|--------|
| 1 E-invoice seeder | Fix 1 | ERP | `9297a7c` | 2 tests pass |
| 2 Policy tests | Fix 2 | ERP | `084113b` | 4 tests pass; `policyPermission()` helper for connection-aware strings |
| 3 Closed fiscal period | Fix 3 | ERP | `72dcdb3` | 13 tests in `InvoicePostingServiceTest`; regression 16 pass |
| 4 Decimal helper | Fix 4a | ERP | `cac0fa3` | 4 tests pass |
| 5 lineTax | Fix 4b | ERP | `d882aaf` | 2 tests pass |
| 6 Money math refactor | Fix 4c | ERP | `51cba65` | 45 regression tests pass; golden values unchanged |
| 7 Test gaps | Fix 5 | ERP | `e332542` | 7 tests (3WM + bank CSV) |
| 8 CRUD write guard | Fix 6 | Core + ERP | `354299c` + `f6ed9fd` | 3 guard tests; full ERP suite 240 pass |

**Base SHAs:** ERP `db841e1`, Core `cd4d5dc`.

**Not done (by design):** module version bumps — awaiting user confirmation.

---

## Task 1: Fix e-invoice domain permissions seeder (Fix 1, P0)

`ERPDatabaseSeeder::domainPermissions()` never seeds the e-invoice abilities because `is_a($model, Invoice::class)` is called on a class-string without the `allow_string` flag (always false). It also has a `$sntities` typo and an out-of-place `flushEventListeners()` side effect.

**Files:**
- Modify: `Modules/ERP/app/Database/Seeders/ERPDatabaseSeeder.php` (the file lives at `Modules/ERP/database/seeders/ERPDatabaseSeeder.php`), method `domainPermissions()` at lines 224-246
- Test: `Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php` (new)

- [ ] **Step 1: Write the failing test**

Create `Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;

uses(RefreshDatabase::class);

it('seeds e-invoice domain permissions for the invoice model only', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    expect(Permission::query()->where('name', 'default.erp_invoices.post')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_invoices.unpost')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_invoices.submitEInvoice')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_invoices.refreshEInvoice')->exists())->toBeTrue();
});

it('does not seed e-invoice permissions for non-invoice models', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    expect(Permission::query()->where('name', 'default.erp_journal_entries.post')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_journal_entries.submitEInvoice')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php`
Expected: FAIL — `default.erp_invoices.submitEInvoice` does not exist.

- [ ] **Step 3: Fix the seeder**

In `Modules/ERP/database/seeders/ERPDatabaseSeeder.php`, replace the `domainPermissions()` body (lines 224-246) with:

```php
    private function domainPermissions(): array
    {
        $entities = [DeliveryNote::class, FiscalPeriod::class, Invoice::class, JournalEntry::class, SalesOrder::class];
        $permissions = [];

        foreach ($entities as $model) {
            $instance = new ReflectionClass($model)->newInstanceWithoutConstructor();

            $connection = $instance->getConnectionName() ?? 'default';
            $table = $instance->getTable();

            $permissions[] = "{$connection}." . $table . '.post';
            $permissions[] = "{$connection}." . $table . '.unpost';

            if ($model === Invoice::class) {
                $permissions[] = "{$connection}." . $table . '.submitEInvoice';
                $permissions[] = "{$connection}." . $table . '.refreshEInvoice';
            }
        }

        return $permissions;
    }
```

This renames `$sntities` → `$entities`, replaces the broken `is_a(...)` check with a direct class-string comparison `$model === Invoice::class`, and removes the unrelated `$model::flushEventListeners();` side effect from the permission-name builder.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php`
Expected: PASS (both tests).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty
git add Modules/ERP/database/seeders/ERPDatabaseSeeder.php Modules/ERP/tests/Feature/ErpDomainPermissionsSeederTest.php
git commit -m "fix(erp): seed e-invoice domain permissions for invoices"
```

---

## Task 2: Authorization policy tests (Fix 2, P0)

`ERPModelPolicy` is fail-closed and correct, but there is no authorization test anywhere in `Modules/ERP/tests/`. After Task 1 the e-invoice abilities become usable; pin the semantics with tests. No production code changes.

**Files:**
- Test: `Modules/ERP/tests/Feature/ErpModelPolicyTest.php` (new)

- [ ] **Step 1: Write the test**

Create `Modules/ERP/tests/Feature/ErpModelPolicyTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Policies\ERPModelPolicy;

uses(RefreshDatabase::class);

function policyInvoice(): Invoice
{
    $company = Company::query()->create([
        'slug' => 'policy-co',
        'name' => 'Policy Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    return Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => \Modules\ERP\Casts\InvoiceDirection::Sale,
        'invoice_type' => \Modules\ERP\Casts\InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
}

it('allows superadmin to run every ERP ability', function (): void {
    $invoice = policyInvoice();
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    $policy = app(ERPModelPolicy::class);

    expect($policy->post($user, $invoice))->toBeTrue()
        ->and($policy->unpost($user, $invoice))->toBeTrue()
        ->and($policy->submitEInvoice($user, $invoice))->toBeTrue()
        ->and($policy->refreshEInvoice($user, $invoice))->toBeTrue();
});

it('allows a user granted the specific permission', function (): void {
    $invoice = policyInvoice();
    $permission = 'default.erp_invoices.submitEInvoice';
    Permission::findOrCreate($permission, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo($permission);

    expect(app(ERPModelPolicy::class)->submitEInvoice($user, $invoice))->toBeTrue();
});

it('denies a user who lacks the permission even when the permission row exists', function (): void {
    $invoice = policyInvoice();
    Permission::findOrCreate('default.erp_invoices.submitEInvoice', 'web');

    $user = User::factory()->create();

    expect(app(ERPModelPolicy::class)->submitEInvoice($user, $invoice))->toBeFalse();
});

it('denies (fail-closed) when the permission row is absent', function (): void {
    $invoice = policyInvoice();
    $user = User::factory()->create();

    expect(app(ERPModelPolicy::class)->refreshEInvoice($user, $invoice))->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it passes**

Run: `php artisan test --compact Modules/ERP/tests/Feature/ErpModelPolicyTest.php`
Expected: PASS (4 tests). These document the intended, already-correct fail-closed contract.

- [ ] **Step 3: Format and commit**

```bash
vendor/bin/pint --dirty
git add Modules/ERP/tests/Feature/ErpModelPolicyTest.php
git commit -m "test(erp): pin ERPModelPolicy authorization semantics"
```

---

## Task 3: Enforce closed fiscal periods on invoice posting (Fix 3, P0)

`InvoicePostingService::post()` passes `null` as the fiscal period to `JournalPostingService::post()` (lines 96-104), so the closed-period guard never runs for invoices. Resolve the covering period and pass it. **Non-breaking:** if no period covers the date, pass `null` (current behavior); the guard only fires for a covering period that is closed. (Existing posting fixtures create a `FiscalYear` but no `FiscalPeriod` rows, so they keep posting unguarded.)

**Files:**
- Modify: `Modules/ERP/app/Services/Accounting/InvoicePostingService.php` (imports; the `post()` call at lines 95-104; add a `resolveFiscalPeriod()` helper)
- Test: `Modules/ERP/tests/Feature/InvoicePostingServiceTest.php` (append cases; reuse `createInvoicePostingCompany()`)

- [ ] **Step 1: Write the failing tests**

Append to `Modules/ERP/tests/Feature/InvoicePostingServiceTest.php`. First add the model imports near the top of the file (after the existing `use Modules\ERP\Models\FiscalYear;` line):

```php
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Exceptions\PostingToClosedFiscalPeriodException;
```

Then append these cases at the end of the file:

```php
it('blocks posting a sale invoice into a closed fiscal period', function (): void {
    $company = createInvoicePostingCompany('inv-closed-period');
    $fiscal_year = FiscalYear::query()->where('company_id', $company->id)->firstOrFail();

    FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => (int) now()->format('n'),
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
        'is_closed' => true,
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Part',
        'quantity' => 1,
        'unit_price' => 10,
    ]);

    expect(fn () => $invoice->update(['posted_at' => now()]))
        ->toThrow(PostingToClosedFiscalPeriodException::class);

    expect($invoice->fresh()->journal_entry_id)->toBeNull();
});

it('posts a sale invoice into an open fiscal period', function (): void {
    $company = createInvoicePostingCompany('inv-open-period');
    $fiscal_year = FiscalYear::query()->where('company_id', $company->id)->firstOrFail();

    FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => (int) now()->format('n'),
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
        'is_closed' => false,
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Part',
        'quantity' => 1,
        'unit_price' => 10,
    ]);

    $invoice->update(['posted_at' => now()]);

    expect($invoice->fresh()->journal_entry_id)->not->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact Modules/ERP/tests/Feature/InvoicePostingServiceTest.php`
Expected: the "blocks posting … closed fiscal period" test FAILS (posting currently succeeds and `journal_entry_id` is set). The "open period" test passes already.

- [ ] **Step 3: Resolve and pass the fiscal period**

In `Modules/ERP/app/Services/Accounting/InvoicePostingService.php`, add the import (with the other `use Modules\ERP\Models\...` lines):

```php
use Modules\ERP\Models\FiscalPeriod;
```

Replace the journal post call (lines 95-104) so the resolved period is passed instead of `null`:

```php
            $journal_lines = $this->buildJournalLines($company, $locked->direction, $locked->currency, $net_total, $tax_total, $gross_total);
            $fiscal_period = $this->resolveFiscalPeriod($company, $posted_at);
            $entry = $this->journal_posting_service->post(
                $company,
                $journal_lines,
                $fiscal_period,
                'Invoice posted ' . $reference,
                null,
                $locked,
                $posted_at,
            );
```

Add this private method next to `postedAtForPosting()` (after line 349):

```php
    private function resolveFiscalPeriod(Company $company, CarbonImmutable $posted_at): ?FiscalPeriod
    {
        return FiscalPeriod::query()
            ->withoutGlobalScopes()
            ->whereHas('fiscal_year', static function (\Illuminate\Database\Eloquent\Builder $query) use ($company, $posted_at): void {
                $query->withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('year', $posted_at->year);
            })
            ->whereDate('start_date', '<=', $posted_at)
            ->whereDate('end_date', '>=', $posted_at)
            ->first();
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/ERP/tests/Feature/InvoicePostingServiceTest.php`
Expected: PASS (all cases, old and new).

- [ ] **Step 5: Regression — full posting/golden suites**

Run: `php artisan test --compact Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php Modules/ERP/tests/Feature/AccountingSequencesAndPostingTest.php`
Expected: PASS — no covering periods exist in those fixtures, so posting behavior is unchanged.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty
git add Modules/ERP/app/Services/Accounting/InvoicePostingService.php Modules/ERP/tests/Feature/InvoicePostingServiceTest.php
git commit -m "fix(erp): block invoice posting into closed fiscal periods"
```

---

## Task 4: Shared decimal helper (Fix 4a, P1)

Add a single decimal primitive so all money math is exact. It wraps `Brick\Math\BigDecimal` (scale 4, `RoundingMode::HALF_UP`), matching the existing `TaxLineCalculator` convention.

**Files:**
- Create: `Modules/ERP/app/Support/Decimal.php`
- Test: `Modules/ERP/tests/Feature/Support/DecimalTest.php` (new)

- [ ] **Step 1: Write the failing test**

Create `Modules/ERP/tests/Feature/Support/DecimalTest.php`:

```php
<?php

declare(strict_types=1);

use Modules\ERP\Support\Decimal;

it('adds and subtracts at scale 4', function (): void {
    expect(Decimal::add('0.1', '0.2'))->toBe('0.3000')
        ->and(Decimal::add('0.9999', '7.7777'))->toBe('8.7776')
        ->and(Decimal::sub('8.7776', '7.7777'))->toBe('0.9999');
});

it('multiplies and formats at scale 4', function (): void {
    expect(Decimal::mul('3', '0.3333'))->toBe('0.9999')
        ->and(Decimal::mul('7', '1.1111'))->toBe('7.7777')
        ->and(Decimal::format('5'))->toBe('5.0000');
});

it('divides with HALF_UP at the 4th decimal boundary', function (): void {
    // 0.125 / 100 = 0.00125 -> HALF_UP scale 4 -> 0.0013 (the float pipeline rounds this down).
    expect(Decimal::div('0.125', '100'))->toBe('0.0013')
        ->and(Decimal::div('1', '3'))->toBe('0.3333');
});

it('negates, takes absolute value, and reports sign/zero', function (): void {
    expect(Decimal::negate('1.2300'))->toBe('-1.2300')
        ->and(Decimal::abs('-1.2300'))->toBe('1.2300')
        ->and(Decimal::isZero('0.0000'))->toBeTrue()
        ->and(Decimal::isZero('0.0001'))->toBeFalse()
        ->and(Decimal::isNegative('-0.0001'))->toBeTrue()
        ->and(Decimal::isNegative('0.0000'))->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/ERP/tests/Feature/Support/DecimalTest.php`
Expected: FAIL — class `Modules\ERP\Support\Decimal` not found.

- [ ] **Step 3: Implement the helper**

Create `Modules/ERP/app/Support/Decimal.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\ERP\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Decimal-exact money math (scale 4, HALF_UP) shared across ERP accounting services.
 *
 * All inputs and outputs are decimal strings so values round-trip through the database
 * decimal columns without float drift.
 */
final class Decimal
{
    private const int SCALE = 4;

    public static function add(string $a, string $b): string
    {
        return self::format(BigDecimal::of($a)->plus(BigDecimal::of($b)));
    }

    public static function sub(string $a, string $b): string
    {
        return self::format(BigDecimal::of($a)->minus(BigDecimal::of($b)));
    }

    public static function mul(string $a, string $b): string
    {
        return self::format(BigDecimal::of($a)->multipliedBy(BigDecimal::of($b)));
    }

    public static function div(string $a, string $b): string
    {
        return self::format(BigDecimal::of($a)->dividedBy(BigDecimal::of($b), self::SCALE, RoundingMode::HALF_UP));
    }

    public static function negate(string $a): string
    {
        return self::format(BigDecimal::of($a)->negated());
    }

    public static function abs(string $a): string
    {
        return self::format(BigDecimal::of($a)->abs());
    }

    public static function isZero(string $a): bool
    {
        return self::scaled($a)->isZero();
    }

    public static function isNegative(string $a): bool
    {
        return self::scaled($a)->isNegative();
    }

    public static function format(BigDecimal|string $value): string
    {
        return self::scaled($value)->__toString();
    }

    private static function scaled(BigDecimal|string $value): BigDecimal
    {
        $decimal = $value instanceof BigDecimal ? $value : BigDecimal::of($value);

        return $decimal->toScale(self::SCALE, RoundingMode::HALF_UP);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/ERP/tests/Feature/Support/DecimalTest.php`
Expected: PASS.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty
git add Modules/ERP/app/Support/Decimal.php Modules/ERP/tests/Feature/Support/DecimalTest.php
git commit -m "feat(erp): add decimal-exact money math helper"
```

---

## Task 5: Single-source line tax in TaxLineCalculator (Fix 4b, P1)

Add one decimal `lineTax(net, rate)` method so invoice posting and the VAT register compute per-line tax identically, instead of duplicating float tax math. It uses a single rounding step, matching the existing `computeVatFromNet()` semantics.

**Files:**
- Modify: `Modules/ERP/app/Services/Taxation/TaxLineCalculator.php`
- Test: `Modules/ERP/tests/Feature/Services/TaxLineCalculatorTest.php` (new)

- [ ] **Step 1: Write the failing test**

Create `Modules/ERP/tests/Feature/Services/TaxLineCalculatorTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Services\Taxation\TaxLineCalculator;

uses(RefreshDatabase::class);

it('computes line tax decimal-exact with HALF_UP rounding', function (): void {
    $calculator = app(TaxLineCalculator::class);

    expect($calculator->lineTax('0.0500', '2.5'))->toBe('0.0013')
        ->and($calculator->lineTax('100', '22'))->toBe('22.0000')
        ->and($calculator->lineTax('8.7776', '22'))->toBe('1.9311');
});

it('matches computeVatFromNet tax for VAT codes', function (): void {
    $company = Company::query()->create([
        'slug' => 'tax-calc',
        'name' => 'Tax Calc',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $vat = TaxCode::query()->create([
        'company_id' => $company->id,
        'code' => 'VAT22',
        'kind' => 'vat',
        'country' => 'IT',
        'rate' => 22,
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => now()->toDateString(),
    ]);

    $calculator = app(TaxLineCalculator::class);

    expect($calculator->lineTax('8.7776', (string) $vat->rate))
        ->toBe($calculator->computeVatFromNet($vat, '8.7776')['tax']);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/ERP/tests/Feature/Services/TaxLineCalculatorTest.php`
Expected: FAIL — method `lineTax()` does not exist.

- [ ] **Step 3: Add the method**

In `Modules/ERP/app/Services/Taxation/TaxLineCalculator.php`, add this public method (e.g. after `computeWithholdingFromGross()`):

```php
    /**
     * Decimal-exact per-line tax = net * rate / 100, rounded once at scale 4 (HALF_UP).
     *
     * Single source of truth for invoice posting and VAT register line tax; takes a net
     * amount and a percentage rate so it needs neither a TaxKind check nor a TaxCode lookup.
     */
    public function lineTax(string $net_amount, string $rate): string
    {
        return BigDecimal::of($net_amount)
            ->multipliedBy(BigDecimal::of($rate))
            ->dividedBy('100', 4, RoundingMode::HALF_UP)
            ->__toString();
    }
```

`BigDecimal` and `RoundingMode` are already imported in this file.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact Modules/ERP/tests/Feature/Services/TaxLineCalculatorTest.php`
Expected: PASS.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty
git add Modules/ERP/app/Services/Taxation/TaxLineCalculator.php Modules/ERP/tests/Feature/Services/TaxLineCalculatorTest.php
git commit -m "feat(erp): add single-source decimal line tax to TaxLineCalculator"
```

---

## Task 6: Refactor money math to Decimal across services (Fix 4c, P1)

Replace the float helpers (`round4`/`add`/`mul`/`neg`/`asFloat`) and the duplicated float tax math in `InvoicePostingService`, `VatRegisterService`, `VatSettlementService`, and `PriceResolverService::applyRule()` with `Decimal` and `TaxLineCalculator::lineTax()`. Existing golden masters are the regression baseline.

**Files:**
- Modify: `Modules/ERP/app/Services/Accounting/InvoicePostingService.php`
- Modify: `Modules/ERP/app/Services/Accounting/VatRegisterService.php`
- Modify: `Modules/ERP/app/Services/Accounting/VatSettlementService.php`
- Modify: `Modules/ERP/app/Services/Pricing/PriceResolverService.php`
- Test: `Modules/ERP/tests/Feature/MoneyMathDecimalPostingTest.php` (new)

- [ ] **Step 1: Write the fractional-posting test**

Create `Modules/ERP/tests/Feature/MoneyMathDecimalPostingTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\TaxCode;

uses(RefreshDatabase::class);

it('posts a fractional sale invoice with decimal-exact, balanced journal totals', function (): void {
    $company = Company::query()->create([
        'slug' => 'money-frac',
        'name' => 'Money Frac',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => (int) now()->format('Y'),
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
    ]);
    $vat = TaxCode::query()->create([
        'company_id' => $company->id,
        'code' => 'VAT22',
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
    ]);
    // net 0.9999 -> tax 0.2200
    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Frac A',
        'quantity' => 3,
        'unit_price' => '0.3333',
        'tax_code_id' => $vat->id,
    ]);
    // net 7.7777 -> tax 1.7111
    $invoice->lines()->create([
        'line_no' => 2,
        'description' => 'Frac B',
        'quantity' => 7,
        'unit_price' => '1.1111',
        'tax_code_id' => $vat->id,
    ]);

    $invoice->update(['posted_at' => now()]);

    $journal = JournalEntry::query()->withoutGlobalScopes()
        ->findOrFail((int) $invoice->fresh()->journal_entry_id);
    $amounts = $journal->lines->pluck('amount_doc')->map(fn ($v): string => (string) $v)->all();

    // receivable +gross 10.7087, revenue -net 8.7776, vat_output -tax 1.9311
    expect($amounts)->toContain('10.7087')
        ->and($amounts)->toContain('-8.7776')
        ->and($amounts)->toContain('-1.9311');

    // Double-entry balance is exactly zero.
    $sum = array_reduce(
        $amounts,
        fn (\Brick\Math\BigDecimal $carry, string $v): \Brick\Math\BigDecimal => $carry->plus(\Brick\Math\BigDecimal::of($v)),
        \Brick\Math\BigDecimal::zero(),
    );
    expect($sum->toScale(4)->__toString())->toBe('0.0000');
});
```

- [ ] **Step 2: Run it against the current float code**

Run: `php artisan test --compact Modules/ERP/tests/Feature/MoneyMathDecimalPostingTest.php`
Expected: record the result. It MAY fail now if the float pipeline drifts on these values; if it already passes, keep it as a regression guard. Either way it must be green after Step 7.

- [ ] **Step 3: Refactor `InvoicePostingService`**

In `Modules/ERP/app/Services/Accounting/InvoicePostingService.php`:

Add the import (with the other `use Modules\ERP\...` lines):

```php
use Modules\ERP\Services\Taxation\TaxLineCalculator;
use Modules\ERP\Support\Decimal;
```

Inject `TaxLineCalculator` into the constructor (add as the last promoted property):

```php
    public function __construct(
        private ChartOfAccountsInstaller $chart_of_accounts_installer,
        private CreditNoteService $credit_note_service,
        private DocumentNumberAllocator $document_number_allocator,
        private InvoiceDeliveryNoteValidationService $invoice_delivery_note_validation_service,
        private JournalPostingService $journal_posting_service,
        private PaymentScheduleGeneratorService $payment_schedule_generator_service,
        private SalesOrderEvasionService $sales_order_evasion_service,
        private ErpCompanySettings $erp_company_settings,
        private ThreeWayMatchService $three_way_match_service,
        private VatRegisterService $vat_register_service,
        private TaxLineCalculator $tax_line_calculator,
    ) {}
```

Replace the negation block (lines 90-92) with `Decimal::negate`:

```php
                $net_total = Decimal::negate($net_total);
                $tax_total = Decimal::negate($tax_total);
                $gross_total = Decimal::negate($gross_total);
```

Replace the per-line tax computation inside `resolveAndSnapshotTaxes()` (lines 157-175) with:

```php
        foreach ($lines as $line) {
            $line_net = Decimal::mul((string) $line->quantity, (string) $line->unit_price);
            $line_tax = '0.0000';

            if ($line->tax_code_id !== null) {
                $tax_code = TaxCode::query()->withoutGlobalScopes()->findOrFail($line->tax_code_id);
                $line_tax = $this->tax_line_calculator->lineTax($line_net, (string) $tax_code->rate);

                $line->tax_code = $tax_code->code;
                $line->tax_rate = $tax_code->rate;
                $line->tax_label = $tax_code->label;
                $line->save();
            }

            $net_total = Decimal::add($net_total, $line_net);
            $tax_total = Decimal::add($tax_total, $line_tax);
        }

        $gross_total = Decimal::add($net_total, $tax_total);
```

Replace the two `abs($this->asFloat($tax_total)) > 0` tax-presence checks in `buildJournalLines()` (lines 211 and 226) with:

```php
        if (! Decimal::isZero($tax_total)) {
```

Replace the `$this->neg(...)` calls inside `buildJournalLines()` (lines 208, 212, 230) with `Decimal::negate(...)`:

```php
                $this->line($this->modelId($revenue), Decimal::negate($net_total), $currency, $fx_rate, 'Sales revenue'),
```
```php
                $lines[] = $this->line($this->modelId($vat_output), Decimal::negate($tax_total), $currency, $fx_rate, 'VAT output');
```
```php
        $lines[] = $this->line($this->modelId($payable), Decimal::negate($gross_total), $currency, $fx_rate, 'Trade payable');
```

Delete the now-unused float helpers `round4()`, `add()`, `mul()`, `neg()`, and `asFloat()` (lines 375-378 and 385-403). Keep `modelId()`.

- [ ] **Step 4: Refactor `VatRegisterService`**

In `Modules/ERP/app/Services/Accounting/VatRegisterService.php`:

Add imports and a constructor that injects `TaxLineCalculator`:

```php
use Modules\ERP\Services\Taxation\TaxLineCalculator;
use Modules\ERP\Support\Decimal;
```
```php
final class VatRegisterService
{
    public function __construct(private TaxLineCalculator $tax_line_calculator) {}
```

Replace the per-line tax loop (lines 59-70) with:

```php
                foreach ($group as $line) {
                    $line_net = Decimal::mul((string) $line->quantity, (string) $line->unit_price);
                    $line_tax = $this->tax_line_calculator->lineTax($line_net, (string) $line->tax_rate);

                    $taxable_amount = Decimal::add($taxable_amount, $line_net);
                    $tax_amount = Decimal::add($tax_amount, $line_tax);
                }

                if ($is_credit_note) {
                    $taxable_amount = Decimal::negate($taxable_amount);
                    $tax_amount = Decimal::negate($tax_amount);
                }
```

Delete the now-unused float helpers `round4()`, `add()`, and `neg()` (lines 108-121).

- [ ] **Step 5: Refactor `VatSettlementService`**

In `Modules/ERP/app/Services/Accounting/VatSettlementService.php`:

Add the import:

```php
use Modules\ERP\Support\Decimal;
```

Replace the previous-credit branch (lines 60-62):

```php
                if ($previous_settlement !== null && Decimal::isNegative((string) $previous_settlement->settlement_amount)) {
                    $previous_credit = Decimal::abs((string) $previous_settlement->settlement_amount);
                }
```

Replace the settlement computation (lines 65-67):

```php
            $settlement_amount = Decimal::sub(Decimal::sub($vat_sales, $vat_purchases), $previous_credit);
```

Replace the persisted rounded aggregates (lines 84-85):

```php
                    'vat_sales' => Decimal::format($vat_sales),
                    'vat_purchases' => Decimal::format($vat_purchases),
```

Delete the now-unused `round4()` helper (lines 109-111).

- [ ] **Step 6: Refactor `PriceResolverService::applyRule()`**

In `Modules/ERP/app/Services/Pricing/PriceResolverService.php`, add the import:

```php
use Modules\ERP\Support\Decimal;
```

Replace `applyRule()` (lines 114-130) with:

```php
    private function applyRule(string $base_price, ?PartyPriceRule $rule): string
    {
        if ($rule === null) {
            return Decimal::format($base_price);
        }

        $value = (string) $rule->discount_value;

        $resolved = match ($rule->discount_type) {
            DiscountType::Percent => Decimal::mul($base_price, Decimal::sub('1', Decimal::div($value, '100'))),
            DiscountType::FixedAmount => Decimal::sub($base_price, $value),
            DiscountType::OverridePrice => Decimal::format($value),
        };

        return Decimal::isNegative($resolved) ? '0.0000' : $resolved;
    }
```

- [ ] **Step 7: Run the new test and the full regression suites**

Run: `php artisan test --compact Modules/ERP/tests/Feature/MoneyMathDecimalPostingTest.php Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php Modules/ERP/tests/Feature/InventoryAccountingGoldenMasterTest.php Modules/ERP/tests/Feature/InvoicePostingServiceTest.php Modules/ERP/tests/Feature/VatRegisterServiceTest.php Modules/ERP/tests/Feature/Services/PriceResolverServiceTest.php Modules/ERP/tests/Feature/Services/InvoiceLinePricingServiceTest.php Modules/ERP/tests/Feature/FinancialStatementsTest.php`
Expected: PASS. If a golden value changed, treat it as a corrected float defect (do NOT relax assertions); confirm the new value is the decimal-exact one before updating any golden expectation.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty
git add Modules/ERP/app/Services/Accounting/InvoicePostingService.php Modules/ERP/app/Services/Accounting/VatRegisterService.php Modules/ERP/app/Services/Accounting/VatSettlementService.php Modules/ERP/app/Services/Pricing/PriceResolverService.php Modules/ERP/tests/Feature/MoneyMathDecimalPostingTest.php
git commit -m "refactor(erp): decimal-exact money math across accounting services"
```

---

## Task 7: Targeted test gaps — three-way match + bank CSV (Fix 5, P1)

Two gaps: `ThreeWayMatchService` is only tested on `PurchaseOrderLine` (no GR-line and no unmatched cases), and `BankStatementCsvImporter` validates only duplicate headers, not per-row content.

**Files:**
- Test: `Modules/ERP/tests/Feature/ThreeWayMatchServiceTest.php` (append cases)
- Modify: `Modules/ERP/app/Services/Banking/BankStatementCsvImporter.php`
- Test: `Modules/ERP/tests/Feature/Services/BankStatementImportServiceTest.php` (append cases)

### 7a — Three-way match GR and unmatched coverage

- [ ] **Step 1: Write the tests**

Append the imports to the top of `Modules/ERP/tests/Feature/ThreeWayMatchServiceTest.php` (after the existing `use` block):

```php
use Modules\ERP\Models\GoodsReceipt;
use Modules\ERP\Models\GoodsReceiptLine;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Warehouse;
```

Append these cases at the end of the file:

```php
it('flags a goods-receipt quantity discrepancy beyond tolerance', function (): void {
    $company = Company::query()->create([
        'slug' => '3wm-gr',
        'name' => '3WM GR',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $warehouse = Warehouse::query()->create(['company_id' => $company->id, 'name' => 'WH', 'code' => 'GR-WH']);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Bolt',
        'sku' => 'B-3WM',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);
    $receipt = GoodsReceipt::query()->create([
        'company_id' => $company->id,
        'reference' => 'GR-3WM',
    ]);
    $gr_line = GoodsReceiptLine::query()->create([
        'company_id' => $company->id,
        'goods_receipt_id' => $receipt->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_cost' => '3.5000',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Bolt',
        'quantity' => 12,
        'unit_price' => 3.5,
        'goods_receipt_line_id' => $gr_line->id,
    ]);

    $service = app(ThreeWayMatchService::class);

    expect(fn () => $service->validate($invoice_line, qty_tolerance_percent: 0.0))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $result = $service->validate($invoice_line, qty_tolerance_percent: 0.0, force: true);
    expect($result['status'])->toBe(MatchStatus::Forced)
        ->and($result['discrepancies'])->toHaveKey('gr_qty');
});

it('returns unmatched when the invoice line has no PO or GR link', function (): void {
    $company = Company::query()->create([
        'slug' => '3wm-unmatched',
        'name' => '3WM Unmatched',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Loose item',
        'quantity' => 1,
        'unit_price' => 10,
    ]);

    $result = app(ThreeWayMatchService::class)->validate($invoice_line);

    expect($result['status'])->toBe(MatchStatus::Unmatched)
        ->and($result['discrepancies'])->toHaveKey('reason');
});
```

- [ ] **Step 2: Run the tests to verify they pass**

Run: `php artisan test --compact Modules/ERP/tests/Feature/ThreeWayMatchServiceTest.php`
Expected: PASS (existing 3 + 2 new). These document already-correct behavior (`gr_qty` discrepancy path and the `Unmatched` early return).

### 7b — Bank CSV per-row validation

- [ ] **Step 3: Write the failing test**

Append the import to `Modules/ERP/tests/Feature/Services/BankStatementImportServiceTest.php` if not present:

```php
use Illuminate\Validation\ValidationException;
```

Append this case at the end of the file:

```php
it('rejects a CSV row missing the required date or amount', function (): void {
    $company = \Modules\ERP\Models\Company::query()->create([
        'slug' => 'csv-bad-row',
        'name' => 'CSV Bad Row',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $account = \Modules\ERP\Models\BankAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'Main bank',
        'currency' => 'EUR',
    ]);
    $statement = \Modules\ERP\Models\BankStatement::query()->create([
        'company_id' => $company->id,
        'bank_account_id' => $account->id,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents(
        $path,
        "booked_at,description,amount_doc\n2026-05-01,Valid row,100.00\n,Missing date,50.00\n",
    );

    try {
        expect(fn () => app(\Modules\ERP\Services\Banking\BankStatementCsvImporter::class)->import($statement, $path))
            ->toThrow(ValidationException::class);

        // The whole import is transactional: nothing is persisted on a bad row.
        expect($statement->lines()->count())->toBe(0);
    } finally {
        @unlink($path);
    }
});
```

- [ ] **Step 4: Run the test to verify it fails**

Run: `php artisan test --compact Modules/ERP/tests/Feature/Services/BankStatementImportServiceTest.php`
Expected: FAIL — the importer currently parses the empty `booked_at` (`CarbonImmutable::parse('')`) and/or persists rows instead of throwing a `ValidationException`.

- [ ] **Step 5: Add per-row validation to the importer**

In `Modules/ERP/app/Services/Banking/BankStatementCsvImporter.php`, inside the `DB::transaction` loop in `import()`, validate each row before creating the line. Replace the `foreach ($rows as $row) {` block (lines 38-56) with:

```php
            foreach ($rows as $index => $row) {
                $this->assertRowIsValid($row, $columns, $index);

                $statement->lines()->create([
                    'company_id' => $statement->company_id,
                    'booked_at' => CarbonImmutable::parse((string) $row[$columns['booked_at']])->toDateString(),
                    'value_at' => isset($row[$columns['value_at']]) && $row[$columns['value_at']] !== ''
                        ? CarbonImmutable::parse((string) $row[$columns['value_at']])->toDateString()
                        : null,
                    'reference' => $row[$columns['reference']] ?? null,
                    'description' => $row[$columns['description']] ?? null,
                    'amount_doc' => $this->normalizeDecimal((string) $row[$columns['amount_doc']]),
                    'currency_doc' => $row[$columns['currency_doc']] ?? $default_currency,
                    'amount_local' => $this->normalizeDecimal((string) $row[$columns['amount_doc']]),
                    'currency_local' => $default_currency,
                    'fx_rate' => '1.00000000',
                    'status' => BankStatementLineStatus::Imported,
                    'raw_payload' => $row,
                ]);
                $created++;
            }
```

Add this private method (after `import()`, before `readRows()`):

```php
    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, string>  $columns
     */
    private function assertRowIsValid(array $row, array $columns, int $index): void
    {
        $booked_at = trim((string) ($row[$columns['booked_at']] ?? ''));
        $amount = trim((string) ($row[$columns['amount_doc']] ?? ''));
        $description = trim((string) ($row[$columns['description']] ?? ''));

        $errors = [];

        if ($booked_at === '') {
            $errors[] = 'a booked date';
        }

        if ($amount === '' || ! is_numeric($this->normalizeDecimal($amount))) {
            $errors[] = 'a numeric amount';
        }

        if ($description === '') {
            $errors[] = 'a description';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => ['Row ' . ($index + 1) . ' is missing ' . implode(', ', $errors) . '.'],
            ]);
        }
    }
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --compact Modules/ERP/tests/Feature/Services/BankStatementImportServiceTest.php`
Expected: PASS (existing import test + the new bad-row test).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty
git add Modules/ERP/tests/Feature/ThreeWayMatchServiceTest.php Modules/ERP/app/Services/Banking/BankStatementCsvImporter.php Modules/ERP/tests/Feature/Services/BankStatementImportServiceTest.php
git commit -m "test(erp): cover GR/unmatched three-way match and bank CSV row validation"
```

---

## Task 8: Block generic-CRUD writes on immutable/derived models (Fix 6, P0 integrity)

The Core dynamic CRUD has no structural model-exposure control: `CrudService::insert/update/delete` operates on any resolved model, and `JournalEntry` only blocks update/delete *after* posting — generic CRUD can still create an unbalanced voucher, bypassing every `JournalPostingService` invariant. Add a minimal Core write-guard contract and apply it to the immutable/derived ERP models. The guard runs before the permission check, so it blocks everyone (superadmin included). It guards only writes, so CMS's in-process `list()` delegation is unaffected.

**Files:**
- Create: `Modules/Core/app/Contracts/RestrictsCrudWrites.php`
- Create: `Modules/Core/app/Models/Concerns/DeniesGenericCrudWrites.php`
- Create: `Modules/Core/app/Exceptions/CrudWriteNotAllowedException.php`
- Modify: `Modules/Core/app/Services/Crud/CrudService.php` (insert/update/delete + a guard helper)
- Modify: `Modules/Core/app/Http/Controllers/CrudController.php` (map the exception to 403)
- Modify: `Modules/ERP/app/Models/JournalEntry.php`, `JournalEntryLine.php`, `VatRegisterEntry.php`, `StockMovement.php`, `StockCostLayer.php`, `StockLevel.php`
- Test: `Modules/ERP/tests/Feature/CrudWriteGuardTest.php` (new)

- [ ] **Step 1: Write the failing test**

Create `Modules/ERP/tests/Feature/CrudWriteGuardTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\JournalEntryLine;
use Modules\ERP\Models\StockCostLayer;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\VatRegisterEntry;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('core.expose_crud_api', true);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
    $this->actingAs($this->user);
});

it('marks the immutable/derived ERP models as write-restricted', function (): void {
    foreach ([JournalEntry::class, JournalEntryLine::class, VatRegisterEntry::class, StockMovement::class, StockCostLayer::class, StockLevel::class] as $model) {
        $instance = new $model;
        expect($instance)->toBeInstanceOf(RestrictsCrudWrites::class)
            ->and($instance->deniedCrudWrites())->toContain('insert')
            ->and($instance->deniedCrudWrites())->toContain('update')
            ->and($instance->deniedCrudWrites())->toContain('delete');
    }
});

it('leaves an ordinary editable ERP model unrestricted', function (): void {
    expect(new Invoice)->not->toBeInstanceOf(RestrictsCrudWrites::class);
});

it('returns 403 and persists nothing when inserting a journal entry via generic CRUD as superadmin', function (): void {
    $company = \Modules\ERP\Models\Company::query()->create([
        'slug' => 'crud-guard-co',
        'name' => 'Crud Guard Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    // The generic CRUD routes are registered by Core; the module is a URL parameter,
    // so the route name is "core.api.insert" with module=erp (NOT "erp.api.insert").
    // JournalEntry's only required create field is company_id, so validation passes
    // and execution reaches the CrudService write guard.
    $response = $this->postJson(
        route('core.api.insert', ['module' => 'erp', 'entity' => 'journal_entries']),
        ['company_id' => $company->id],
    );

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    expect(JournalEntry::query()->withoutGlobalScopes()->count())->toBe(0);
});
```

**Important caveats for this third test (read before running):**
- Route name: the dynamic CRUD routes live in `Modules/Core/routes/crud.php` and are registered under the Core name prefix, so the insert route is `core.api.insert` with `module`/`entity` as parameters (mirroring `Modules/Core/tests/Feature/Api/CrudApiTest.php`). Confirm with `php artisan route:list --path=insert`.
- Entity resolution: ERP model resolution requires the model registry/HelpersCache that the application test case populates (see the note at the top of `CrudApiTest.php`). If this test returns `400` with "Dynamic tables mapping is not enabled" instead of `403`, the request never resolved the ERP model — that is an *entity-resolution* concern, separate from the write guard. In that case rely on the two deterministic contract tests above as the proof of Fix 6 and raise the resolution gap as a Spec 3 routing/exposure item; do not weaken the guard. The guard itself (Steps 6-7) is correct by construction: it runs in `CrudService::insert/update/delete` before the permission check, for every caller.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact Modules/ERP/tests/Feature/CrudWriteGuardTest.php`
Expected: FAIL — `RestrictsCrudWrites` interface does not exist; the journal insert currently does not return 403.

- [ ] **Step 3: Create the Core contract**

Create `Modules/Core/app/Contracts/RestrictsCrudWrites.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Marks a model whose write operations must not be performed through the generic CRUD layer.
 *
 * Implementers are typically immutable or service-derived models (e.g. posted accounting
 * vouchers, stock movements) that must only be mutated through their owning service, which
 * enforces domain invariants the generic CRUD cannot.
 */
interface RestrictsCrudWrites
{
    /**
     * Generic CRUD write operations denied for this model.
     *
     * @return list<string> subset of "insert", "update", "delete", "forceDelete"
     */
    public function deniedCrudWrites(): array;
}
```

- [ ] **Step 4: Create the convenience trait**

Create `Modules/Core/app/Models/Concerns/DeniesGenericCrudWrites.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

/**
 * Denies every generic CRUD write operation. Use on models that may only be mutated
 * through their owning service. Implement {@see \Modules\Core\Contracts\RestrictsCrudWrites}.
 */
trait DeniesGenericCrudWrites
{
    /**
     * @return list<string>
     */
    public function deniedCrudWrites(): array
    {
        return ['insert', 'update', 'delete', 'forceDelete'];
    }
}
```

- [ ] **Step 5: Create the exception**

Create `Modules/Core/app/Exceptions/CrudWriteNotAllowedException.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when a generic CRUD write is attempted on a model that restricts it.
 * Mapped to HTTP 403 by {@see \Modules\Core\Http\Controllers\CrudController}.
 */
final class CrudWriteNotAllowedException extends RuntimeException
{
    public static function for(Model $model, string $operation): self
    {
        return new self(sprintf(
            'The "%s" operation is not allowed on "%s" through the generic CRUD API.',
            $operation,
            $model->getTable(),
        ));
    }
}
```

- [ ] **Step 6: Enforce the guard in `CrudService`**

In `Modules/Core/app/Services/Crud/CrudService.php`, add the imports (with the other `use` statements):

```php
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Exceptions\CrudWriteNotAllowedException;
```

Add the guard call as the first line of `insert()`, `update()`, and `delete()` — before the `ensurePermission()` call, so it applies even to superadmin:

In `insert()` (after `$model = $requestData->model;`, line 340):
```php
        $this->assertCrudWriteAllowed($model, 'insert');
```

In `update()` (after `$model = $requestData->model;`, line 362):
```php
        $this->assertCrudWriteAllowed($model, 'update');
```

In `delete()` (after `$model = $requestData->model;`, line 391):
```php
        $this->assertCrudWriteAllowed($model, 'delete');
```

Add this private method to the class:

```php
    private function assertCrudWriteAllowed(Model $model, string $operation): void
    {
        if ($model instanceof RestrictsCrudWrites && in_array($operation, $model->deniedCrudWrites(), true)) {
            throw CrudWriteNotAllowedException::for($model, $operation);
        }
    }
```

(`Illuminate\Database\Eloquent\Model` is already imported in this file.)

- [ ] **Step 7: Map the exception to HTTP 403 in `CrudController`**

In `Modules/Core/app/Http/Controllers/CrudController.php`, add the import:

```php
use Modules\Core\Exceptions\CrudWriteNotAllowedException;
```

Add a dedicated catch in `handleServiceCall()` — place it before the `catch (LogicException|...)` block (line 356), because `CrudWriteNotAllowedException` extends `RuntimeException` and must be matched before the generic `Throwable` handler:

```php
        } catch (CrudWriteNotAllowedException $ex) {
            return $this->buildResponse(
                new CrudResult(
                    data: null,
                    error: $ex->getMessage(),
                    statusCode: Response::HTTP_FORBIDDEN,
                ),
                $request,
            );
```

- [ ] **Step 8: Apply the trait to the six ERP models**

For each of the following files, add the two imports and change the class declaration so it implements the contract and uses the trait. Each model currently declares `final class <Name> extends Model`.

In `Modules/ERP/app/Models/JournalEntry.php`:
- Add imports:
```php
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Models\Concerns\DeniesGenericCrudWrites;
```
- Change `final class JournalEntry extends Model` to:
```php
final class JournalEntry extends Model implements RestrictsCrudWrites
```
- Add inside the class body (with the other `use <Trait>;` lines):
```php
    use DeniesGenericCrudWrites;
```

Repeat the exact same change in each of:
- `Modules/ERP/app/Models/JournalEntryLine.php` — `final class JournalEntryLine extends Model implements RestrictsCrudWrites`
- `Modules/ERP/app/Models/VatRegisterEntry.php` — `final class VatRegisterEntry extends Model implements RestrictsCrudWrites`
- `Modules/ERP/app/Models/StockMovement.php` — `final class StockMovement extends Model implements RestrictsCrudWrites`
- `Modules/ERP/app/Models/StockCostLayer.php` — `final class StockCostLayer extends Model implements RestrictsCrudWrites`
- `Modules/ERP/app/Models/StockLevel.php` — `final class StockLevel extends Model implements RestrictsCrudWrites`

Each file gets the same two `use` imports at the top and the same `use DeniesGenericCrudWrites;` inside the class body.

- [ ] **Step 9: Run the test to verify it passes**

Run: `php artisan test --compact Modules/ERP/tests/Feature/CrudWriteGuardTest.php`
Expected: PASS (3 tests).

- [ ] **Step 10: Regression — service paths and Core CRUD still work**

Run: `php artisan test --compact Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php Modules/ERP/tests/Feature/InventoryAccountingGoldenMasterTest.php Modules/Core/tests/Feature/Api/CrudApiTest.php`
Expected: PASS — service-layer creation (which does not go through `CrudService`) is unaffected, and the Core CRUD on non-restricted models still works.

- [ ] **Step 11: Format and commit**

```bash
vendor/bin/pint --dirty
git add Modules/Core/app/Contracts/RestrictsCrudWrites.php Modules/Core/app/Models/Concerns/DeniesGenericCrudWrites.php Modules/Core/app/Exceptions/CrudWriteNotAllowedException.php Modules/Core/app/Services/Crud/CrudService.php Modules/Core/app/Http/Controllers/CrudController.php Modules/ERP/app/Models/JournalEntry.php Modules/ERP/app/Models/JournalEntryLine.php Modules/ERP/app/Models/VatRegisterEntry.php Modules/ERP/app/Models/StockMovement.php Modules/ERP/app/Models/StockCostLayer.php Modules/ERP/app/Models/StockLevel.php Modules/ERP/tests/Feature/CrudWriteGuardTest.php
git commit -m "feat(core): guard generic-CRUD writes on immutable/derived ERP models"
```

---

## Final verification

- [x] **Run the full ERP suite**

Run: `php artisan test --compact Modules/ERP/tests`
Result: **240 passed, 1 skipped** (1184 assertions), ~163s.

- [x] **Run the touched Core tests**

Run: `php artisan test --compact Modules/Core/tests/Feature/Api/CrudApiTest.php`
Result: PASS (included in Task 8 regression: 23 tests with golden masters).

- [x] **Format the whole change set**

Run: `vendor/bin/pint --dirty`
Result: clean on all touched files.

---

## Module version bump (per `.cursor/rules/08-versioning.mdc`)

These changes are P0 bug fixes plus internal hardening (no new public features), so a `patch` bump is appropriate for `Modules/ERP`. Fix 6 also adds a small Core contract (a backward-compatible addition), suggesting a `patch` for `Modules/Core` as well. Do not run the version commands automatically — ask the user first, then, if approved:

```bash
cd Modules/ERP && composer version:patch
cd Modules/Core && composer version:patch
```

---

## Self-Review Notes

- **Spec coverage:** Fix 1 → Task 1; Fix 2 → Task 2; Fix 3 → Task 3; Fix 4 (decimal money math) → Tasks 4-6; Fix 5 (test gaps) → Task 7; Fix 6 (CRUD write guard) → Task 8. The dropped bank-tolerance and return-idempotency items are intentionally absent (see spec "Considered and dropped").
- **Type consistency:** `Decimal` exposes `add/sub/mul/div/negate/abs/isZero/isNegative/format`; every call site in Task 6 uses only those. `TaxLineCalculator::lineTax(string, string): string` is defined in Task 5 and consumed in Task 6. `RestrictsCrudWrites::deniedCrudWrites(): array`, the `DeniesGenericCrudWrites` trait, and `CrudWriteNotAllowedException::for()` are defined in Task 8 and used consistently by `CrudService` and the test.
- **Non-breaking guarantees:** Fix 3 only blocks closed *covering* periods; Fix 6 only guards writes (CMS read delegation is untouched) and defaults to no restriction when the contract is absent.

---

## Post-Implementation Code Review (2026-06-30)

Full review after all 8 tasks. Verdict: **approved with reserve**.

### Permission prefix note — `default` is expected for current ERP models

Fix 1 seeds `default.erp_*.*`, and that matches runtime checks for the current ERP models.
Laravel's `Model::getConnectionName()` returns the model's explicit `$connection` property, not the
resolved default database connection name. Since ERP models do not define `$connection`, both the
seeder and `ERPModelPolicy` / `CrudService` fall back to `default`.

`ErpModelPolicyTest::policyPermission()` mirrors the policy construction so the test remains correct
if an explicit model connection is introduced later. This is future-proofing, not a workaround for a
current production bug.

### Important follow-ups — patch applied (`971851d` ERP, `15b11c8` Core, 2026-07-01)

| # | Finding | Status |
|---|---------|--------|
| 1 | CRUD guard on restore/approve/lock | Done — guard + HTTP tests |
| 2 | Bank CSV malformed non-empty dates | Done — `isParseableDate()` + test |
| 3 | `resolveFiscalPeriod()` solar `year` filter | Done — filter removed + test |
| 4 | Lowercase `module=erp` resolution | Done — Core `15b11c8` |
| 5 | HTTP update/delete on restricted models | Done — `CrudWriteGuardTest` |
| 6 | Centralize permission-name construction | Future (explicit connections) |
| 7 | `Decimal::div()` divide-by-zero guard + test | Done — ERP `971851d` |

### Task-specific notes

- **Task 1:** Seeder test asserts `default.*`, which is also the current runtime policy prefix for
  ERP models without an explicit `$connection`.
- **Task 7:** Bad-row CSV test covers empty `booked_at`; malformed non-empty dates covered in patch
  `971851d`.
- **Task 8:** Core `354299c` + ERP `1ede917`; HTTP coverage extended in `971851d`; module alias in
  Core `15b11c8`.

### What went well

- TDD followed on all fixes; golden masters unchanged after decimal refactor.
- CRUD guard before permission check — correct integrity-over-convenience placement.
- Submodule commits on `master` (ERP 8 tasks + patch `971851d`, Core `354299c` + `15b11c8`).

---

## Post-review patch — Task 9 and follow-ups (`971851d` / `15b11c8`)

**Status:** Implemented 2026-07-01 in ERP commit `971851d` and Core commit `15b11c8`.

**Scope delivered:**
- `Decimal::div()` zero-divisor guard + PHPDoc + test
- Bank CSV malformed-date validation + test
- `resolveFiscalPeriod()` — removed redundant `year` filter
- CRUD HTTP tests: update/delete/activate/approve/lock + lowercase `module=erp`
- Version bumps: ERP `v1.14.4`, Core `v1.55.4+`

**Remaining (Spec 3 / future):** per-model API exposure governance, permission-name helper if
explicit model connections are introduced. **→ Spec 2 Phase 3 master backlog.**
