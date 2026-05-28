# ERP M4 - Policies, Filament Actions, Reporting Alignment Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> or `superpowers:executing-plans`.

**Goal:** Add ERP domain authorization and reporting without conflicting with Core's existing
permission model or duplicating current Filament invoice actions.

**Architecture:** Core already seeds table permissions from model tables and `ActionEnum`.
M4 adds only domain-specific abilities, registers Laravel policies explicitly, and reuses existing
Filament resource/page structure.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Spatie Permission, Pest.

---

## Current Truth

- Core common permission actions are `select`, `insert`, `update`, `delete`, `restore`,
  `forceDelete`, `approve`, `publish`, and `lock`.
- Permission names include actual table names, for example `default.erp_invoices.update`.
- ERP table names come from `Modules\ERP\Enums\ERPTables`.
- Existing invoice actions live in
  `Modules/ERP/app/Filament/Resources/Invoices/Actions/InvoicePostingActions.php`.
- Current invoice pages are under `Modules/ERP/app/Filament/Resources/Invoices/Pages/`.

## Task 1: Seed Domain Abilities Only

**Files:**
- Modify: `Modules/ERP/database/seeders/ERPDatabaseSeeder.php`

- [ ] Add a small private method such as `ensureDomainPermissions()` and call it after Core tables
  are available.
- [ ] Seed custom permissions with `Permission::firstOrCreate()` and `guard_name => web`.
- [ ] Use names based on `ERPTables`, for example:
  - `default.erp_invoices.post`
  - `default.erp_invoices.unpost`
  - `default.erp_invoices.force_post`
  - `default.erp_delivery_notes.post`
  - `default.erp_delivery_notes.unpost`
  - `default.erp_fiscal_periods.close`
  - `default.erp_fiscal_periods.reopen`
  - `default.erp_journal_entries.reverse`
  - `default.erp_sales_orders.amend`
  - `default.erp_quotations.unlock`
  - `default.erp_document_sequences.reset`
- [ ] Do not seed `viewAny`, `view`, or `create`; those are not Core action names.

## Task 2: Register Policies

**Files:**
- Modify: `Modules/ERP/app/Providers/ERPServiceProvider.php`
- Create: `Modules/ERP/app/Policies/InvoicePolicy.php`
- Create only additional policies needed by actions implemented in this milestone.

- [ ] Register policies with `Gate::policy()` in `boot()` or the established Laravel provider
  pattern used by this app.
- [ ] Policy methods should combine table permissions and state checks:
  - `post`: permission exists and record is not posted.
  - `unpost`: permission exists and record is posted.
  - `forcePost`: permission exists, invoice is purchase, and record is not posted.
  - `close/reopen`: fiscal period status allows the transition.
  - `reverse`: journal entry is posted and has no reversal.
- [ ] Use `Modules\Core\Models\User` as the user type.

## Task 3: Reuse Filament Invoice Actions

**Files:**
- Modify: `Modules/ERP/app/Filament/Resources/Invoices/Actions/InvoicePostingActions.php`
- Modify: `Modules/ERP/app/Filament/Resources/Invoices/Pages/EditInvoice.php`

- [ ] Add authorization checks to existing `post()` and `unpost()` actions rather than creating
  duplicate page-local actions.
- [ ] Preserve force 3-way match checkbox behavior for purchase invoices.
- [ ] Keep the current observer-driven posting entry point:
  `InvoicePostingActions::postInvoice()` sets `forceThreeWayMatchOnPosting` and updates
  `posted_at`.
- [ ] Add focused Filament action tests only if this project already has a compatible Livewire /
  Filament testing pattern; otherwise cover policy decisions directly.

## Task 4: Add Delivery Note, Fiscal Period, Journal Actions

**Files:**
- Modify relevant existing Filament resource pages under:
  - `Modules/ERP/app/Filament/Resources/DeliveryNotes/Pages/`
  - `Modules/ERP/app/Filament/Resources/FiscalPeriods/Pages/`
  - `Modules/ERP/app/Filament/Resources/JournalEntries/Pages/`

- [ ] Delivery note actions must call existing service methods:
  - post inventory: set `posted_at` if needed, then rely on observer/service path;
  - unpost inventory: use `DeliveryNoteInventoryService::unpostInventory()`.
- [ ] Do not call non-existent `DeliveryNoteInventoryService::post()` or `::unpost()`.
- [ ] Fiscal period **close** action must call `FiscalPeriodCloser::closePeriod()` (exists).
- [ ] Fiscal period **reopen** action: `FiscalPeriodCloser` has no `reopen()` method.
  Before implementing the action, add `reopenPeriod(FiscalPeriod $period): void` to
  `FiscalPeriodCloser` with appropriate state-machine guard (`status === 'closed'`), or
  mark the reopen ability as backlog and skip it from this milestone.
- [ ] Journal reversal must use `JournalPostingService::reverse()` with the current method
  signature.

## Task 5: Reporting Services

**Files:**
- Create: `Modules/ERP/app/Services/Reporting/SalesPipelineService.php`
- Create: `Modules/ERP/app/Services/Reporting/StockValuationService.php`
- Create or modify focused reporting tests.

- [ ] `SalesPipelineService` must query `Opportunity` using actual fields:
  `status`, `expected_value_doc`, `expected_value_local`, `won_at`, and `lost_at`.
  There are no `amount_doc` / `amount_local` columns on `Opportunity`.
- [ ] `StockValuationService` must query `StockLevel` using `quantity` and
  `weighted_avg_cost`.
- [ ] Avoid raw SQL date formatting unless necessary; prefer portable query builder or collection
  grouping for testability.
- [ ] Tests must create required model rows manually unless ERP factories are introduced in a
  separate explicit task.

## Task 6: Reporting Pages

**Files:**
- Create: `Modules/ERP/app/Filament/Pages/SalesPipelinePage.php`
- Create: `Modules/ERP/app/Filament/Pages/StockValuationPage.php`
- Create Blade views under `Modules/ERP/resources/views/filament/pages/`.

- [ ] Resolve company context via `Modules\ERP\Helpers\current_company_id()` or explicit page
  filters. Do not assume a user-level current-company accessor exists.
- [ ] Pages are read-only and must not create new tables.

## Verification

Run the narrowest applicable tests, then:

```bash
php artisan test --compact Modules/ERP/tests/Feature
vendor/bin/pint --dirty
```

If full feature tests are too broad during implementation, document the narrower commands run and
the reason.

## Assumptions

- Custom ERP abilities are additive and do not replace Core CRUD permissions.
- Invoice actions should remain centralized in `InvoicePostingActions`.
- Reporting is operational/read-only and not part of fiscal statement generation.

## Implementation Status / Verification

- Implemented first development slice: shared ERP policy registration, seeded domain permissions,
  invoice action authorization, and `FiscalPeriodCloser::reopenPeriod()`.
- Existing financial statement reporting services were aligned to `ERPTables::*` and the installed
  PHP runtime; `TrialBalancePage`, `IncomeStatementPage`, and `BalanceSheetPage` now use the
  Filament 5 non-static page view override.
- Operational `SalesPipelineService` and `StockValuationService` remain a follow-up development
  slice unless this milestone is expanded.
- Verified on 2026-05-29:
  - `php artisan test --compact Modules/ERP/tests/Integration/Services/Accounting/FiscalPeriodCloserTest.php`
  - `php artisan test --compact Modules/ERP/tests/Feature/FinancialStatementsTest.php`
  - `php artisan test --compact Modules/ERP/tests/Feature/Filament/ERPFilamentResourcesTest.php Modules/ERP/tests/Feature/Filament/ERPFilamentCommercialResourcesTest.php`
  - `php artisan migrate --pretend --no-interaction` -> `Nothing to migrate`
  - `vendor/bin/pint --dirty`
