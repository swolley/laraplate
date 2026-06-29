# ERP Hardening — Bugs, Correctness & Money Math (Spec 1)

**Status:** Approved design, ready for implementation planning
**Date:** 2026-06-30
**Module:** `Modules/ERP`
**Scope:** P0 correctness bugs + P1 quality/money-math hardening of the existing v1, plus a minimal
Core integrity guard against generic CRUD writes on immutable/derived models.

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
- Spec 3: domain action endpoints (internal CRUD-style actions + opt-in external API), and the full
  per-model API/CRUD exposure governance (settings-driven toggles extending `core.expose_crud_api`,
  model-property hard override, route/middleware-level gating that stays CMS-delegation-safe). Spec 1
  ships only the minimal write-integrity guard (Fix 6) as a forerunner.
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
- Tax single source of truth: invoice posting and VAT register compute per-line tax through one
  shared decimal method (a `lineTax(net, rate)` helper added to `TaxLineCalculator`, already
  Brick-based) instead of duplicating float tax math. The formula is identical on both paths and
  takes a net amount + rate, so it needs neither a `TaxKind` check nor an extra `TaxCode` lookup
  (kept low-risk and behavior-preserving).
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
invoice `posted_at` date (join `FiscalYear` on `company_id` + `year`, then match
`start_date <= posted_at <= end_date`) and pass it to `JournalPostingService::post()`, so a closed
covering period blocks invoice posting through the existing guard. **Non-breaking behavior:** when no
fiscal period covers the date, pass `null` (current behavior) — posting is not blocked. The guard
fires only when a covering period exists **and** `is_closed === true`. This mirrors
`JournalPostingService`, which already no-ops the guard when no period is provided, and avoids
breaking the existing posting suite, whose fixtures create a `FiscalYear` but no `FiscalPeriod`
rows.

**Test:** posting a sale invoice whose `posted_at` falls inside a **closed** covering period throws
`PostingToClosedFiscalPeriodException`; posting inside an **open** covering period succeeds and links
the journal to that period; posting with no covering period (existing fixtures) still succeeds.

### Considered and dropped (verified against current code/tests)

- **Bank suggestion/match tolerance** — the EUR ±1 fuzzy window in `suggestPayments()` is
  intentional and pinned by `BankReconciliationServiceTest` ("suggests compatible payments…", which
  asserts a `100.5000` near payment is suggested for a `100.0000` line). Suggestions are fuzzy
  candidates for human review; `matchPayment()` stays strict (0.0001). This is by design, not a P0
  bug; any UX refinement is deferred to Spec 2/3.
- **Return completion idempotency** — the normal flow is already guarded and tested:
  `ReturnOrderServiceTest` ("prevents completing the same customer return twice") asserts a second
  `complete()` throws (`Approved → Processed → re-complete throws`). The only double-count path
  requires a pre-set `delivery_note_id` while status is still `Approved`, which is not reachable in
  the normal flow. No change needed in Spec 1.

### Fix 4 — Decimal-exact money math (P1)

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

### Fix 5 — Targeted test gaps (P1)

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

### Fix 6 — Block generic CRUD writes on immutable/derived models (P0 integrity)

**Current:** the Core dynamic CRUD has no structural model-exposure control. `CrudRequest`
resolves any model by name via `DynamicEntity::resolve()` →
`DynamicEntityService::resolve()`/`tryResolveModel()` (`Modules/Core/app/Models/DynamicEntity.php`),
with no allow/deny list, and `CrudService::insert/update/delete`
(`Modules/Core/app/Services/Crud/CrudService.php:338-410`) then operates on it. The only gate is the
per-model permission check, which is fail-closed for normal users but bypassed by superadmin.

`JournalEntry` blocks `update`/`delete` only once posted, via model events, but does **not** guard
creation and even exposes CRUD `create`/`update` rule sets:

- `Modules/ERP/app/Models/JournalEntry.php:143-168` (`booted()` blocks update/delete post-posting).
- `Modules/ERP/app/Models/JournalEntry.php:115-141` (`getRules()` exposes `create`/`update`).

So `POST insert/erp/journal-entries` (with the permission, or as superadmin) can create an
unbalanced voucher, bypassing every `JournalPostingService` invariant (double-entry balance,
sequencing, fiscal period). The same risk applies to derived/immutable tables produced only by
services: `journal_entry_lines`, `vat_register_entries`, `stock_movements`, `stock_cost_layers`,
`stock_levels`.

**Target (minimal forerunner of the Spec 3 exposure system):**
- Add a minimal Core contract `Modules\Core\Contracts\RestrictsCrudWrites` exposing the denied write
  operations for a model (subset of `insert`/`update`/`delete`/`forceDelete`).
- In `CrudService::insert/update/delete`, when the resolved model implements the contract and the
  operation is denied, throw a dedicated exception mapped to HTTP 403 in
  `CrudController::handleServiceCall()`. The guard applies to everyone, superadmin included.
- The ERP immutable/derived models implement the contract to deny direct CRUD writes. Service paths
  (`JournalPostingService`, inventory services, VAT services) do not go through `CrudService`, so
  they are unaffected. Existing `JournalEntry` model-event guards stay as defense-in-depth.

**Constraint (CMS delegation):** the exposure governance must not break specialized controllers that
reuse the generic CRUD logic in-process. `Modules/Cms/app/Http/Controllers/ContentsController.php`
extends `CrudController` and calls `$this->list()` for `contents`. Fix 8 only guards **write**
operations (`insert`/`update`/`delete`), which CMS does not reuse, so it is safe. The broader
read/write per-model exposure toggles are intentionally deferred to Spec 3, where they must be gated
at the route/middleware layer to remain CMS-safe.

**Test:**
- `insert`/`update`/`delete` via the generic CRUD on each restricted model returns 403 and persists
  no change, even as superadmin.
- Service-layer creation still works: the accounting golden masters stay green.
- A non-restricted ERP model (e.g. a draft invoice) still inserts/updates normally via CRUD.

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
- Fix 3 resolves a covering fiscal period for the posted date. Mitigation: it is non-breaking — the
  guard only fires when a covering period exists and is closed; with no covering period posting is
  unchanged, so the existing posting suite (FiscalYear-only fixtures) stays green.
- Fixes 1 and 2 alter who can run e-invoice actions. Mitigation: explicit authorization tests pin
  the new, intended behavior.
- Fix 6 introduces a small Core contract and a write guard in `CrudService`, touching Core beyond
  ERP. Mitigation: keep the contract minimal (write-only), default to no restriction when the
  contract is absent (existing models behave unchanged), and cover with tests that non-restricted
  entities and CMS read delegation are unaffected.

## Out of Scope (routed to later specs)

- Filament page actions, state-aware policies, extra seeded abilities (`force_post`, `close`,
  `reopen`, `reverse`, `amend`, `unlock`), Party price-rule UI, bank difference journals, automatic
  NC/ND creation → Spec 2.
- Revert/reverse of a processed return; internal action endpoints and opt-in external API; deeper
  order-safe ERP domain-action routes (`.../{id}/{action}`); full per-model API/CRUD exposure
  governance (settings + model-property override + CMS-safe route/middleware gating) → Spec 3.
- Analytic dimensions, real multi-currency and FX revaluation, full `Money` value object,
  integration outbox/events → Spec 4 / Spec 5.
