# ERP Stock Reservation / ATP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give ERP a native stock reservation so `available = on_hand − active reservations`, closing the Confirmed-but-unevaded oversell gap and letting sales (and MES) commit stock.

**Architecture:** A new ERP-owned `erp_stock_reservations` table and `StockReservationService` compute availability and hold stock under an atomic per-item lock. Reservations are created `soft` (caller-initiated, TTL) or `hard` (automatic at `SalesOrderConfirmed`), consumed at evasion, released on cancel/amend/expiry. The source document is an opaque `(source_type, source_id)` pair — ERP never imports MES or Shop classes. MES reserves BOM components at production-order release and consumes them at backflush.

**Tech Stack:** PHP 8.5, Laravel 12, `nwidart/laravel-modules`, Pest, Eloquent, `Cache::lock` atomic locks.

**Spec:** `docs/superpowers/specs/2026-10-06-erp-stock-reservation-design.md`

## Global Constraints

- Every PHP file declares `declare(strict_types=1);`; braces on all control structures; explicit param/return types; `#[Override]` when overriding; `final` where siblings are final.
- ERP models extend `Modules\Core\Overrides\Model`, use `Modules\ERP\Concerns\BelongsToCompany`, table name from `Modules\ERP\Enums\ERPTables`, validations in `getRules()`, migrations via `MigrateUtils`. Follow sibling `StockMovement` for a company-scoped, write-restricted transactional model.
- **ERP never imports or references MES or Shop classes.** The reservation `source_type` is an opaque string; the `StockReservation` model defines **no** `morphTo` relation. Dependency direction stays MES → ERP.
- Availability and reservation quantities are decimal strings (`decimal:4`), computed with the same string-decimal helpers `StockMovementService` already uses — never native float arithmetic for stock math.
- Tests are Pest feature tests under `Modules/ERP/tests/` (and `Modules/MES/tests/` for MES tasks), using factories; no classes declared inside test files. Run `vendor/bin/pint --dirty` from the laraplate root before each commit.

## Review Focus

- **Concurrent double-reserve of the last unit** — two `reserve()` calls racing on the same item must not both succeed beyond `on_hand`; the atomic lock serializes them (Task 2 test `reserve rejects a second concurrent reservation beyond on_hand`).
- **Expired soft reservation still blocking stock** — a `soft` reservation past `expires_at` must not count toward reserved, whether or not the sweep has run (Task 2 test `available ignores expired soft reservations`).
- **Consuming or releasing more than reserved** — `consume()` beyond the reserved quantity, or a double `release()`, must be rejected or idempotent, never drive reserved negative (Task 2 tests `consume rejects more than reserved`, `release is idempotent`).
- **Confirm when stock vanished after a soft hold lapsed** — promoting at `SalesOrderConfirmed` with no live soft reservation must re-validate availability and fail the confirm when stock is gone (Task 4 test `confirm with expired hold and no stock is rejected`).
- **Amend-down below already-consumed quantity** — reducing a line below what a partial evasion already consumed must not release consumed stock nor go negative (Task 5 test `amend-down below consumed quantity is clamped`).

---

### Task 1: Reservation table, state enum, model, factory

**Files:**
- Create: `Modules/ERP/database/migrations/2026_10_06_000000_create_stock_reservations_table.php`
- Create: `Modules/ERP/app/Enums/StockReservationState.php`
- Create: `Modules/ERP/app/Models/StockReservation.php`
- Create: `Modules/ERP/database/factories/StockReservationFactory.php`
- Modify: `Modules/ERP/app/Enums/ERPTables.php` (add `case StockReservations = 'erp_stock_reservations';`)
- Test: `Modules/ERP/tests/Feature/Models/StockReservationTest.php`

**Interfaces:**
- Produces: `StockReservation` (company-scoped Eloquent model) with columns `company_id`, `item_id`, `warehouse_id` (nullable), `source_type` (string), `source_id`, `quantity` (decimal:4), `state` (`StockReservationState`), `expires_at` (nullable datetime); relations `item()`, `warehouse()`; **no `source()` morphTo**. `StockReservationState` enum cases `Soft='soft'`, `Hard='hard'`, `Consumed='consumed'`, `Released='released'` with a `validationRule()` static (mirror `StockMovementDirection`). Scope `active()` = `whereIn('state', ['soft','hard'])`.

- [x] **Step 1: Write the failing test** — `StockReservationTest`: creating a reservation persists `state` as an enum and `quantity` as `decimal:4`; `active()` scope returns `soft`+`hard` and excludes `consumed`+`released`; `BelongsToCompany` scopes by current company.
- [x] **Step 2: Run it, verify it fails** — `php artisan test --compact Modules/ERP/tests/Feature/Models/StockReservationTest.php` → FAIL (class/table missing).
- [x] **Step 3: Add `ERPTables::StockReservations`**, the `StockReservationState` enum, the migration (follow a sibling e.g. `create_stock_movements_table`: `MigrateUtils`, `company_id` FK, `item_id` FK, nullable `warehouse_id` FK, `source_type` string + `source_id`, `quantity` decimal(15,4), `state` string, `expires_at` nullable; indexes `(company_id, item_id, state)` and `(source_type, source_id)`), the `StockReservation` model (mirror `StockMovement`: `BelongsToCompany`, `DeniesGenericCrudWrites`, `RestrictsCrudWrites`, `shouldVersioning(): false`, casts, `#[Scope] active()`), and the factory.
- [x] **Step 4: Run it, verify it passes.**
- [x] **Step 5: Commit** — `feat(erp): add stock reservation table, state enum and model`.

---

### Task 2: `StockReservationService` — availability, reserve, release, consume

**Files:**
- Create: `Modules/ERP/app/Services/Inventory/StockReservationService.php`
- Create: `Modules/ERP/app/Exceptions/InsufficientStockException.php`
- Test: `Modules/ERP/tests/Feature/Services/Inventory/StockReservationServiceTest.php`

**Interfaces:**
- Consumes: `StockReservation`, `StockLevel`, `StockReservationState` (Task 1).
- Produces:
  - `available(int $companyId, int $itemId): string` — `Σ StockLevel.quantity (all warehouses) − Σ quantity of active reservations for the item`, where an active reservation is `hard`, or `soft` with `expires_at` null or in the future. Decimal-string math.
  - `reserve(int $companyId, int $itemId, string $quantity, StockReservationState $mode, string $sourceType, int $sourceId, ?int $warehouseId = null, ?\Carbon\CarbonInterface $expiresAt = null): StockReservation` — under `Cache::lock("erp:stock-reservation:{$companyId}:{$itemId}", 10)->block(5, …)` inside a DB transaction: recompute `available`, throw `InsufficientStockException` if `available < quantity`, else insert the reservation (`soft` carries `expires_at`).
  - `promoteToHard(string $sourceType, int $sourceId, int $companyId): void` — flip each live `soft` for the source to `hard` and clear `expires_at`; for any line whose soft is missing/expired, this is a no-op here (the caller creates a fresh `hard` via `reserve`, which re-validates).
  - `release(string $sourceType, int $sourceId): void` — set active reservations for the source to `released`; idempotent (no active rows → no-op).
  - `consume(string $sourceType, int $sourceId, string $quantity): void` — reduce the source's active (`hard`) reserved quantity by `quantity` (mark `consumed`, splitting a row if partially consumed); reject `quantity` greater than the source's active reserved total.

- [x] **Step 1: Write the failing tests** — `StockReservationServiceTest`:
  - `available equals on_hand minus active reservations` (seed StockLevels summing e.g. 10, a hard reservation of 3 → `available` = `7.0000`).
  - `reserve rejects when requested exceeds available` → throws `InsufficientStockException`.
  - `reserve rejects a second concurrent reservation beyond on_hand` (on_hand 1; two sequential reserves of 1 → first succeeds, second throws).
  - `available ignores expired soft reservations` (a `soft` with `expires_at` in the past does not reduce `available`).
  - `release restores availability and is idempotent` (reserve, release, available back to full; second release no-ops).
  - `consume closes the reserved quantity` and `consume rejects more than reserved`.
- [x] **Step 2: Run them, verify they fail.**
- [x] **Step 3: Implement `StockReservationService`** and `InsufficientStockException` (extend `\RuntimeException`). Implemented with a DB row lock (`lockForUpdate` on the item's `StockLevel` + live reservation rows) inside reserve's transaction; decimal math via `Modules\ERP\Support\Decimal` (BigDecimal), not `StockMovementService`'s float helpers; queries/transaction derived from the owning model, not `ConnectionScopedModels::for()` (frozen for new code) — see Task 2 rulings in the ledger.
- [x] **Step 4: Run them, verify they pass.**
- [x] **Step 5: Commit** — `feat(erp): stock reservation service with locked availability`.

---

### Task 3: Hard reserve at `SalesOrderConfirmed`

**Files:**
- Create: `Modules/ERP/app/Listeners/ReserveStockForConfirmedSalesOrder.php`
- Test: `Modules/ERP/tests/Feature/SalesOrders/ReserveStockOnConfirmTest.php`

**Interfaces:**
- Consumes: `StockReservationService` (Task 2), `SalesOrderConfirmed` event (`Modules\ERP\Events\SalesOrderConfirmed`), `SalesOrder`/`SalesOrderLine`.
- Produces: a listener (registered in `EventServiceProvider::$listen` — discovery does not work for the module's `EventServiceProvider` subclass) that, for each confirmed order line with a non-null `item_id`, calls `promoteToHard(...)` then **best-effort** `reserve(..., Hard)` for `min(still-unreserved line quantity, available)` using the order's `company_id`, source `('erp.sales_order_line', $line->id)`. It **pre-clamps to availability so it never triggers `reserve()`'s `InsufficientStockException`** and **never blocks the confirm** — the unreserved remainder is backorder / make-to-order (amended 2026-10-06, spec R4: blocking broke the shipped MES make-to-order flow).

- [x] **Step 1: Write the failing test** — `ReserveStockOnConfirmTest`:
  - `confirming a sales order hard-reserves each item-backed line up to availability` (available drops by the reserved quantities).
  - `confirm with no stock reserves nothing and still succeeds` (on_hand 0 → the order confirms, zero reserved, no exception — the make-to-order case; the shipped MES make-to-order confirm must keep planning production).
  - `confirm partially short reserves up to available` (requested 5, on_hand 3 → reserves 3, confirm succeeds, remainder is backorder).
  - item-less lines (`item_id` null, digital) reserve nothing.
  - (added in review) multiple lines of the same item split availability without overselling; lock-held → confirms with a WARNING; foreign-company item → confirms with an ERROR, both leaving the order confirmed and unreserved.
- [x] **Step 2: Run it, verify it fails.**
- [x] **Step 3: Implement the listener** (register it in `EventServiceProvider::$listen`). Best-effort reserve; never throw out of the confirm — all declared `reserve()` exceptions (`InsufficientStockException` retried, `LockTimeoutException`, `ValidationException`) caught and logged with context.
- [x] **Step 4: Run it, verify it passes.**
- [x] **Step 5: Commit** — `feat(erp): hard-reserve stock when a sales order is confirmed` (+ best-effort rework + never-escape fix).

---

### Task 4: Release on cancel/amend, consume at evasion

**Files:**
- Modify: `Modules/ERP/app/Models/SalesOrder.php` (in the existing `updated` hook: on transition to `Cancelled`, release reservations for the order's lines)
- Modify: `Modules/ERP/app/Services/SalesOrders/SalesOrderEvasionService.php` (consume the hard reservation for the shipped quantity per line)
- Modify: `Modules/ERP/app/Services/SalesOrders/SalesOrderAmendmentService.php` (amend-down: reduce/release the line's reservation; amend-up: reserve the delta)
- Test: `Modules/ERP/tests/Feature/SalesOrders/ReservationLifecycleTest.php`

**Interfaces:**
- Consumes: `StockReservationService.release/consume/reserve`, source alias `'erp.sales_order_line'`.

- [x] **Step 1: Write the failing tests** — `ReservationLifecycleTest`:
  - `cancelling a confirmed order releases its reservations` (available restored).
  - `evasion consumes the shipped quantity and keeps the remainder reserved` (partial evasion: reserved drops by shipped, remainder stays `hard`).
  - `amend-down below consumed quantity is clamped` (reduce a line below the already-consumed amount → no negative, consumed stock not released).
  - (added) evasion of a partially-backordered line consumes only the reserved part (never over-consumes).
- [x] **Step 2: Run them, verify they fail.**
- [x] **Step 3: Implement** the hooks: `StockReservationService::reservedQuantity()`; `SalesOrderCancelled` event + `ReleaseStockForCancelledSalesOrder` listener; evasion consumes `min(shipped, reservedQuantity)`; amend releases the source line's hard hold. Task 3 listener swapped onto `reservedQuantity()`. Note (parked ruling): amend releases on draft creation — an abandoned amendment leaves the source unreserved (plan-mandated, ERP amendment-lifecycle follow-up).
- [x] **Step 4: Run them, verify they pass.**
- [x] **Step 5: Commit** — `feat(erp): release/consume reservations on cancel, amend and evasion`.

---

### Task 5: Soft reservation TTL sweep

**Files:**
- Create: `Modules/ERP/app/Console/ExpireStockReservationsCommand.php`
- Modify: `Modules/ERP/app/Providers/*` schedule registration (follow the module's existing command-schedule pattern)
- Test: `Modules/ERP/tests/Feature/Console/ExpireStockReservationsCommandTest.php`

**Interfaces:**
- Consumes: `StockReservation` (Task 1).
- Produces: `erp:stock-reservations:expire` that marks `soft` reservations past `expires_at` as `released`. (Availability already ignores them lazily — Task 2 — so the sweep is housekeeping, not correctness.)

- [x] **Step 1: Write the failing test** — expired `soft` rows become `released`; live `soft` and `hard` are untouched.
- [x] **Step 2: Run it, verify it fails.**
- [x] **Step 3: Implement the command** and schedule it (hourly, `onOneServer`+`withoutOverlapping`, sibling pattern). TTL default from `config('erp.stock_reservation.soft_ttl')` (1440 min via `ERP_STOCK_RESERVATION_SOFT_TTL`), read at reservation-creation time, not by this command.
- [x] **Step 4: Run it, verify it passes.**
- [x] **Step 5: Commit** — `feat(erp): expire stale soft stock reservations`.

---

### Task 6: MES — reserve components at release, consume at backflush, availability

**Files:**
- Modify: `Modules/MES/app/Services/ErpStockReader.php` (`availableQuantity` subtracts reservations pinned to that warehouse)
- Modify: MES production-order release path (reserve BOM components `hard`, source `('mes.production_order_material', $line->id)`, `warehouse_id = order.warehouse_id`) and cancel path (release)
- Modify: `Modules/MES/app/Jobs/BackflushMaterialsJob.php` (consume the reservation for the consumed quantity)
- Test: `Modules/MES/tests/Feature/.../ProductionOrderReservationTest.php`

**Interfaces:**
- Consumes: ERP `StockReservationService` resolved from the container (MES depends on ERP), `ProductionOrderStatus::Released`/`Cancelled`.
- Produces: `ErpStockReader::availableQuantity(item, warehouse, company)` = `on_hand(item, warehouse) − Σ active reservations pinned to that warehouse`. Company-wide (null-warehouse) reservations are not subtracted per-warehouse (documented limitation).

- [x] **Step 1: Write the failing tests** — releasing a production order reserves its BOM components; `availableQuantity` for that warehouse drops accordingly; cancelling releases; backflush consumes the reservation and the existing partial-consume + `MaterialShortageDetected` behaviour is preserved when physical stock is short. (added in review: two orders sharing one BOM reserve/cancel/backflush in isolation.)
- [x] **Step 2: Run them, verify they fail.**
- [x] **Step 3: Implement** the reader change and the release/cancel/backflush hooks, calling the ERP service by its container binding. MES passes its own `source_type` string (`mes.production_order_material`) keyed by a **per-order `material_line_id`** (not the shared template `bom_line_id`) stamped on the frozen BOM snapshot.
- [x] **Step 4: Run them, verify they pass.**
- [x] **Step 5: Commit** — `feat(mes): reserve and consume BOM components through ERP reservations` (+ per-order material_line_id fix).

---

### Task 7: Documentation

**Files:**
- Modify: `Modules/ERP/docs/rag/MODULE.md` (reservation/ATP: the table, the service contract, availability = on_hand − reservations, the sales lifecycle, the advisory lock, the config TTL)
- Modify: `Modules/MES/docs/rag/MODULE.md` (component reservation at release, consume at backflush, the per-warehouse availability change)
- Modify: `Modules/ERP/README.md` if the TTL/schedule introduces an env/config worth documenting
- Test: none (docs).

- [x] **Step 1: Write the ERP and MES RAG doc sections** describing present behaviour (not the plan). (ERP `6be4c2a`: MODULE.md + README; MES: MODULE.md, updated in `4b9afb1`.)
- [x] **Step 2: Add the plan's `## Delivery status` and a `**Documented in:**` line** naming the module docs (required to close the plan; enforced by `tests/Unit/ClosedPlansPointToDocumentationTest.php`).
- [x] **Step 3: Commit** — `docs(erp,mes): document stock reservation / ATP`.

---

### Task 8: Correct the amend reservation lifecycle (follow-up, 2026-10-07)

**Files:**
- Modify: `Modules/ERP/app/Services/SalesOrders/SalesOrderAmendmentService.php` (stop releasing the source's reservation at draft creation; drop the now-unused `StockReservationService` dependency if it becomes unused)
- Modify: `Modules/ERP/app/Listeners/ReserveStockForConfirmedSalesOrder.php` (when the confirmed order has `amends_sales_order_id`, release the source order's line reservations BEFORE reserving the amendment's own lines)
- Test: `Modules/ERP/tests/Feature/SalesOrders/ReservationLifecycleTest.php` (extend)

**Interfaces:** consumes `StockReservationService.release/reserve/reservedQuantity`, `SalesOrderConfirmed`, `SalesOrder.amends_sales_order_id`.

- [x] **Step 1: Write the failing tests** — `amend() does not release the source's reservation`; `an abandoned (never-confirmed) amendment leaves the source's hold intact`; `confirming the amendment releases the source's holds and reserves the amendment's lines`; `the release happens before the amendment reserves` (on_hand == remaining qty → the amendment ends fully reserved, not backordered — the discriminating test).
- [x] **Step 2: Run them, verify they fail** (3 failed first).
- [x] **Step 3: Implement** — removed the per-line `release()` from `amend()` (dropped the now-unused `StockReservationService` dep); in `ReserveStockForConfirmedSalesOrder`, when `amends_sales_order_id !== null`, release each source line's reservation first (best-effort, catch Throwable + log), then the normal best-effort reserve of the amendment's lines.
- [x] **Step 4: Run them, verify they pass** (18 passing, 70 assertions).
- [x] **Step 5: Commit** — `fix(erp): release the amended source's reservation on amendment confirm, not draft creation` (`e76cbbd`). Review Approved, must-fix empty.

**Follow-up:** the amendment-supersede model (mark the source `Amended`, block its further evasion) is Task 9.

---

### Task 9: Amendment supersedes the source order (follow-up, 2026-10-07)

**Problem:** after Task 8, confirming an amendment releases the source's reservation but nothing marks the source superseded — it stays `Confirmed`/`PartiallyEvased` and, because `SalesOrderEvasionService` has no status gate, can still be delivered/invoiced (which would also overwrite its status). Re-amending it is already blocked (`amend()` requires `Confirmed`/`PartiallyEvased`).

**Files:**
- Create: `Modules/ERP/app/Listeners/MarkSourceOrderSupersededOnAmendmentConfirm.php` (on `SalesOrderConfirmed`, if `amends_sales_order_id` is set, transition the source order to `SalesOrderStatus::Amended`)
- Modify: `Modules/ERP/app/Providers/EventServiceProvider.php` (register the listener)
- Modify: `Modules/ERP/app/Services/SalesOrders/SalesOrderEvasionService.php` (reject forward `delivery`/`invoice` modes when the order status is `Amended` or `Cancelled`; reversals stay allowed)
- Test: `Modules/ERP/tests/Feature/SalesOrders/` (extend)

**Interfaces:** consumes `SalesOrderConfirmed`, `SalesOrder.amends_sales_order_id`, `SalesOrderStatus::{Amended,Cancelled}`.

- [x] **Step 1: Write the failing tests** — source marked `Amended` on amendment confirm; Amended order cannot be delivered / invoiced / re-amended; reversals still allowed. (`AmendmentSupersedesSourceTest`, 6 tests.)
- [x] **Step 2: Run them, verify they fail** (6 failed first).
- [x] **Step 3: Implement** the `MarkSourceOrderSupersededOnAmendmentConfirm` listener (best-effort, Confirmed/PartiallyEvased → Amended) + the `guardForwardEvasion` check in `SalesOrderEvasionService`.
- [x] **Step 4: Run them, verify they pass** (6 new; SalesOrders suite 24 passing).
- [x] **Step 5: Commit** — `feat(erp): amendment supersedes the source sales order (mark Amended, block evasion)` (`663f4c7`). Review Approved, must-fix empty.

**Deferred (review minors / recommended with the inverse-lifecycle follow-up):** a delivery/invoice **reversal** on an Amended order still runs `syncHeaderStatus`, which recomputes the header from line quantities and can flip it off `Amended` (silently un-superseding) — cheap ~3-line fix: make `syncHeaderStatus` preserve a terminal `Amended`/`Cancelled` status, plus a status assertion in the reversal test. Grammar: "A amended/cancelled" → "An amended". Test-file-scope helper functions could be closures.

---

### Task 10: Amended-status integrity — reversal preserve + cancel reverts the source (2026-10-07)

Close the inverse-lifecycle holes left by Task 9 so the `Amended` supersede is watertight.

**Files:**
- Modify: `Modules/ERP/app/Services/SalesOrders/SalesOrderEvasionService.php` (`syncHeaderStatus` must PRESERVE a terminal `Amended`/`Cancelled` status instead of recomputing it from line quantities — so a delivery/invoice **reversal** on an Amended order no longer silently un-supersedes it; also fix the grammar "A amended/cancelled" → "An amended/cancelled")
- Modify: `Modules/ERP/app/Listeners/ReleaseStockForCancelledSalesOrder.php` (or a dedicated listener) — when the cancelled order is itself an amendment (`amends_sales_order_id` set), **revert the source**: recompute its status from its own line quantities (PartiallyEvased if any delivered, else Confirmed) and re-reserve its lines best-effort (the amendment's own holds are released by the existing cancel handling)
- Test: `Modules/ERP/tests/Feature/SalesOrders/` (extend)

- [x] **Step 1: Write the failing tests** — reversal keeps Amended + guard still blocks; cancelling an amendment reverts a Confirmed source and re-reserves; cancelling an amendment of a PartiallyEvased source reverts to PartiallyEvased; grammar assertion. (4 new.)
- [x] **Step 2: Run them, verify they fail.**
- [x] **Step 3: Implement** — `syncHeaderStatus` preserves terminal Amended/Cancelled (shared `statusFromLineQuantities`); `RevertSourceOrderOnAmendmentCancel` listener (recompute + best-effort re-reserve via extracted `reserveOrderLines`, now `(qty_ordered − qty_delivered) − reserved` — behaviour-preserving at confirm); grammar "An amended"/"A cancelled".
- [x] **Step 4: Run them, verify they pass** (28 SalesOrders; full ERP suite 718 passing).
- [x] **Step 5: Commit** — `fix(erp): keep Amended terminal on reversal and revert the source when an amendment is cancelled` (`15705bc`). Review Approved. Minors (deferred): source-with-no-lines stays Amended (unreachable); scope note.

---

### Task 11: MES reservation hardening (2026-10-07)

Close the three MES fast-follows the final review flagged.

**Files:**
- Modify: `Modules/MES/app/Services/ProductionOrderService.php` → move the `material_line_id` stamping into a `ProductionOrder` model `created` boot hook (or an equivalent covering every creation path), so orders created outside `create()` (factories, imports) also get it; add a **guard** that throws/asserts when a snapshot has `>= 1000` component lines (the `order_id * 1000 + index` stride), so a silent cross-order collision can never happen unnoticed
- Modify: `Modules/MES/app/Services/ErpStockReader.php` → `availableQuantity(item, warehouse, company)` also subtracts the item's company-wide **null-warehouse** active holds (sales reservations), conservatively, so MES backflush cannot consume stock a sales order reserved (closes the cross-module gap; conservative = may under-report when stock is spread across warehouses, never oversells)
- Modify: `Modules/MES/app/Models/ProductionOrder.php` (boot hook) if that is where the stamp lands
- Test: `Modules/MES/tests/Feature/` (extend)

- [x] **Step 1: Write the failing tests** — `a production order created via the factory still gets a material_line_id` (reserve works for a non-service creation path); `a BOM with >= 1000 lines is rejected` (guard throws); `availableQuantity excludes a sales (null-warehouse) hold on the same item` (seed a null-warehouse hard hold → MES available drops → backflush cannot consume it).
- [x] **Step 2: Run them, verify they fail.**
- [x] **Step 3: Implement** the boot-hook stamp + stride guard + the reader's null-warehouse subtraction. Keep the own-hold add-back at backflush correct (it adds back the order's OWN warehouse-pinned line hold; sales null-warehouse holds are not the order's own).
- [x] **Step 4: Run them, verify they pass.**
- [x] **Step 5: Commit** — `fix(mes): stamp material_line_id on every creation path, guard the stride, subtract sales holds in the reader`.

---

## Notes / deferred (from the spec)

- Incoming-stock ATP (`on_hand + on_order − reservations`), per-warehouse ATP and allocation strategy are **out of scope** (spec §2).
- The not-fulfillable-remainder refund/backorder choice is a **consumer (Shop) policy**, not this plan (spec R7).
- Shop's own reserve-at-Draft wiring lives in the Shop plan (spec §7, Shop E29); this plan only ships the `StockReservationService` it will call.

---

## Delivery status (2026-10-07)

All seven tasks delivered, executed subagent-driven on `master` of the ERP and MES submodules (per user consent). Tests green per task (ERP reservation service, sales confirm/cancel/amend/evasion, expiry command; MES component reservation + backflush + reader). ERP commits `e1f88a72..a17f15d`; MES commits `091f1af` + `4b9afb1` (on top of concurrent unrelated MES work).

**Documented in:** `Modules/ERP/docs/rag/MODULE.md`, `Modules/ERP/README.md`, `Modules/MES/docs/rag/MODULE.md`.

**Divergences from the plan as written (all deliberate, ruled during execution):**
- **Confirm reserve is best-effort, not blocking** (spec R4 amended 2026-10-06): hard-reserve at `SalesOrderConfirmed` reserves up to availability and never blocks the confirm — blocking broke MES make-to-order. The remainder is backorder; Shop's paid-but-unfulfillable gate lives in Shop's checkout. All `reserve()` exceptions are caught/logged in the listener.
- **MES source id**: component reservations are keyed by a per-order `material_line_id` (`order_id*1000 + line_index`) stamped on the frozen BOM snapshot, not the shared template `bom_line_id` (which pooled holds across orders built from the same BOM). Bounds an order to <1000 component lines.
- **Decimal math** uses `Modules\ERP\Support\Decimal` (BigDecimal), not `StockMovementService`'s float helpers (those use floats).
- **Queries/transaction** derive from the owning model, not `ConnectionScopedModels::for()` (frozen for new code).
- Concurrency uses `Cache::lock` **plus** a `lockForUpdate` DB row lock inside `reserve()`'s transaction (the cache lock alone was insufficient under an outer transaction).

**Amend lifecycle (resolved 2026-10-07, Task 8):** the earlier parked window is fixed — `amend()` no longer releases at draft creation; the source keeps its hold until the amendment is **confirmed**, at which point the confirm listener releases the source's holds first and then reserves the amendment's lines (remaining qty reserved exactly once; abandoned amendment keeps the source's hold). Spec R4 amended accordingly. **Remaining out-of-scope ERP gap (fast-follow):** nothing marks the source order `Amended`/superseded today, so after the amendment confirms the source stays `Confirmed` and still evadable with no reservation — an ERP amendment-model task (mark the source `Amended`, block its further evasion), not reservation.

**Final whole-branch review (2026-10-07): Approved for merge, must-fix set empty.** Core guarantees verified end to end (oversell impossible at reserve; consistent service contract and lock ordering across all consumers; opaque source with globally-unique ids; idempotent lifecycle). Additional divergences/caveats it surfaced, recorded here so plan and code agree:
- **R5 lock mechanism**: implemented as `Cache::lock` + a `lockForUpdate` DB row-lock (held to COMMIT), not Core's named advisory-lock policy the decision referenced. The row lock is a stronger guarantee; deliberate divergence.
- **R6 early-shortage benefit not delivered**: MES reserves at `Released` but best-effort, with no early shortage event; a component shortage still surfaces at backflush, not at planning. The decision's mechanism shipped; its stated benefit did not.
- **Important (cross-module, non-blocking for v1): sales↔MES null-warehouse backflush gap.** Sales reservations carry `warehouse_id = null`; `ErpStockReader` subtracts only warehouse-pinned holds, so MES backflush can physically consume stock a sales order hard-reserved. Mitigated because sold finished goods and BOM components are usually distinct items, and per-warehouse ATP is a deferred v1 non-goal (spec §2). To close: have the MES reader also subtract company-wide null-warehouse holds. Documented as a known limitation.

**Recommended fast-follows (not merge blockers):**
- Stamp MES `material_line_id` in a model `created` boot hook so EVERY production-order creation path gets it (today only `ProductionOrderService::create()` stamps it; orders created elsewhere silently reserve nothing). Reviewer's top follow-up.
- Add the ≥1000-line guard for `material_line_id` (throw/assert rather than silently collide).
- Close or document the sales↔MES null-warehouse backflush caveat (above).

**Task 11 (MES reservation hardening, 2026-10-07): the three fast-follows above are CLOSED.** (Landed in MES commit `b48ee0d` — see the note below: a concurrent session's broad `git add` swept Task 11's files into its own machine-states commit instead of an isolated `fix(mes): …` commit. The content is correct and green; the commit is not isolated.)
- `material_line_id` is now stamped by the `ProductionOrder` `created` boot hook on every creation path (service, factory, import, direct create), not only `ProductionOrderService::create()`. The hook stamps only lines that lack an id (a fixture-supplied id is preserved); the service no longer stamps explicitly.
- The `creating` boot hook rejects a BOM snapshot with ≥ 1000 component lines (`DomainException`, before insert), so the `order_id * 1000 + index` stride can never silently collide with the next order.
- `ErpStockReader::availableQuantity()` now also subtracts the item's company-wide null-warehouse (sales) holds, conservatively (off every warehouse), so MES backflush can no longer consume stock a sales order hard-reserved. The backflush own-hold add-back still adds back only the order's OWN warehouse-pinned line hold; a sales null-warehouse hold is never the order's own, so it stays subtracted. **The earlier "sales↔MES null-warehouse backflush gap" caveat above is resolved** (per-warehouse ATP remains a deferred non-goal; the subtraction is conservative across warehouses). Documented in `Modules/MES/docs/rag/MODULE.md`.

**Polish applied (2026-10-07):** the reviewer-flagged cosmetic minors were addressed — ERP (`226abae`): expire-command plural grammar (`Str::plural`), `soft_ttl` `max(1, …)` guard, `SalesOrderEvasionService` → `final readonly`, listener docblock reworded to "catches reserve()'s declared failures", `StockReservation` added to `ModelQuantityValidationTest` + `CrudWriteGuardTest`; MES (`5002fac`): `bom_snapshot` null-guard in the stride guard. The items below remain deliberately deferred (genuinely not worth churn): `availableUnderRowLock` predicate duplication (forced), the ≥1000 off-by-one margin (deliberate), N release UPDATEs (correct), incoming-stock/per-warehouse ATP (v1 non-goals).

**Deferred minors (for later cleanup, none load-bearing):**
- ERP: `ExpireStockReservationsCommand` singular-count grammar; `(int) env()` non-numeric → 0; availability `availableUnderRowLock()` duplicates `available()`'s predicate and locks O(n) reservation rows; `SalesOrderEvasionService` could be `final readonly`; cancel issues N release UPDATEs; docblock "no exception escapes" covers only `reserve()`'s declared exceptions; `StockReservation` not added to `ModelQuantityValidationTest`/`CrudWriteGuardTest`.
- MES: reader counts expired soft holds that ERP `available()` ignores (conservative); `material_line_id` has no ≥1000-line guard (silent collision at the bound — **worth the cheap guard before merge**); the `material_line_id` stamp is a second write (not shown to be in one transaction; orders created outside `ProductionOrderService::create()` get none); backflush reservation-close runs outside the stock-out transaction (deferred defect 4).
