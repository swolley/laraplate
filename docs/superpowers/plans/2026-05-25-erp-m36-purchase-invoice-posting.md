# ERP M3.6 - Purchase Invoice Posting Cleanup Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> or `superpowers:executing-plans`. This is a cleanup/verification plan, not a greenfield
> implementation.

**Goal:** Verify and document the existing purchase invoice posting behavior, then fill only
missing coverage or naming gaps.

**Architecture:** `InvoicePostingService` already posts purchase invoices. It allocates
`DocumentType::PurchaseInvoice`, applies `ThreeWayMatchService`, builds signed journal lines,
registers VAT, and generates payment schedules. Work must preserve this flow.

**Tech Stack:** PHP 8.5, Laravel 12, Pest, Eloquent, `Modules/ERP`.

---

## Current Truth

- `InvoicePostingService` lives at `Modules/ERP/app/Services/Accounting/InvoicePostingService.php`.
- `ThreeWayMatchService` lives at `Modules/ERP/app/Services/Purchasing/ThreeWayMatchService.php`.
- `MatchStatus` lives at `Modules/ERP/app/Casts/MatchStatus.php`.
- `MatchStatus` cases are `Matched`, `Tolerance`, `Forced`, `Unmatched`; there is no `Exceeded`.
- Purchase journal account roles are `purchase_expense`, `vat_input`, and `trade_payable`.
- Per-company 3-way match settings are resolved by `ErpCompanySettings`, not directly from
  `config('erp.three_way_match.*')`.
- Existing focused tests pass with:

```bash
php artisan test --compact Modules/ERP/tests/Feature/ThreeWayMatchServiceTest.php Modules/ERP/tests/Feature/InvoicePostingServiceTest.php
```

## Task 1: Verify Existing Coverage

**Files:**
- Inspect: `Modules/ERP/tests/Feature/ThreeWayMatchServiceTest.php`
- Inspect: `Modules/ERP/tests/Feature/InvoicePostingServiceTest.php`

- [x] Run the focused command above and confirm the current baseline.
- [x] Confirm purchase invoice posting coverage includes:
  - purchase document sequence allocation;
  - 3-way match matched/tolerance/forced/failure behavior;
  - purchase journal lines with expense, VAT input, and trade payable;
  - unpost reversal and reference clearing.
- [x] No missing behavior was identified during the focused verification; no extra test was needed
  beyond the existing focused coverage.

## Task 2: Remove Stale Planning Assumptions

**Files:**
- Modify only docs or tests that still reference stale M3.6 names.

- [x] Replace the old accounts-payable role name with `trade_payable` when referring to current code.
- [x] Replace the old plural purchases-expense role name with `purchase_expense`.
- [x] Remove stale exceeded-status references; hard failures are `ValidationException`,
  while forced breaches return `MatchStatus::Forced`.
- [x] Do not add `ThreeWayMatchException` unless a separate refactor is approved.
- [x] Do not introduce `MatchResult` unless a separate service API refactor is approved.

## Task 3: Optional Code Cleanup Only If Tests Expose A Gap

**Files:**
- Potentially modify: `InvoicePostingService.php`
- Potentially modify: `ThreeWayMatchService.php`
- Potentially modify: focused tests in `Modules/ERP/tests/Feature`

- [x] Keep current public method signatures unless a failing test proves an API issue.
- [x] Preserve account-role names already seeded by `ItalianCoaProvider`.
- [x] Keep `InvoicePostingActions::postInvoice()` as the Filament-facing entry point for forced
  three-way match posting.

## Verification

Run:

```bash
php artisan test --compact Modules/ERP/tests/Feature/ThreeWayMatchServiceTest.php Modules/ERP/tests/Feature/InvoicePostingServiceTest.php
vendor/bin/pint --dirty
```

Expected result: focused tests pass; Pint has no unrelated changes.

## Implementation Status / Verification

- M3.6 remains a cleanup/verification milestone; no greenfield purchase-posting implementation was
  added.
- Verified on 2026-05-29:
  - `php artisan test --compact Modules/ERP/tests/Feature/ThreeWayMatchServiceTest.php Modules/ERP/tests/Feature/InvoicePostingServiceTest.php`
  - `vendor/bin/pint --dirty`

## Assumptions

- The current M3.6 implementation is the source of truth.
- This plan intentionally avoids creating new domain abstractions for 3-way match.
- Any future accounting role rename requires a separate migration/refactor plan.
