# ERP Hardening — Bugs, Correctness & Money Math (Spec 1)

**Status:** Approved design, ready for implementation planning
**Date:** 2026-06-30
**Module:** `Modules/ERP`
**Scope:** P0 correctness bugs + P1 quality/money-math hardening of the existing v1.

---

## Context

The ERP module v1 milestones (M3.6, M4, M6.1, M6.2, M6.3, M7.1) and the accounting
golden-master suite are implemented. A code review against the original plans found that the
milestones are complete per their own checklists, but the module is not yet production-ready: it
contains real defects not documented as deferred, a cross-cutting money-math weakness, and
targeted test gaps.

This spec is the first of five review sub-projects. It covers only correctness and decimal
precision hardening. It explicitly excludes new features, deferred follow-ups, domain action
endpoints, and architectural expansions, which are owned by later specs:

- Spec 2: deferred follow-ups (Filament page actions, state-aware policies, Party price-rule UI,
  bank difference journals, automatic credit/debit notes).
- Spec 3: domain action endpoints (internal CRUD-style actions + opt-in external API).
- Spec 4 / Spec 5: architectural expansions (analytic dimensions, real multi-currency, Money value
  object, integration outbox/events).

## Goal

Make the existing accounting and operational flows correct and decimal-exact, with regression
tests proving each fix, without changing public behavior except where the current behavior is a
defect.

## Non-Goals

- No new Filament UI, pages, or actions.
- No new API/web routes.
- No automatic credit/debit note creation, no bank difference journals.
- No full `Money` value object (deferred to Spec 5); this spec introduces only a shared decimal
  helper based on the existing Brick Math pattern.
- No real multi-currency: `CurrencyConverter` stays no-op, posting keeps `fx_rate = 1`. An FX
  posting test is therefore not meaningful here and is deferred to Spec 5.

## Approach

- Test-first: every fix begins with a failing Pest test that reproduces the current behavior, then
  the fix makes it pass.
- Shared decimal helper: a single `Modules\ERP\Support\Decimal` API (wrapping
  `Brick\Math\BigDecimal`, scale 4, `RoundingMode::HALF_UP`) replaces the ad-hoc float helpers
  (`add`/`mul`/`neg`/`round4`) currently duplicated across services. `brick/math` is already
  present in the root lock file; no new dependency is added.
- Tax single source of truth: invoice posting and VAT register must compute line tax through
  `TaxLineCalculator` (already Brick-based) instead of duplicating float tax math.
- Golden master as guardrail: existing golden-master tests must stay green. If a golden value
  changes after the decimal refactor, that is a float defect being corrected, not a reason to relax
  assertions. A new fractional-quantity golden master is added to prove the refactor fixes a real
  discrepancy.
- Tests use Pest with `RefreshDatabase` and manual model setup (no ERP factories), consistent with
  the existing module convention.

---

## Fixes

Each fix lists the current behavior with file/line references, the target behavior, and the
required test.

### Fix 1 — E-invoice domain permissions are never seeded (P0)

**Current:** `Modules/ERP/database/seeders/ERPDatabaseSeeder.php` iterates class-strings and gates
the e-invoice abilities with `is_a()` called without the `allow_string` flag, which is always
false on a class-string:

- `domainPermissions()` (lines 224-246): `$sntities = [DeliveryNote::class, ...]`; `foreach
  ($sntities as $model)`; `if (is_a($model, Invoice::class))` (line 239) — `$model` is a
  class-string and the third `$allow_string` argument defaults to `false`, so `is_a()` expects an
  object and never matches. As a result `default.erp_invoices.submitEInvoice` and
  `default.erp_invoices.refreshEInvoice` are never created.
- Variable typo `$sntities`.
- `$model::flushEventListeners()` (line 234) is an unexpected side effect inside a permission-name
  builder.

**Effect:** Because `ERPModelPolicy::allows()` is fail-closed
(`Modules/ERP/app/Policies/ERPModelPolicy.php:81-83` returns `false` when the permission row does
not exist), the e-invoice actions in `EditInvoice` are denied to every non-superadmin user. The
feature is unusable rather than insecure.

**Target:**
- Add the missing `allow_string` flag: `is_a($model, Invoice::class, true)` (or
  `$model === Invoice::class`) so the e-invoice abilities are seeded.
- Rename `$sntities` to `$entities`.
- Remove the `flushEventListeners()` call or move it out of the permission-name builder with a
  documented reason; the seeder method should only build permission names.

**Test:** `Modules/ERP/tests/Feature/...` (permissions seeder feature test) asserts that after
running the seeder the permissions `default.erp_invoices.submitEInvoice` and
`default.erp_invoices.refreshEInvoice` exist, alongside the existing `post`/`unpost` permissions
for the five seeded models.

### Fix 2 — Authorization semantics locked by tests (P0)

**Current:** `ERPModelPolicy` is fail-closed and correct, but there is no authorization test
coverage anywhere in `Modules/ERP/tests/`. After Fix 1 the e-invoice abilities become usable; the
semantics must be pinned.

**Target:** No new policy logic. Add authorization tests covering, for the policy abilities
(`post`, `unpost`, `submitEInvoice`, `refreshEInvoice`):
- superadmin is always allowed;
- a user granted the specific permission is allowed;
- a user without the permission is denied;
- when the permission row is absent, access is denied (documents the fail-closed contract).

### Fix 3 — Invoice posting does not enforce closed fiscal periods (P0 correctness)

**Current:** `JournalPostingService::post()` only enforces the closed-period guard when a
`FiscalPeriod` is passed:

- `Modules/ERP/app/Services/Accounting/JournalPostingService.php:42-66` signature has
  `?FiscalPeriod $fiscal_period = null`.
- `validateLinesForPosting()` (lines 210-237) throws `PostingToClosedFiscalPeriodException` only
  inside `if ($fiscal_period instanceof FiscalPeriod)` (lines 218-221).
- `InvoicePostingService::post()` calls the journal service with `null` as the fiscal period
  (`Modules/ERP/app/Services/Accounting/InvoicePostingService.php:95-104`), so posting an invoice
  into a closed period bypasses the guard. The closure guard is only exercised today via direct
  `JournalPostingService` tests (`AccountingSequencesAndPostingTest`).

**Target:** In `InvoicePostingService::post()`, resolve the company's `FiscalPeriod` covering the
invoice `posted_at` date and pass it to `JournalPostingService::post()`, so a closed period blocks
invoice posting through the existing guard. Define behavior when no fiscal period exists for the
date (align with `VatRegisterService`, which already throws when the fiscal year is missing): fail
with a clear validation error rather than posting unguarded.

**Test:** posting a sale invoice with `posted_at` inside a closed period throws; posting inside an
open period succeeds and links the journal to that period.

### Fix 4 — Bank suggestion/match amount tolerance mismatch (P0)

**Current:** `Modules/ERP/app/Services/Banking/BankReconciliationService.php`:
- `suggestPayments()` filters candidate payments within a EUR 1.00 window
  (`whereBetween('amount_doc', [expected - 1, expected + 1])`, lines 84-87).
- `assertCanMatch()` requires an exact amount within 0.0001
  (`abs(abs($line_amount) - $payment_amount) > 0.0001` throws, lines 135-143).

A payment suggested within the EUR 1.00 window but not exactly equal cannot actually be matched.

**Target:** Introduce a single private predicate (e.g. `amountsMatch(BankStatementLine, Payment)`)
used by both `suggestPayments()` and `assertCanMatch()` so suggestion and match always agree.
Default rule stays exact (0.0001) to keep accounting safe. Near-but-not-equal amounts may still be
surfaced as non-matchable "near" hints, but must never be presented as directly matchable.
Difference journals remain out of scope (Spec 3).

**Test:** a payment within EUR 1.00 but not exact is not returned as a matchable suggestion (or is
clearly flagged non-matchable), and `matchPayment()` and `suggestPayments()` agree on the same
payment set for exact amounts.

### Fix 5 — Idempotency guard on return completion (P0 hardening)

**Current:** `CustomerReturnReceiptService::receive()` and
`SupplierReturnShipmentService::ship()` are guarded by `status === Approved` and set `Processed` at
the end, so normal double-processing is blocked. However, if `delivery_note_id` is already set
while the status is still `Approved`, `deliveryNoteFor()` reuses the existing delivery note
(`CustomerReturnReceiptService.php:61-68`, `SupplierReturnShipmentService.php:61-68`) while
`registerSourceReturnedQuantities()` (called at lines 50) increments `qty_returned` again. This can
double-count returned quantities on the source lines.

**Target:** Add an idempotency guard so a return that already has a generated/linked delivery note
does not re-run source returned-quantity registration. Stock posting is already idempotent (the
service only sets `posted_at` when it is null), so the fix targets quantity double-counting.

**Note (out of scope, routed to Spec 3):** there is no path to reverse a return after it reaches
`Processed` (cancel is blocked for `Processed`, and the generated DDT and `qty_returned` are not
rolled back). A "revert processed return" workflow is a design gap to be handled in Spec 3, not
here.

**Test:** invoking completion twice on a return whose delivery note is already linked does not
double-increment `qty_returned` on the source invoice/sales-order or purchase-order/goods-receipt
lines, and does not double-post stock.

### Fix 6 — Decimal-exact money math (P1)

**Current:** float-based money math, with duplicated tax logic:
- `InvoicePostingService`: `round4`/`add`/`mul`/`neg`/`asFloat` (lines 375-403); per-line tax via
  `((float) $line_net * (float) $tax_code->rate) / 100` (line 163).
- `VatRegisterService`: `round4`/`add`/`neg` (lines 108-121); per-line tax recomputed
  independently with floats (lines 60-61) — duplicates the invoice-posting tax logic.
- `VatSettlementService`: `round4` (lines 109-111); float casts on aggregates (lines 66, 84-85).
- `PriceResolverService::applyRule()`: discount math on floats (lines 114-129).
- `ThreeWayMatchService::percentDiff()`: float percentage math (lines 117-124).
- Brick Math is used today only in `TaxLineCalculator` (`multipliedBy`/`dividedBy`, scale 4,
  `HALF_UP`).

**Target:**
- Add `Modules\ERP\Support\Decimal` exposing `add`, `sub`, `mul`, `div`, `negate`, and a 4-decimal
  string formatter, all backed by `Brick\Math\BigDecimal` (scale 4, `HALF_UP`).
- Replace the float helpers in `InvoicePostingService`, `VatRegisterService`,
  `VatSettlementService`, and `PriceResolverService::applyRule()` with `Decimal`.
- Route invoice-posting and VAT-register per-line tax through `TaxLineCalculator` to remove the
  duplicated tax computation and guarantee both paths produce identical amounts.
- `ThreeWayMatchService::percentDiff()` may move to `Decimal` for consistency; tolerance comparison
  remains percentage-based.

**Test:**
- All existing golden masters (`AccountingGoldenMasterTest`, `InventoryAccountingGoldenMasterTest`)
  stay green.
- A new golden-master case with fractional quantities and a rate that produces float drift (for
  example repeated `0.1`-style quantities) asserts exact 4-decimal signed journal, VAT register,
  and settlement values that the current float pipeline gets wrong.

### Fix 7 — Targeted test gaps (P1)

**Three-way match coverage:**
- `ThreeWayMatchService` is exercised today only on `PurchaseOrderLine` (matched/throw/forced, 3
  PO-only cases). Add tests for the `GoodsReceiptLine` quantity comparison
  (`goods_receipt_line_id`).
- Add a test documenting that a purchase invoice with no PO/GR links posts with
  `MatchStatus::Unmatched` and does not block posting.

**Bank CSV row validation:**
- The CSV importer currently validates only duplicate headers, not per-row content. Add per-row
  validation that rejects rows missing date/description/amount with a `ValidationException`, and a
  test for malformed/empty rows.

---

## Testing Strategy

- Pest feature tests under `Modules/ERP/tests/Feature` (and `tests/Feature/Services` for service
  tests), `RefreshDatabase`, manual model setup, no ERP factories.
- Run the narrowest relevant test files per fix, then the full accounting golden-master suite as a
  regression gate.
- `vendor/bin/pint --dirty` before completion.
- Golden-master assertions are never relaxed; a changed golden value is investigated as a float
  defect.

## Risks and Mitigations

- The decimal refactor touches the accounting core. Mitigation: test-first, existing golden masters
  as the baseline, and incremental per-service replacement rather than a single sweep.
- Fix 3 changes invoice posting to require a fiscal period for the posted date. Mitigation: define
  and test the missing-period behavior explicitly (fail with a clear validation error), mirroring
  the existing `VatRegisterService` fiscal-year guard.
- Fixes 1 and 2 alter who can run e-invoice actions. Mitigation: explicit authorization tests pin
  the new, intended behavior.

## Out of Scope (routed to later specs)

- Filament page actions, state-aware policies, extra seeded abilities (`force_post`, `close`,
  `reopen`, `reverse`, `amend`, `unlock`), Party price-rule UI, bank difference journals, automatic
  NC/ND creation → Spec 2.
- Revert/reverse of a processed return; internal action endpoints and opt-in external API → Spec 3.
- Analytic dimensions, real multi-currency and FX revaluation, full `Money` value object,
  integration outbox/events → Spec 4 / Spec 5.
