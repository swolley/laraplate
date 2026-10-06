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

- [ ] **Step 1: Write the failing test** — `ReserveStockOnConfirmTest`:
  - `confirming a sales order hard-reserves each item-backed line up to availability` (available drops by the reserved quantities).
  - `confirm with no stock reserves nothing and still succeeds` (on_hand 0 → the order confirms, zero reserved, no exception — the make-to-order case; the shipped MES make-to-order confirm must keep planning production).
  - `confirm partially short reserves up to available` (requested 5, on_hand 3 → reserves 3, confirm succeeds, remainder is backorder).
  - item-less lines (`item_id` null, digital) reserve nothing.
- [ ] **Step 2: Run it, verify it fails.**
- [ ] **Step 3: Implement the listener** (register it in `EventServiceProvider::$listen`). Best-effort reserve; never throw out of the confirm.
- [ ] **Step 4: Run it, verify it passes.**
- [ ] **Step 5: Commit** — `feat(erp): hard-reserve stock when a sales order is confirmed`.

---

### Task 4: Release on cancel/amend, consume at evasion

**Files:**
- Modify: `Modules/ERP/app/Models/SalesOrder.php` (in the existing `updated` hook: on transition to `Cancelled`, release reservations for the order's lines)
- Modify: `Modules/ERP/app/Services/SalesOrders/SalesOrderEvasionService.php` (consume the hard reservation for the shipped quantity per line)
- Modify: `Modules/ERP/app/Services/SalesOrders/SalesOrderAmendmentService.php` (amend-down: reduce/release the line's reservation; amend-up: reserve the delta)
- Test: `Modules/ERP/tests/Feature/SalesOrders/ReservationLifecycleTest.php`

**Interfaces:**
- Consumes: `StockReservationService.release/consume/reserve`, source alias `'erp.sales_order_line'`.

- [ ] **Step 1: Write the failing tests** — `ReservationLifecycleTest`:
  - `cancelling a confirmed order releases its reservations` (available restored).
  - `evasion consumes the shipped quantity and keeps the remainder reserved` (partial evasion: reserved drops by shipped, remainder stays `hard`).
  - `amend-down below consumed quantity is clamped` (reduce a line below the already-consumed amount → no negative, consumed stock not released).
- [ ] **Step 2: Run them, verify they fail.**
- [ ] **Step 3: Implement** the three hook edits, each calling the service with the line source. Keep edits minimal and within the existing transaction boundaries of those services.
- [ ] **Step 4: Run them, verify they pass.**
- [ ] **Step 5: Commit** — `feat(erp): release/consume reservations on cancel, amend and evasion`.

---

### Task 5: Soft reservation TTL sweep

**Files:**
- Create: `Modules/ERP/app/Console/ExpireStockReservationsCommand.php`
- Modify: `Modules/ERP/app/Providers/*` schedule registration (follow the module's existing command-schedule pattern)
- Test: `Modules/ERP/tests/Feature/Console/ExpireStockReservationsCommandTest.php`

**Interfaces:**
- Consumes: `StockReservation` (Task 1).
- Produces: `erp:stock-reservations:expire` that marks `soft` reservations past `expires_at` as `released`. (Availability already ignores them lazily — Task 2 — so the sweep is housekeeping, not correctness.)

- [ ] **Step 1: Write the failing test** — expired `soft` rows become `released`; live `soft` and `hard` are untouched.
- [ ] **Step 2: Run it, verify it fails.**
- [ ] **Step 3: Implement the command** and schedule it (daily/hourly per sibling commands). TTL default comes from a config value (`config('erp.stock_reservation.soft_ttl')`), set well above the payment window.
- [ ] **Step 4: Run it, verify it passes.**
- [ ] **Step 5: Commit** — `feat(erp): expire stale soft stock reservations`.

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

- [ ] **Step 1: Write the failing tests** — releasing a production order reserves its BOM components; `availableQuantity` for that warehouse drops accordingly; cancelling releases; backflush consumes the reservation and the existing partial-consume + `MaterialShortageDetected` behaviour is preserved when physical stock is short.
- [ ] **Step 2: Run them, verify they fail.**
- [ ] **Step 3: Implement** the reader change and the release/cancel/backflush hooks, calling the ERP service by its container binding. MES registers no morph map in ERP; it passes its own `source_type` string.
- [ ] **Step 4: Run them, verify they pass.**
- [ ] **Step 5: Commit** — `feat(mes): reserve and consume BOM components through ERP reservations`.

---

### Task 7: Documentation

**Files:**
- Modify: `Modules/ERP/docs/rag/MODULE.md` (reservation/ATP: the table, the service contract, availability = on_hand − reservations, the sales lifecycle, the advisory lock, the config TTL)
- Modify: `Modules/MES/docs/rag/MODULE.md` (component reservation at release, consume at backflush, the per-warehouse availability change)
- Modify: `Modules/ERP/README.md` if the TTL/schedule introduces an env/config worth documenting
- Test: none (docs).

- [ ] **Step 1: Write the ERP and MES RAG doc sections** describing present behaviour (not the plan).
- [ ] **Step 2: Add the plan's `## Delivery status` and a `**Documented in:**` line** naming the two module docs (required to close the plan; enforced by `tests/Unit/ClosedPlansPointToDocumentationTest.php`).
- [ ] **Step 3: Commit** — `docs(erp,mes): document stock reservation / ATP`.

---

## Notes / deferred (from the spec)

- Incoming-stock ATP (`on_hand + on_order − reservations`), per-warehouse ATP and allocation strategy are **out of scope** (spec §2).
- The not-fulfillable-remainder refund/backorder choice is a **consumer (Shop) policy**, not this plan (spec R7).
- Shop's own reserve-at-Draft wiring lives in the Shop plan (spec §7, Shop E29); this plan only ships the `StockReservationService` it will call.
