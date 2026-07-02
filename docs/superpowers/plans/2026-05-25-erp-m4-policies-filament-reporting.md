# ERP M4 - Policies, Filament Actions, Reporting Alignment Plan

> **Navigation:** Open items from this plan are consolidated in
> [`specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md`](../specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md)
> (ERP Spec 2 master backlog, Phase 2A). This file is kept for historical context.

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

- [x] Add a small private method such as `ensureDomainPermissions()` and call it after Core tables
  are available.
- [x] Seed custom permissions with `Permission::firstOrCreate()`.
- [x] Use permission names based on each model's actual connection and table name.
- [x] Do not seed `viewAny`, `view`, or `create`; those are not Core action names.
- [ ] Follow-up: seed additional custom abilities only when the matching UI/service action is
  implemented. Candidate abilities include `force_post`, `close`, `reopen`, `reverse`, `amend`,
  `unlock`, and `reset`.

## Task 2: Register Policies

**Files:**
- Modify: `Modules/ERP/app/Providers/ERPServiceProvider.php`
- Create: `Modules/ERP/app/Policies/ERPModelPolicy.php`

- [x] Register policies with `Gate::policy()` in `boot()` using the established provider pattern.
- [x] Use `Modules\Core\Models\User` as the user type.
- [x] Centralize table-permission checks in `ERPModelPolicy` for the ERP models covered by v1.
- [ ] Follow-up: add state-aware policy methods only for domain actions that are exposed in UI or
  services. Examples: invoice `post`/`unpost` state guards, fiscal period `close`/`reopen`, journal
  `reverse`, sales order `amend`, quotation `unlock`, and document sequence `reset`.

## Task 3: Reuse Filament Invoice Actions

**Files:**
- Modify: `Modules/ERP/app/Filament/Resources/Invoices/Actions/InvoicePostingActions.php`
- Modify: `Modules/ERP/app/Filament/Resources/Invoices/Pages/EditInvoice.php`

- [x] Add authorization checks to existing `post()` and `unpost()` actions rather than creating
  duplicate page-local actions.
- [x] Preserve force 3-way match checkbox behavior for purchase invoices.
- [x] Keep the current observer-driven posting entry point:
  `InvoicePostingActions::postInvoice()` sets `forceThreeWayMatchOnPosting` and updates
  `posted_at`.
- [x] Cover Filament resource/page registration and server-side rendering with smoke tests. Full
  browser click-through remains optional until a browser runner is part of the test workflow.

## Task 4: Add Delivery Note, Fiscal Period, Journal Actions

**Files:**
- Modify relevant existing Filament resource pages under:
  - `Modules/ERP/app/Filament/Resources/DeliveryNotes/Pages/`
  - `Modules/ERP/app/Filament/Resources/FiscalPeriods/Pages/`
  - `Modules/ERP/app/Filament/Resources/JournalEntries/Pages/`

- [ ] Follow-up: delivery note post/unpost page actions must call existing service methods:
  - post inventory: set `posted_at` if needed, then rely on observer/service path;
  - unpost inventory: use `DeliveryNoteInventoryService::unpostInventory()`.
- [x] Do not call non-existent `DeliveryNoteInventoryService::post()` or `::unpost()`.
- [ ] Follow-up: fiscal period **close** page action must call `FiscalPeriodCloser::closePeriod()`.
- [x] `FiscalPeriodCloser::reopenPeriod(FiscalPeriod $period): void` exists for future UI actions.
- [ ] Follow-up: fiscal period **reopen** page action must call `FiscalPeriodCloser::reopenPeriod()`
  and keep the state-machine guard (`status === 'closed'`).
- [ ] Follow-up: journal reversal page action must use `JournalPostingService::reverse()` with the
  current method signature.

## Task 5: Reporting Services

**Files:**
- Create: `Modules/ERP/app/Services/Reporting/SalesPipelineService.php`
- Create: `Modules/ERP/app/Services/Reporting/StockValuationService.php`
- Create or modify focused reporting tests.

- [x] `SalesPipelineService` must query `Opportunity` using actual fields:
  `status`, `expected_value_doc`, `expected_value_local`, `won_at`, and `lost_at`.
  There are no `amount_doc` / `amount_local` columns on `Opportunity`.
- [x] `StockValuationService` must query `StockLevel` using `quantity` and
  `weighted_avg_cost`.
- [x] Avoid raw SQL date formatting unless necessary; prefer portable query builder or collection
  grouping for testability.
- [x] Tests must create required model rows manually unless ERP factories are introduced in a
  separate explicit task.

## Task 6: Reporting Pages

**Files:**
- Create: `Modules/ERP/app/Filament/Pages/SalesPipelinePage.php`
- Create: `Modules/ERP/app/Filament/Pages/StockValuationPage.php`
- Create Blade views under `Modules/ERP/resources/views/filament/pages/`.

- [x] Resolve company context via `Modules\ERP\Helpers\current_company_id()` or explicit page
  filters. Do not assume a user-level current-company accessor exists.
- [x] Pages are read-only and must not create new tables.

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
- Operational `SalesPipelineService` and `StockValuationService` are implemented as read-only
  service-layer summaries with read-only Filament pages.
- Still deferred from the original expanded M4 idea: explicit Delivery Note post/unpost page
  actions, Fiscal Period close/reopen page actions, Journal Entry reversal page action, and the
  matching extra custom abilities/state-aware policy checks.
  **→ Tracked as Phase 2A in Spec 2 master backlog** (`2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md`).
- Verified on 2026-06-23:
  - `php artisan test --compact Modules/ERP/tests/Feature/OperationalReportingServicesTest.php`
  - `php artisan test --compact Modules/ERP/tests/Feature/Filament/ERPFilamentRouteSmokeTest.php`
  - `php artisan test --compact Modules/ERP/tests/Feature/FinancialStatementsTest.php Modules/ERP/tests/Feature/OperationalReportingServicesTest.php`
- Verified on 2026-05-29:
  - `php artisan test --compact Modules/ERP/tests/Integration/Services/Accounting/FiscalPeriodCloserTest.php`
  - `php artisan test --compact Modules/ERP/tests/Feature/FinancialStatementsTest.php`
  - `php artisan test --compact Modules/ERP/tests/Feature/Filament/ERPFilamentResourcesTest.php Modules/ERP/tests/Feature/Filament/ERPFilamentCommercialResourcesTest.php`
  - `php artisan migrate --pretend --no-interaction` -> `Nothing to migrate`
  - `vendor/bin/pint --dirty`
