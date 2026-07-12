# ERP Remaining Work — Spec 2 (Master Backlog)

**Status:** Approved design; **Phase 2A completed**; **Phase 2B completed**; **Phase 2C active**

**Date:** 2026-06-30 (backlog consolidated 2026-06-30; completion audit updated 2026-07-11)

**Module:** `Modules/ERP`

**Depends on:** Spec 1 implemented + patch (`971851d` ERP, `15b11c8` Core)

> **Single source of truth** for ERP work tracking. Completed items are listed in **§ Completed**
> below; only **§ Open** requires implementation. The original audit was verified against the codebase,
> with `299` ERP tests passing; Phase 2A closure evidence is listed in § Completed.

---

## Completion summary (2026-06-30 audit)

| Bucket | Count | Notes |
|--------|------:|-------|
| **Done** | **86** | Core M0–M7 v1 + Spec 1 + M4 reporting slice + Spec 2 Phase 2A + Phase 2B Wave A + optional price-list resources + Wave B admin actions + Wave C return automation + supplier payment runs + bank difference reconciliation + CAMT/MT940 import + financial CSV export UI + operational dashboard polish + FatturaPA readiness schema + FatturaPA mapper — § Completed |
| **Partial remaining** | **0** | No partial ERP backlog rows remain in this master backlog |
| **Open backlog rows** | **28** | § Open — **0 in Phase 2B** + **28 in Phases 2C–5** |

**How to read the backlog**

- **28 open** = every row in § Open with status `open` or `next`.
- PART-01…PART-04 were closed by Phase 2A and are tracked in § Completed.
- PART-05 / 2B-11 is closed for CSV export UI. PDF export remains explicitly out of Phase 2B scope unless promoted by a new requirement.

**Current target:** Phase 2C — FatturaPA / SDI production readiness. Completed Phase 2B plan:
[`plans/2026-06-30-erp-hardening-spec2-phase2b.md`](../plans/2026-06-30-erp-hardening-spec2-phase2b.md).

**Next:** Continue Phase 2C, then Phases 3–5 — 28 open items (`2C-01`…`5-06`, excluding completed `2C-05` and `2C-02`). Plan:
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
| Spec 2 Phase 2A | Filament domain actions + state-aware policies | **Done** |
| Spec 2 Phase 2B | Party UI, bank journals, auto NC/ND, payment runs, reporting polish | **Done** |
| Spec 2 Phase 2C | FatturaPA and extended permissions | **Active** |
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

### Spec 2 Phase 2A — Filament domain actions

| ID | Item | Evidence |
|----|------|----------|
| DONE-S2-2A | Seeded domain abilities, state-aware `ERPModelPolicy`, gated `force_post`, DDT/fiscal period/fiscal year/journal/sales order Filament actions, and focused regression tests | ERP `300f9ef`; targeted ERP subset: `38 passed, 86 assertions` |
| DONE-S2-2B-A | Party `price_rules()` relation, concrete `PartyPriceRule::activity()` relation, and Party Filament price-rule relation manager | Working tree; `PartyPriceRuleTest`, `ERPFilamentCommercialResourcesTest`, `PriceResolverServiceTest` |
| DONE-S2-2B-03 | Optional `PriceList` Filament resource with nested `PriceListItem` repeater | ERP `169bdbd`; `ERPFilamentCommercialResourcesTest`, `ERPFilamentResourcesTest` |
| DONE-S2-2B-08 | Quotation `unlock` permission, state-aware policy, and Filament edit-page action | ERP `169bdbd`; `ErpQuotationUnlockTest`, `ErpDomainActionsSmokeTest` |
| DONE-S2-2B-09 | Document sequence reset service, permission, policy, and Filament edit-page action | ERP `169bdbd`; `DocumentSequenceResetTest`, `ErpDomainActionsSmokeTest`, `ERPFilamentResourcesTest` |
| DONE-S2-2B-07 | Return line invoice-line fiscal override contract for customer credit notes and supplier debit notes | ERP `e53d594`; `ReturnLineOverrideTest` |
| DONE-S2-2B-06 | Optional auto NC/ND draft creation on return `complete()` via `erp.returns.auto_create_notes_on_complete` | ERP `103e627`; `ReturnOrderServiceTest`, `SupplierReturnServiceTest`, `ErpCompanySettingsTest` |
| DONE-S2-2B-13 | Supplier payment runs with supplier bank coordinates, approval/export lifecycle, SEPA `pain.001` XML, checksum metadata, and Filament resource | ERP `01678cc`; `PaymentRunBuilderServiceTest`, `SepaPain001ExporterTest`, `PaymentRunResourceTest`, `ERPFilamentResourcesTest` |
| DONE-S2-2B-04 | Bank reconciliation difference journals with audit FK from bank statement line to journal entry | ERP `50ed21e`; `BankDifferenceJournalTest`, `BankReconciliationServiceTest` |
| DONE-S2-2B-05 | Match-with-difference action on bank reconciliation page with expense-account selection | ERP `50ed21e`; `BankReconciliationDifferenceTest`, `ERPFilamentCommercialResourcesTest` |
| DONE-S2-2B-10 | CAMT.053 XML and minimal MT940 statement import through shared bank statement import service | ERP `d800c18`; `BankStatementParserTest`, `BankStatementImportServiceTest`, `ERPFilamentCommercialResourcesTest`, `ERPFilamentRouteSmokeTest` |
| DONE-S2-2B-11 | Financial report CSV export from Trial Balance, Balance Sheet, and Income Statement Filament pages | ERP `0b0c893`; `FinancialStatementsTest`, `ERPFilamentRouteSmokeTest` |
| DONE-S2-2B-12 | Sales Pipeline and Stock Valuation filters, KPI rows, empty states, and CSV exports | ERP `b70ec97`; `OperationalReportingServicesTest`, `ERPFilamentRouteSmokeTest` |
| DONE-S2-2C-05 | FatturaPA/SDI readiness fields on company, party, invoice; model rules/forms; submit validation for missing mandatory readiness data | ERP `d7f41cc`; `EInvoiceProviderTest`, `EInvoiceSubmissionSchemaTest`, `EInvoiceSubmissionTest`, `ERPFilamentResourcesTest` |
| DONE-S2-2C-02 | `FatturaPaAnagraphicMapper` maps company, party, invoice, and lines into a FatturaPA-shaped neutral `EInvoicePayload` | ERP `dc0a9ab`; `FatturaPaAnagraphicMapperTest` |

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
| DONE-M6-01 | Bank CSV/CAMT.053/minimal MT940 import + manual match + suggestions + reco page | M6.1 |
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

## Partially complete (remaining)

None.

---

## Open (remaining work)

Status: `open` · `next` = current sprint target

### Phase 2B — Commercial & banking UX (**completed**)

> Implementation plan: [`plans/2026-06-30-erp-hardening-spec2-phase2b.md`](../plans/2026-06-30-erp-hardening-spec2-phase2b.md) (6 waves, 13 tasks + optional PriceList resources).

No open Phase 2B items remain.

### Phase 2C — E-invoice & extended permissions

> Plan: [`plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md`](../plans/2026-06-30-erp-hardening-spec2-phase3-remaining.md) — Wave 2 (Tasks 3–7).

| ID | Status | Item |
|----|--------|------|
| 2C-01 | next | Full FatturaPA XML + XSD validation |
| 2C-02 | done | Complete SDI party/company mapping |
| 2C-03 | open | Production provider (e.g. Aruba) |
| 2C-04 | open | Extended admin policies (tax codes, company switch, sequences) |
| 2C-05 | done | FatturaPA schema columns and submit readiness validation |

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

## Phase 2A — implementation summary

Implemented in ERP `300f9ef`. Backend services already existed; Phase 2A closed the **Filament actions**, **permissions**, **state-aware policy**, and **form hardening** gap.

### Action classes implemented

| Class | Page | Service |
|-------|------|---------|
| `FiscalPeriodActions` | `EditFiscalPeriod` | `FiscalPeriodCloser` |
| `FiscalYearActions` | `EditFiscalYear` | `FiscalPeriodCloser::closeYear()` |
| `JournalEntryActions` | `ViewJournalEntry` | `JournalPostingService::reverse()` |
| `DeliveryNotePostingActions` | `EditDeliveryNote` | `posted_at` + observer |
| `SalesOrderAmendmentActions` | `EditSalesOrder` | `SalesOrderAmendmentService` |

### Implemented order

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
