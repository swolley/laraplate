# ERP Accounting Golden Master Test Suite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development
> (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use
> checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add end-to-end accounting regression tests that prove ERP posting workflows produce
stable journals, VAT registers, payment schedules, and financial statements.

**Architecture:** Keep production code unchanged unless a golden-master test exposes a real
accounting defect. Add a focused Pest feature test file that builds deterministic accounting
scenarios from public models/services and asserts exact signed ledger effects across invoices,
credit notes, VAT settlement, and statements.

**Tech Stack:** PHP 8.5, Laravel 12, Pest, Eloquent, ERP module services.

---

## Current Coverage Baseline

Existing focused tests already cover individual pieces:

- `Modules/ERP/tests/Feature/InvoicePostingServiceTest.php`: sale/purchase posting, DDT invoice
  constraints, three-way match persistence, posting/unposting side effects.
- `Modules/ERP/tests/Feature/CreditNoteServiceTest.php`: credit note creation, posting inversion,
  document numbering, over-credit prevention.
- `Modules/ERP/tests/Feature/VatRegisterServiceTest.php`: sales/purchase VAT registers, protocol
  numbering, credit-note negative VAT, VAT settlement carry-forward.
- `Modules/ERP/tests/Feature/FinancialStatementsTest.php`: trial balance, balance sheet, income
  statement calculations from posted journals.
- `Modules/ERP/tests/Feature/PaymentScheduleServiceTest.php`: payment schedules and allocation
  status transitions.
- `Modules/ERP/tests/Feature/AccountingSequencesAndPostingTest.php`: journal posting, reversal,
  immutability, fiscal-period closure guard.

Missing coverage is not a single service method. The risk is cross-service drift: an invoice can
post correctly in isolation while later changes silently break VAT register totals, payment
schedules, statement totals, or reversal symmetry. The golden-master suite must assert complete
end-to-end accounting outcomes from realistic ERP documents.

## File Map

- Create: `Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php`
- Reuse services:
  - `Modules\ERP\Services\Accounting\CreditNoteService`
  - `Modules\ERP\Services\Accounting\VatSettlementService`
  - `Modules\ERP\Services\Reporting\TrialBalanceService`
  - `Modules\ERP\Services\Reporting\IncomeStatementService`
  - `Modules\ERP\Services\Reporting\BalanceSheetService`
- Reuse models:
  - `Company`, `FiscalYear`, `FiscalPeriod`, `Invoice`, `InvoiceLine`, `JournalEntry`,
    `JournalEntryLine`, `Party`, `PaymentScheduleLine`, `TaxCode`, `VatRegisterEntry`,
    `VatSettlement` when settlement persistence needs direct assertions.

## Golden-Master Rules

- Tests must use manual model setup; ERP factories are still not part of this module.
- Use unique helper names prefixed with `goldenMaster` to avoid Pest global function collisions
  with existing test files.
- Assert signed journal lines by account role / description, not only line counts.
- Assert string-normalized decimals with 4 decimal places.
- Use `posted_at` dates inside explicit fiscal periods so VAT settlement and statements are
  deterministic.
- Do not assert exact document reference formatting unless the test is specifically about
  numbering; assert sequence type and non-null reference for broader flows.

## Task 1: Sale Invoice Golden Master

**Files:**
- Create: `Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php`

- [x] **Step 1: Add deterministic setup helpers**

Create the file with strict types, imports, `RefreshDatabase`, and helper functions:

```php
<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Casts\VatRegisterType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PaymentScheduleLine;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Models\VatRegisterEntry;

uses(RefreshDatabase::class);

function goldenMasterCompany(string $slug): array
{
    $company = Company::query()->create([
        'slug' => $slug . '-' . uniqid(),
        'name' => 'Golden Master ' . $slug,
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $fiscal_year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $period = FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]);

    return [$company, $fiscal_year, $period];
}

function goldenMasterVat(Company $company): TaxCode
{
    return TaxCode::query()->create([
        'company_id' => $company->id,
        'code' => 'VAT22',
        'kind' => 'vat',
        'country' => 'IT',
        'rate' => '22.0000',
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => '2026-01-01',
    ]);
}

function goldenMasterParty(Company $company, string $name, bool $is_customer = true, bool $is_supplier = false): Party
{
    return Party::query()->create([
        'company_id' => $company->id,
        'name' => $name,
        'is_customer' => $is_customer,
        'is_supplier' => $is_supplier,
    ]);
}

function goldenMasterPostedInvoiceJournalLines(Invoice $invoice): array
{
    $journal = JournalEntry::query()
        ->withoutGlobalScopes()
        ->with('lines')
        ->findOrFail((int) $invoice->journal_entry_id);

    return $journal->lines
        ->mapWithKeys(static fn ($line): array => [
            (string) $line->description => number_format((float) $line->amount_local, 4, '.', ''),
        ])
        ->all();
}
```

- [x] **Step 2: Write the sale invoice golden-master regression test**

Append this regression test:

```php
it('posts sale invoice into journal, VAT register, payment schedule, and trial balance', function (): void {
    [$company] = goldenMasterCompany('sale');
    $vat = goldenMasterVat($company);
    goldenMasterParty($company, 'Golden Customer');

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Consulting services',
        'quantity' => '2.0000',
        'unit_price' => '500.0000',
        'tax_code_id' => $vat->id,
    ]);

    $invoice->update(['posted_at' => CarbonImmutable::parse('2026-01-15 10:00:00')]);
    $invoice->refresh();

    expect($invoice->reference)->not->toBeNull()
        ->and($invoice->journal_entry_id)->not->toBeNull();

    expect(goldenMasterPostedInvoiceJournalLines($invoice))->toBe([
        'Trade receivable' => '1220.0000',
        'Sales revenue' => '-1000.0000',
        'VAT output' => '-220.0000',
    ]);

    $vat_entry = VatRegisterEntry::query()
        ->where('invoice_id', (int) $invoice->id)
        ->firstOrFail();

    expect($vat_entry->register_type)->toBe(VatRegisterType::Sales)
        ->and((string) $vat_entry->taxable_amount)->toBe('1000.0000')
        ->and((string) $vat_entry->tax_amount)->toBe('220.0000')
        ->and($vat_entry->protocol_number)->toBe(1);

    $schedule = PaymentScheduleLine::query()
        ->where('invoice_id', (int) $invoice->id)
        ->firstOrFail();

    expect((string) $schedule->amount_local)->toBe('1220.0000')
        ->and($schedule->status)->toBe(PaymentScheduleStatus::Open);
});
```

- [x] **Step 3: Run the test and verify the baseline**

Run:

```bash
php artisan test --compact Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php --filter "posts sale invoice"
```

Expected: the test should pass if the existing ERP posting workflow is stable. If it fails, stop
and inspect the exact journal/VAT/schedule difference before changing production code.

- [x] **Step 4: Commit**

```bash
git add Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php
git commit -m "test(erp): add sale invoice accounting golden master"
```

## Task 2: Purchase Invoice Golden Master

**Files:**
- Modify: `Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php`

- [x] **Step 1: Write the purchase invoice regression test**

Append:

```php
it('posts purchase invoice into expense, VAT input, payable, and purchase VAT register', function (): void {
    [$company] = goldenMasterCompany('purchase');
    $vat = goldenMasterVat($company);
    goldenMasterParty($company, 'Golden Supplier', is_customer: false, is_supplier: true);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Supplier services',
        'quantity' => '1.0000',
        'unit_price' => '300.0000',
        'tax_code_id' => $vat->id,
    ]);

    $invoice->update(['posted_at' => CarbonImmutable::parse('2026-01-16 10:00:00')]);
    $invoice->refresh();

    expect(goldenMasterPostedInvoiceJournalLines($invoice))->toBe([
        'Purchase expense' => '300.0000',
        'VAT input' => '66.0000',
        'Trade payable' => '-366.0000',
    ]);

    $vat_entry = VatRegisterEntry::query()
        ->where('invoice_id', (int) $invoice->id)
        ->firstOrFail();

    expect($vat_entry->register_type)->toBe(VatRegisterType::Purchases)
        ->and((string) $vat_entry->taxable_amount)->toBe('300.0000')
        ->and((string) $vat_entry->tax_amount)->toBe('66.0000');
});
```

- [x] **Step 2: Run the test**

```bash
php artisan test --compact Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php --filter "posts purchase invoice"
```

Expected: pass with exact signed journal lines. If the purchase test posts without three-way match
links, keep this test as the clean accounting baseline and leave linked PO/GR matching in
`InvoicePostingServiceTest.php` / `ThreeWayMatchServiceTest.php`.

- [x] **Step 3: Commit**

```bash
git add Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php
git commit -m "test(erp): add purchase invoice accounting golden master"
```

## Task 3: Credit Note Golden Master

**Files:**
- Modify: `Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php`

- [x] **Step 1: Add imports**

Add:

```php
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Services\Accounting\CreditNoteService;
```

- [x] **Step 2: Write the credit note regression test**

Append:

```php
it('posts sale credit note as negative journal and negative VAT register entry', function (): void {
    [$company] = goldenMasterCompany('credit-note');
    $vat = goldenMasterVat($company);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Returned service',
        'quantity' => '1.0000',
        'unit_price' => '100.0000',
        'tax_code_id' => $vat->id,
    ]);
    $invoice->update(['posted_at' => CarbonImmutable::parse('2026-01-17 10:00:00')]);
    $invoice->refresh();

    $credit_note = app(CreditNoteService::class)->createFromInvoice($invoice);
    $credit_note->update(['posted_at' => CarbonImmutable::parse('2026-01-18 10:00:00')]);
    $credit_note->refresh();

    expect($credit_note->invoice_type)->toBe(InvoiceType::CreditNote)
        ->and($credit_note->reference)->not->toBeNull()
        ->and(DocumentSequence::query()
            ->where('company_id', (int) $company->id)
            ->where('document_type', DocumentType::SalesCreditNote)
            ->exists())->toBeTrue();

    expect(goldenMasterPostedInvoiceJournalLines($credit_note))->toBe([
        'Trade receivable' => '-122.0000',
        'Sales revenue' => '100.0000',
        'VAT output' => '22.0000',
    ]);

    $vat_entry = VatRegisterEntry::query()
        ->where('invoice_id', (int) $credit_note->id)
        ->firstOrFail();

    expect($vat_entry->register_type)->toBe(VatRegisterType::Sales)
        ->and((string) $vat_entry->taxable_amount)->toBe('-100.0000')
        ->and((string) $vat_entry->tax_amount)->toBe('-22.0000');
});
```

- [x] **Step 3: Run the test**

```bash
php artisan test --compact Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php --filter "posts sale credit note"
```

Expected: pass. If order of journal lines differs, do not relax the signed amounts; adjust the
helper to compare by description as shown in Task 1.

- [x] **Step 4: Commit**

```bash
git add Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php
git commit -m "test(erp): add credit note accounting golden master"
```

## Task 4: Period VAT Settlement Golden Master

**Files:**
- Modify: `Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php`

- [x] **Step 1: Add imports**

Add:

```php
use Modules\ERP\Casts\VatSettlementStatus;
use Modules\ERP\Services\Accounting\VatSettlementService;
```

- [x] **Step 2: Write the VAT settlement regression test**

Append:

```php
it('computes period VAT settlement from posted sale purchase and credit note registers', function (): void {
    [$company, $fiscal_year, $period] = goldenMasterCompany('vat-settlement');
    $vat = goldenMasterVat($company);

    $sale = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $sale->lines()->create([
        'line_no' => 1,
        'description' => 'Sale',
        'quantity' => '1.0000',
        'unit_price' => '1000.0000',
        'tax_code_id' => $vat->id,
    ]);
    $sale->update(['posted_at' => CarbonImmutable::parse('2026-01-10 10:00:00')]);

    $purchase = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $purchase->lines()->create([
        'line_no' => 1,
        'description' => 'Purchase',
        'quantity' => '1.0000',
        'unit_price' => '300.0000',
        'tax_code_id' => $vat->id,
    ]);
    $purchase->update(['posted_at' => CarbonImmutable::parse('2026-01-11 10:00:00')]);

    $credit_note = app(CreditNoteService::class)->createFromInvoice($sale->fresh());
    $credit_note->lines()->delete();
    $credit_note->lines()->create([
        'line_no' => 1,
        'description' => 'Partial credit',
        'quantity' => '1.0000',
        'unit_price' => '100.0000',
        'tax_code_id' => $vat->id,
    ]);
    $credit_note->update(['posted_at' => CarbonImmutable::parse('2026-01-12 10:00:00')]);

    $settlement = app(VatSettlementService::class)->compute((int) $company->id, (int) $period->id);

    expect($settlement->status)->toBe(VatSettlementStatus::Draft)
        ->and((string) $settlement->vat_sales)->toBe('198.0000')
        ->and((string) $settlement->vat_purchases)->toBe('66.0000')
        ->and((string) $settlement->previous_credit)->toBe('0.0000')
        ->and((string) $settlement->settlement_amount)->toBe('132.0000');
});
```

- [x] **Step 3: Run the test**

```bash
php artisan test --compact Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php --filter "computes period VAT settlement"
```

Expected: pass. This locks the period-level VAT formula: sales VAT minus credit-note VAT minus
purchase VAT minus previous credit.

- [x] **Step 4: Commit**

```bash
git add Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php
git commit -m "test(erp): add vat settlement golden master"
```

## Task 5: Financial Statements Golden Master

**Files:**
- Modify: `Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php`

- [x] **Step 1: Add imports**

Add:

```php
use Modules\ERP\Services\Reporting\BalanceSheetService;
use Modules\ERP\Services\Reporting\IncomeStatementService;
use Modules\ERP\Services\Reporting\TrialBalanceService;
```

- [x] **Step 2: Write the statement regression test**

Append:

```php
it('rolls posted documents into trial balance income statement and balance sheet', function (): void {
    [$company] = goldenMasterCompany('statements');
    $vat = goldenMasterVat($company);

    $sale = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $sale->lines()->create([
        'line_no' => 1,
        'description' => 'Sale',
        'quantity' => '1.0000',
        'unit_price' => '1000.0000',
        'tax_code_id' => $vat->id,
    ]);
    $sale->update(['posted_at' => CarbonImmutable::parse('2026-01-20 10:00:00')]);

    $purchase = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $purchase->lines()->create([
        'line_no' => 1,
        'description' => 'Purchase',
        'quantity' => '1.0000',
        'unit_price' => '300.0000',
        'tax_code_id' => $vat->id,
    ]);
    $purchase->update(['posted_at' => CarbonImmutable::parse('2026-01-21 10:00:00')]);

    $trial_balance = app(TrialBalanceService::class)->generate(
        (int) $company->id,
        CarbonImmutable::parse('2026-01-31 23:59:59'),
    );
    $income_statement = app(IncomeStatementService::class)->generate(
        (int) $company->id,
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-01-31 23:59:59'),
    );
    $balance_sheet = app(BalanceSheetService::class)->generate(
        (int) $company->id,
        CarbonImmutable::parse('2026-01-31 23:59:59'),
    );

    $debit_total = collect($trial_balance)->sum(static fn (array $row): float => (float) $row['debit']);
    $credit_total = collect($trial_balance)->sum(static fn (array $row): float => (float) $row['credit']);

    expect(number_format($debit_total, 4, '.', ''))->toBe(number_format($credit_total, 4, '.', ''))
        ->and($income_statement['total_revenue'])->toBe('1000.0000')
        ->and($income_statement['total_expenses'])->toBe('300.0000')
        ->and($income_statement['net_income'])->toBe('700.0000')
        ->and($balance_sheet['is_balanced'])->toBeTrue();
});
```

- [x] **Step 3: Run the statement test**

```bash
php artisan test --compact Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php --filter "rolls posted documents"
```

Expected: pass. If `BalanceSheetService` reports not balanced, inspect whether VAT receivable /
payable accounts are classified correctly before changing the assertion.

- [x] **Step 4: Commit**

```bash
git add Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php
git commit -m "test(erp): add statement golden master"
```

## Task 6: Unpost Reversal Golden Master

**Files:**
- Modify: `Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php`

- [x] **Step 1: Write the reversal regression test**

Append:

```php
it('unposts invoice by reversing journal clearing VAT register and removing schedule', function (): void {
    [$company] = goldenMasterCompany('unpost');
    $vat = goldenMasterVat($company);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Sale',
        'quantity' => '1.0000',
        'unit_price' => '100.0000',
        'tax_code_id' => $vat->id,
    ]);
    $invoice->update(['posted_at' => CarbonImmutable::parse('2026-01-22 10:00:00')]);
    $invoice->refresh();

    $original_journal_id = (int) $invoice->journal_entry_id;

    $invoice->update(['posted_at' => null]);
    $invoice->refresh();

    expect($invoice->journal_entry_id)->toBeNull()
        ->and($invoice->reference)->toBeNull()
        ->and(VatRegisterEntry::query()->where('invoice_id', (int) $invoice->id)->count())->toBe(0)
        ->and(PaymentScheduleLine::query()->where('invoice_id', (int) $invoice->id)->count())->toBe(0)
        ->and(JournalEntry::query()
            ->withoutGlobalScopes()
            ->where('reverses_journal_entry_id', $original_journal_id)
            ->exists())->toBeTrue();
});
```

- [x] **Step 2: Run the reversal test**

```bash
php artisan test --compact Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php --filter "unposts invoice"
```

Expected: pass. This locks the cross-service rollback behavior.

- [x] **Step 3: Commit**

```bash
git add Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php
git commit -m "test(erp): add invoice unpost golden master"
```

## Verification

Run the complete new golden-master suite:

```bash
php artisan test --compact Modules/ERP/tests/Feature/AccountingGoldenMasterTest.php
```

Then run the adjacent accounting suites:

```bash
php artisan test --compact Modules/ERP/tests/Feature/InvoicePostingServiceTest.php Modules/ERP/tests/Feature/CreditNoteServiceTest.php Modules/ERP/tests/Feature/VatRegisterServiceTest.php Modules/ERP/tests/Feature/FinancialStatementsTest.php Modules/ERP/tests/Feature/PaymentScheduleServiceTest.php Modules/ERP/tests/Feature/AccountingSequencesAndPostingTest.php
```

Before committing final cleanup:

```bash
vendor/bin/pint --dirty
```

Expected result: all listed tests pass and Pint reports no formatting issues. If a golden-master
test fails, treat it as a high-signal accounting regression investigation, not as a reason to relax
assertions.

## Follow-Up After Golden Master

- Add a second suite for inventory/accounting integration: DDT outbound COGS, inbound return DDT,
  supplier return outbound DDT, and COGS reversal symmetry.
- Add a focused settlement-confirmation test proving confirmed settlements cannot be recomputed or
  mutated.
- Add API/export work only after the golden-master accounting suite is green.
