# ERP Remaining Work — Spec 2 (Master Backlog)

**Status:** Approved design; **Phase 2A** ready for implementation planning  
**Date:** 2026-06-30 (backlog consolidated 2026-06-30; completion audit 2026-06-30)  
**Module:** `Modules/ERP`  
**Depends on:** Spec 1 implemented + patch (`971851d` ERP, `15b11c8` Core)

> **Single source of truth** for ERP work tracking. Completed items are listed in **§ Completed**
> below; only **§ Open** requires implementation. Verified against codebase + `299` ERP tests pass.

---

## Completion summary (2026-06-30 audit)

| Bucket | Count | Notes |
|--------|------:|-------|
| **Done** | **62** | Core M0–M7 v1 + Spec 1 + M4 reporting slice — § Completed |
| **Partial → closed by Phase 2A** | **4** | PART-01…PART-04 — backend/skeleton exists; 2A finishes wiring |
| **Partial → Phase 2B** | **1** | PART-05 / 2B-11 — `FinancialReportCsvExporter` exists; Filament export UI missing |
| **Open backlog rows** | **51** | § Open — **9 in Phase 2A** (next) + **42 in Phases 2B–5** |

**How to read the backlog**

- **51 open** = every row in § Open with status `open` (or `partial` for 2B-11 only).
- **4 partial** (PART-01…04) are **not** extra open rows; they explain what Phase 2A completes on top of existing code.
- **Do not double-count:** PART-05 and 2B-11 are the same item.

**Next target:** Phase 2A — 9 open items (`2A-01`…`2A-09`). Implementation plan:
[`plans/2026-06-30-erp-hardening-spec2-phase2a.md`](../plans/2026-06-30-erp-hardening-spec2-phase2a.md).

**Then:** Phase 2B — 12 items (`2B-01`…`2B-12`). Plan:
[`plans/2026-06-30-erp-hardening-spec2-phase2b.md`](../plans/2026-06-30-erp-hardening-spec2-phase2b.md).

**Finally:** Phases 2C–5 — 30 items (`2C-01`…`5-06`). Plan:
[`plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md`](../plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md).

---

## Document map

| Source | Role today |
|--------|------------|
| `.cursor/plans/nebula_verso_business_0d6eb0ed.plan.md` | Historical M0–M7 roadmap; open items → § Open |
| `specs/2026-06-30-erp-hardening-bugs-money-math-design.md` | Spec 1 — **done** |
| `plans/2026-06-30-erp-hardening-spec1.md` | Spec 1 plan — **done** |
| Milestone plans M3.6–M7.1 | v1 **done**; follow-ups → § Open |

### Roadmap phases

| Phase | Scope | Status |
|-------|--------|--------|
| Spec 1 | Bug + money math + CRUD guard | **Done** |
| Spec 2 Phase 2A | Filament domain actions + state-aware policies | **Open (next)** |
| Spec 2 Phase 2B–2C | Party UI, bank journals, auto NC/ND, FatturaPA | Open |
| Phase 3 | Domain HTTP actions + API exposure | Open |
| Phase 4–5 | Tricount refactor, FX, Money VO, dimensions | Open |

---

## Completed (done — do not re-open without new finding)

Status verified in `Modules/ERP` unless noted.

### Spec 1 & Core

| ID | Item | Evidence |
|----|------|----------|
| DONE-S1 | Spec 1 P0/P1 fixes (seeder, policy tests, fiscal period guard, decimal money math, test gaps, CRUD guard) | ERP `9297a7c…971851d`, Core `354299c`, `15b11c8` |
| DONE-S1P | Post-review patch: CSV dates, `Decimal::div` guard, CRUD HTTP tests, `module=erp` alias | `971851d`, `15b11c8` |

### Nebula / M0 — Foundations

| ID | Item | Evidence |
|----|------|----------|
| DONE-M0-01 | Multi-company + `BelongsToCompany` + dual-currency columns | `Company` model, migrations |
| DONE-M0-02 | `VersionStrategy::DIFF` on accounting models | Model properties + tests |
| DONE-M0-03 | P0 Place + P1 Taxonomy (Core/CMS) | nebula todos completed |

### M1 — Accounting core

| ID | Item | Evidence |
|----|------|----------|
| DONE-M1-01 | Chart of accounts + `ItalianCoaProvider` | `ChartOfAccountsInstaller`, `Account` |
| DONE-M1-02 | `JournalPostingService` post/reverse + immutability | Service + golden master |
| DONE-M1-03 | Fiscal years/periods + `FiscalPeriodCloser` service | Service + integration tests |
| DONE-M1-04 | Document sequences + `DocumentNumberAllocator` | Service + tests |

### M2 — Tax

| ID | Item | Evidence |
|----|------|----------|
| DONE-M2-01 | `TaxCode`, `TaxLineCalculator`, supersession | Models + services |
| DONE-M2-02 | Invoice/invoice_line stub + posting integration | M3.5 |

### M3 — Commercial & inventory

| ID | Item | Evidence |
|----|------|----------|
| DONE-M3-01 | CRM leads/opportunities + lifecycle | Resources + services |
| DONE-M3-02 | Sales orders + lock-chain + evasion + `SalesOrderAmendmentService` | Service exists (UI amend → 2A) |
| DONE-M3-03 | Inventory: movements, cost layers, `StockMovementService` | M3.3 |
| DONE-M3-04 | DDT outbound/inbound + COGS journal + unpost | `DeliveryNoteInventoryService`, observer |
| DONE-M3-05 | Invoice posting/unposting (sale + purchase) | `InvoicePostingService` |
| DONE-M3-06 | Purchase cycle: PO/GR/parties/3-way match | `ThreeWayMatchService`, tests |
| DONE-M3-07 | MVP pre-contabilità (quotations, projects, tasks, time entries) | nebula todo completed |
| DONE-M3-08 | Party type validation (customer/supplier) on models + Filament | D6 |

### M4 — Policies & reporting (v1 slice)

| ID | Item | Evidence |
|----|------|----------|
| DONE-M4-01 | `ERPModelPolicy` + Gate registration (permission-only) | Policy + `ERPServiceProvider` |
| DONE-M4-02 | Seed domain permissions: `post`, `unpost`, `submitEInvoice`, `refreshEInvoice` | `ERPDatabaseSeeder` |
| DONE-M4-03 | Invoice Filament post/unpost + e-invoice actions | `InvoicePostingActions`, `EditInvoice` |
| DONE-M4-04 | `SalesPipelineService` + `StockValuationService` + Filament pages | M4 Task 5–6 |
| DONE-M4-05 | Financial statements: trial balance, balance sheet, income statement | M5.4 services + pages |
| DONE-M4-06 | `FiscalPeriodCloser::reopenPeriod()` service | Integration test |

### M5 — Payments & fiscal reporting

| ID | Item | Evidence |
|----|------|----------|
| DONE-M5-01 | Payment terms, schedule, allocations, aging | M5.1 services + resources |
| DONE-M5-02 | Credit/debit notes + `CreditNoteService` | M5.2 + Filament manual actions |
| DONE-M5-03 | VAT register + settlement | `VatRegisterService`, `VatSettlementService` |
| DONE-M5-04 | Financial statement pages + tests | `FinancialStatementsTest` |

### M6 — Banking, returns, e-invoice (v1)

| ID | Item | Evidence |
|----|------|----------|
| DONE-M6-01 | Bank CSV import + manual match + suggestions + reco page | M6.1 |
| DONE-M6-02 | Returns approve/complete/cancel + DDT linking + qty tracking | M6.2 |
| DONE-M6-03 | Manual NC/ND Filament actions on returns/invoices | `EditReturnOrder`, `EditSupplierReturn`, `EditInvoice` |
| DONE-M6-04 | E-invoice stub provider + submission + Filament submit/refresh | `StubEInvoiceProvider`, `EInvoiceSubmissionService` |

### M7 — Pricing (backend)

| ID | Item | Evidence |
|----|------|----------|
| DONE-M7-01 | `PartyPriceRule` model + migrations | M7.1 |
| DONE-M7-02 | `PriceResolverService` + cascade/temporal rules | Tests |
| DONE-M7-03 | Line pricing on quotation/SO/invoice | `InvoiceLinePricingService` |

### Testing & hardening

| ID | Item | Evidence |
|----|------|----------|
| DONE-T-01 | Accounting golden master suite | `AccountingGoldenMasterTest` |
| DONE-T-02 | Inventory/accounting integration golden cases | Plan follow-up done |
| DONE-T-03 | VAT settlement confirmation guards | Golden master extension |
| DONE-T-04 | ERP feature suite green | `299 passed, 1 skipped` |

---

## Partially complete (Phase 2A finishes these)

| ID | Done today | Still open (→ Phase 2A ID) |
|----|------------|----------------------------|
| PART-01 | `ERPModelPolicy` + `close`/`reopen`/`post`/`unpost` methods | State guards + `reverse`/`amend`/`forcePost` → **2A-02** |
| PART-02 | Seeded `post`/`unpost`/e-invoice permissions | Seed `close`/`reopen`/`reverse`/`amend`/`force_post` → **2A-01** |
| PART-03 | Force 3-way match checkbox on invoice post | Dedicated `force_post` permission gate → **2A-03** |
| PART-04 | `FiscalPeriodCloser`, `JournalPostingService::reverse`, `SalesOrderAmendmentService`, DDT observer | Filament actions + form hardening → **2A-04…2A-08** |
| PART-05 | `FinancialReportCsvExporter` service + tests | Filament export buttons / PDF → **2B-11** |

---

## Open (remaining work)

Status: `open` · `next` = current sprint target

### Phase 2A — Filament domain actions & state-aware policies (**next**)

| ID | Status | Item |
|----|--------|------|
| 2A-01 | open | Seed abilities: `close`, `reopen`, `reverse`, `amend`, `force_post` |
| 2A-02 | open | State-aware `ERPModelPolicy` (state before permission; superadmin bypasses permission only) |
| 2A-03 | open | `force_post` gate on purchase invoice post checkbox |
| 2A-04 | open | DDT post/unpost Filament actions (`posted_at` + observer) |
| 2A-05 | open | Fiscal period close/reopen → `FiscalPeriodCloser`; read-only `is_closed` on form |
| 2A-06 | open | Fiscal year close → `closeYear()`; register `FiscalYear` on policy map |
| 2A-07 | open | Journal reverse → `JournalPostingService::reverse()` on `ViewJournalEntry` |
| 2A-08 | open | Sales order amend → `SalesOrderAmendmentService` + redirect |
| 2A-09 | open | Policy + seeder + action wiring tests (TDD) |

**Deferred to 2B:** quotation `unlock`, document sequence `reset`.

### Phase 2B — Commercial & banking UX

> Implementation plan: [`plans/2026-06-30-erp-hardening-spec2-phase2b.md`](../plans/2026-06-30-erp-hardening-spec2-phase2b.md) (5 waves, 12 tasks + optional PriceList resources).

| ID | Status | Item |
|----|--------|------|
| 2B-01 | open | Party price-rule UI |
| 2B-02 | open | `Party::price_rules()` relation if missing |
| 2B-03 | open | Optional `PriceList` / `PriceListItem` Filament resources |
| 2B-04 | open | Bank difference journals (fees, rounding) |
| 2B-05 | open | Match-with-difference on reco page |
| 2B-06 | open | Automatic NC/ND on return `complete()` |
| 2B-07 | open | Return line override contract for auto NC/ND |
| 2B-08 | open | Quotation `unlock` action + policy + seed |
| 2B-09 | open | Document sequence `reset` action + policy + seed |
| 2B-10 | open | CAMT.053 / MT940 import |
| 2B-11 | partial | Financial report export CSV/PDF from Filament (service exists) |
| 2B-12 | open | BI / operational dashboard polish |

### Phase 2C — E-invoice & extended permissions

> Plan: [`plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md`](../plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md) — Wave 2 (Tasks 3–7).

| ID | Status | Item |
|----|--------|------|
| 2C-01 | open | Full FatturaPA XML + XSD validation |
| 2C-02 | open | Complete SDI party/company mapping |
| 2C-03 | open | Production provider (e.g. Aruba) |
| 2C-04 | open | Extended admin policies (tax codes, company switch, sequences) |
| 2C-05 | open | FatturaPA schema columns if needed |

### Phase 3 — Domain HTTP actions & API exposure

> Plan: [`plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md`](../plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md) — Waves 1 + 3 (Tasks 1–2, 8–11). Spine of remaining work.

| ID | Status | Item |
|----|--------|------|
| 3-01 | open | Internal domain-action routes `.../{id}/{action}` |
| 3-02 | open | Opt-in external API + versioning |
| 3-03 | open | Per-model CRUD/API exposure governance |
| 3-04 | open | Centralize permission-name construction (explicit `$connection`) |
| 3-05 | open | Revert/reverse processed return |
| 3-06 | open | HTTP tests for domain actions |

### Phase 4 — Cash / Tricount & commercial depth

> Plan: [`plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md`](../plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md) — Wave 4 (Tasks 12–22).

| ID | Status | Item |
|----|--------|------|
| 4-01 | open | `Movement` → `JournalEntry` refactor |
| 4-02 | open | Tricount UX on journal-only writes |
| 4-03 | open | Quote revisions + project bind locks |
| 4-04 | open | DB lock-chain triggers (D5) |
| 4-05 | open | Settlements / pool / settle-up |
| 4-06 | open | PaymentRequest stub + providers |
| 4-07 | open | Calendar ICS export |
| 4-08 | open | Gantt planning entity (optional) |
| 4-09 | open | ETL Symfony legacy |
| 4-10 | open | ERP `sites.place_id` + ICS |
| 4-11 | open | Hide version-strategy settings for DIFF models |
| 4-12 | open | Concurrency stress tests (50 workers) |
| 4-13 | open | API mobile (optional) |

### Phase 5 — Architecture

> Plan: [`plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md`](../plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md) — Wave 5 (Tasks 23–28).

| ID | Status | Item |
|----|--------|------|
| 5-01 | open | Real multi-currency + FX + revaluation |
| 5-02 | open | Full `Money` value object |
| 5-03 | open | Analytic dimensions on journal lines |
| 5-04 | open | Integration outbox / domain events |
| 5-05 | open | Direct item-specific price lists |
| 5-06 | open | ERP vision meta / pluggable narrative |

---

## Phase 2A — detailed design (implementation target)

Backend services exist; gap = **Filament actions**, **permissions**, **state-aware policy**, **form hardening**.

### Action classes to create

| Class | Page | Service |
|-------|------|---------|
| `FiscalPeriodActions` | `EditFiscalPeriod` | `FiscalPeriodCloser` |
| `FiscalYearActions` | `EditFiscalYear` | `FiscalPeriodCloser::closeYear()` |
| `JournalEntryActions` | `ViewJournalEntry` | `JournalPostingService::reverse()` |
| `DeliveryNotePostingActions` | `EditDeliveryNote` | `posted_at` + observer |
| `SalesOrderAmendmentActions` | `EditSalesOrder` | `SalesOrderAmendmentService` |

### Implementation order

1. Permissions seeder + test  
2. Policy state guards + tests  
3. `force_post` on invoice actions  
4. Fiscal period/year + form hardening  
5. DDT post/unpost  
6. Journal reverse  
7. Sales order amend  
8. Filament smoke + ERP test subset  

---

## Maintenance rule

When closing an item: move it from § Open to § Completed with evidence (commit/test). Partial items
move to § Partial or split into done + remaining open IDs.
