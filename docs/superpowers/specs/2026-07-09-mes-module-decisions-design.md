# MES Module — Locked Decisions

**Status:** Confirmed by product owner on 2026-07-09.  
**Implementation plan:** `docs/superpowers/plans/2026-06-19-mes-module-full-implementation.md`  
**Module law:** `Modules/MES/.cursor/rules/module-context.mdc`

---

## Source of truth

| Layer | Document |
|-------|----------|
| Requirements & tasks | Superpowers implementation plan (above) |
| Module constraints | `module-context.mdc` |
| Developer glossary | `Modules/MES/docs/GLOSSARY.md` |
| User guide (TBD) | `Modules/MES/docs/MES_GUIDA_SEMPLICE.md` |

Legacy Kiro specs (`.kiro/specs/mes-module/`) were removed on 2026-07-09. Do not recreate them.

---

## Confirmed decisions

### D1 — Delivery scope

Implement the **full MES module** per the implementation plan: domain → API → Filament → test hardening → docs. No reduced MVP.

### D2 — Requirements authority

Follow `module-context.mdc` and the superpowers plan. Where older notes conflict, **module-context wins**.

### D3 — Production order numbering

Use ERP `DocumentNumberAllocator` with a new `DocumentType::ProductionOrder` case (`defaultGapAllowed() = true`, same family as sales/purchase orders). Do **not** use custom company+year numbering.

### D4 — Sales order linkage

`mes_production_orders` carries **both** nullable FKs:

- `sales_order_id` → `sales_orders.id`
- `sales_order_line_id` → `sales_order_lines.id`

Application rule: when both are set, `sales_order_line.sales_order_id` must equal `sales_order_id` (DeliveryNote pattern). One SO line may spawn multiple POs (lot split); one PO links to at most one SO line.

Auto-creation from ERP: emit `SalesOrderConfirmed` in ERP; MES listens and dispatches `CreateProductionOrderFromSalesOrderJob` (to be implemented).

### D5 — Backflush timing

**Target model:** operation-scoped backflush — a `backflush` BomLine is consumed when its linked routing operation completes.

**Schema:** add nullable `routing_operation_id` FK on `mes_bom_lines` → `mes_routing_operations.id`.

**Rules:**

| BomLine `routing_operation_id` | When backflush runs |
|--------------------------------|---------------------|
| Set | On completion of that `ProductionOrderOperation` |
| `null` | On completion of the **last** operation in the order sequence (fallback until all lines are operation-linked) |

Manual consumption (`consumption_method = manual`) is always operator-initiated, any time.

`BackflushMaterialsJob` must be idempotent per (operation, bom_line) to prevent double issues.

### D6 — Operator shift check

Shift verification at operation start is a **non-blocking warning** (like capacity overload). `OperatorLog` entries are always created on start/complete.

### D7 — Testing strategy

Use Pest feature/integration tests with explicit invariant datasets. No new property-based testing library.

### D8 — Git workflow

Commit in `Modules/MES` submodule; bump monorepo submodule pointer when releasing.

### D9 — Audit / DIFF

Apply Core DIFF versioning on high-value models: `ProductionOrder`, `Bom`, `Routing`.

Delivered through Core's `HasVersions`, which these models inherit from `Core\Overrides\Model`: Core seeds the per-table setting `versioning.strategy.{table}` (group `versioning`) with `DIFF` for `mes_production_orders`, `mes_boms` and `mes_routings`, and an administrator can change or disable it. The models do not declare `versionStrategy` themselves, because that would force `DIFF` and hide the setting. `MasterDataVersioningTest` seeds and checks that a save records the change.

The lines that carry the substance of a BOM or routing are covered too: `BomLine` and `RoutingOperation` keep the plain Eloquent model and take the Core traits that fit (`HasVersions`, `HasValidations` with rules on every field, `HasPrefixedTableName`) but not `SoftDeletes`. They are deleted for good, because `(routing_id, sequence)` is unique and a soft-deleted operation would block re-creating it, and the version rows keep the whole image of a row deleted for good, so the history of a removed line or operation survives. Adding the traits creates no new permissions. Validation now runs on every create and update of a line or operation, from any path; the unique `(routing_id, sequence)` pair stays with the database constraint.

### D10 — KPI materialization

Capacity load and OEE aggregates are materialized via jobs + cache, not recomputed on every API request.

### D11 — Bill of materials and routing are not locked by use (decided 2026-09-30)

A production order freezes the active BOM and routing into `bom_snapshot` and `routing_snapshot` when it is created, and everything downstream (backflush, operations, quality planning per operation) runs from the snapshot. Editing a BOM or routing therefore never changes an order that already exists, and the master data stays freely editable.

The earlier idea of a guard that forbids editing a BOM or routing once a released order froze it (`assertNotLocked`, `BomLockedException`, `RoutingLockedException`) is **cancelled and removed**: it duplicated what the snapshot already guarantees, it also fired for completed and cancelled orders so a BOM used once could never be edited again, and it contradicted the user guide ("if you change the BOM or the routing, the existing order does not change").

History of changes is D9's job (DIFF versioning on `Bom` and `Routing`), not a lock. A stricter, opt-in revision control for customers who need it (released revisions immutable, every change a new dated revision) is **deferred** and would be its own plan; nothing here forecloses it.

---

## Domain events (to implement)

Typed events emitted by MES observers/services:

- `ProductionOrderReleased`, `ProductionOrderCompleted`, `ProductionOrderCancelled`
- `OperationStarted`, `OperationCompleted`, `OperationSkipped`
- `MaterialConsumed`, `LotCreated`, `SerialNumberAssigned`
- `QualityCheckFailed`, `NonConformanceOpened`
- `DowntimeStarted`, `DowntimeEnded`
- `StockShortageDetected` (event, not exception — consumption recorded with `stock_shortage = true`)

---

## ERP touchpoints (MES-initiated, one-way)

| Change | Location |
|--------|----------|
| `DocumentType::ProductionOrder` | `Modules/ERP/app/Casts/DocumentType.php` + enum migration if needed |
| `SalesOrderConfirmed` event | `Modules/ERP/app/Events/` |
| `tracing_type` on `items` | MES migration (already exists) |
| Stock movements | MES `StockMovementRecorder` → `ErpStockMovementRecorder` (no ERP→MES imports) |

---

## Implementation status snapshot (2026-07-09)

| Area | Status |
|------|--------|
| Scaffolding, contract, WorkCenter | Done |
| `tracing_type` migration | Done |
| BOM / Routing / PO domain | Migrations partial; models/services missing |
| API / Filament | Not started |
| Docs | Glossary partial; user guide missing |
