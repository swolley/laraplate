# ERP stock reservation / ATP — design

**Date:** 2026-10-06
**Module:** `Modules/ERP` (owner) + `Modules/MES` (consumer)
**Status:** Design agreed. No implementation started.
**Related:** `2026-09-17-shop-module-design.md` (§8a ERP-4, E29 — Shop is a future consumer);
`2026-06-19-mes-module-full-implementation.md` (MES materials; near-complete, not the home for this scope).

---

## 1. Purpose

ERP has no reservation / available-to-promise concept: `StockLevel` holds a single on-hand
`quantity` per `(company, item, warehouse)`, stock moves only at evasion (delivery note), and
`SalesOrderConfirmed` triggers only MES production planning. So a **`Confirmed`-but-unevaded sales
order does not reduce availability** — two confirmed orders, even entered by hand in back-office, can
commit the same unit and oversell. MES has the same blind spot: it reads on-hand and consumes at
backflush, with no prior commitment of components to a production order.

Introduce a **native ERP reservation** so that `available = on_hand − active reservations`. This
(a) closes the existing back-office oversell gap, (b) lets MES commit BOM components to a production
order, and (c) gives Shop a reliable number to reserve at checkout. Reservation is an ERP/inventory
concept justified on its own; Shop is merely one future consumer, so the dependency direction stays
Shop → ERP and MES → ERP.

## 2. Scope and non-goals

**v1 (this design):** reservation mechanism owned by ERP; sales-order reservation (soft at order
placement, hard at confirm); MES production-order material reservation; advisory-locked availability;
the availability read consumed by MES and (later) Shop.

**Non-goals (deferred):**
- **Incoming-stock ATP** (`on_hand + on_order − reservations`): v1 availability is `on_hand − reservations`
  only. Promising against inbound purchase/production is a later phase.
- **Per-warehouse ATP and allocation strategy**: v1 availability is item-level company-wide; which
  warehouse ships stays an evasion concern (unchanged).
- **The not-fulfillable-remainder refund/backorder policy**: that is a Shop merchant policy (§7), not
  reservation. ERP's existing `PartiallyEvased` is the mechanism; the choice of what to do with the
  remainder belongs to the consumer.
- **Shop's own consumer wiring** (reserve at Draft, promote/release per E27/E28/E29): lives in the Shop
  plan; this spec only exposes the service it will call.

## 3. Locked decisions

| # | Decision | Rationale |
|---|----------|-----------|
| R1 | **ERP owns a generic reservation mechanism keyed by an opaque polymorphic source** (`source_type` morph alias + `source_id`, e.g. `erp.sales_order_line`, `mes.production_order_material`). ERP **never imports or resolves MES (or Shop) classes**: availability is a pure sum of quantities and never loads the source model, and each module creates / releases / consumes its own reservations through the ERP service and owns its documents' lifecycle. | Keeps the mechanism single and reusable while preserving the one-way dependencies (MES → ERP, Shop → ERP). The source is data, not a class ERP depends on. |
| R2 | **A dedicated `erp_stock_reservations` table, not a `reserved_quantity` column on `StockLevel`.** `available(item) = on_hand(item) − Σ quantity of its active reservations`. | A per-document, soft/hard, TTL-able, releasable, auditable reservation cannot be expressed by an aggregate counter; a table composes with the existing `StockMovement` ledger. |
| R3 | **Availability is item-level, company-wide in v1; `warehouse_id` on a reservation is nullable (optional pinning).** `available(item) = Σ on_hand(item, all warehouses of the company) − Σ active reservations(item)`. The warehouse is fixed only at evasion, as today; a module that must pin a location (MES, or the delivery note) may set `warehouse_id`. | The storefront/sales question is "is there one anywhere in this company's stock?". Item and `StockLevel` are company-scoped (`BelongsToCompany`), so the same SKU never spans sellers — a multi-vendor product is distinct `Item`s per company, each with independent availability. Per-warehouse allocation is a separate, deferred concern. |
| R4 | **Lifecycle: `soft` → `hard` → `consumed`, with `released` as the alternative terminal.** `soft` carries `expires_at`; `hard` does not. `hard`-at-confirm is **automatic for every sales order** (this is the back-office oversell fix); `soft`-at-placement is **caller-initiated** (the Shop checkout flow holding stock during the payment window), not forced on every Draft. Transitions: create `soft` at placement (`expires_at = now + TTL`); `soft → hard` at `Confirmed` is **best-effort** — reserve up to the currently available quantity and **never block the confirm**; the unreserved remainder is backorder / make-to-order (MES plans production for it), and overselling stays impossible because a reservation never exceeds on-hand; `soft → released` on TTL expiry (job) or Draft cancel; `hard → consumed` at evasion (one `StockMovement`); **partial evasion** consumes the shipped quantity and leaves the remainder `hard`; `hard → released` on order `Cancelled` or `Amended`-down (quantity reduced); an amend-up adds a new reservation. `soft` and `hard` count as active for availability; `consumed` (already reflected in the on-hand drop) and `released` do not. | Soft-at-placement eliminates Shop's payment-window race (E28/E29) without forcing a hold on manual back-office drafts; best-effort hard-at-confirm fixes the existing oversell gap **without blocking make-to-order manufacturing** — a confirm fires to trigger production of not-yet-existing stock, so it cannot require stock on hand. Shop's paid-but-unfulfillable gate (E29) is enforced in Shop's checkout, not the ERP confirm. **Amended 2026-10-06** from an earlier "confirm fails if stock is gone" after that wording broke the shipped MES make-to-order flow. |
| R5 | **Concurrency: an advisory lock on `(company_id, item_id)` around the check-and-insert**, using Core's existing advisory-lock policy. Under the lock, recompute `available`, and only insert the reservation if `available ≥ requested`. | Reserving is a critical section; an advisory lock keyed on the item serializes only concurrent reservers of the **same** item (minimal contention) and makes over-reservation impossible deterministically. Optimistic/retry is more error-prone; no-lock reconciliation reintroduces overselling. |
| R6 | **MES production-order material reservation uses the same service.** When a production order reaches its committed state it reserves its BOM components (`hard`, source `mes.production_order_material`); the reservation is released on PO cancel and **consumed by the existing `BackflushMaterialsJob`** (which then records the `StockMovement` and keeps its partial-consume + variance + `MaterialShortageDetected` behaviour for any physical shortfall). MES registers its source alias and calls the ERP service; ERP learns nothing about MES. | One mechanism for sales and production. Reserving at PO commit surfaces a component shortage at planning time (earlier than today's backflush-time detection), improving MES, while the existing non-blocking shortfall handling is preserved. |
| R7 | **Boundary: the not-fulfillable remainder is a consumer policy, not reservation.** When physical stock is short at evasion despite a `hard` reservation (shrinkage, damage, miscount — not a reservation fault), ERP's `PartiallyEvased` ships what exists and leaves the remainder open. What to do with the remainder — backorder (keep open), cancel remainder + partial refund, or cancel + full refund — is decided and actioned by the consumer (for Shop, a configurable merchant policy using PSP refunds, E14/E28), outside this spec. | Reservation minimises the shortfall but cannot abolish physical discrepancies; the refund/backorder choice is business/channel policy and touches payment, which ERP's reservation layer must not own. |
| R8 | **The availability read is the single contract consumers depend on.** A `StockReservationService` exposes `reserve(source, item, qty, mode, warehouse?, ttl?)`, `release(source)`, `consume(source, qty)`, and `available(item, company)`. MES's existing `StockReader`/`ErpStockReader` and Shop's future `ProductAvailabilityService` read `available` through it, so "available" means `on_hand − reservations` everywhere, not raw on-hand. | A single source of truth for availability avoids each consumer re-deriving it and drifting; it is also where the advisory lock and the reservation sum live. |

## 4. Data model (draft)

`erp_stock_reservations`:
- `id`, `company_id` (`BelongsToCompany`), `item_id` FK → `erp_items`, `warehouse_id` **nullable** FK → warehouses
- `source_type` (morph alias string), `source_id` — the opaque owning document (never resolved by ERP for availability)
- `quantity` decimal
- `state` enum `soft` / `hard` / `consumed` / `released`
- `expires_at` nullable (set for `soft`, cleared on promotion to `hard`)
- timestamps; indexes on `(company_id, item_id, state)` for the availability sum, and on `(source_type, source_id)` for lifecycle lookups

`available(item)` = `StockLevel.quantity` summed over the company's warehouses for the item, minus the
sum of `quantity` over reservations of that item in state `soft` or `hard`.

No change to `StockLevel` (still the on-hand truth) or `StockMovement` (still the realized-movement
ledger); a `consumed` reservation produces exactly one outbound `StockMovement`.

## 5. ERP integration points (sales)

- **Order placement → `soft`**: caller-initiated (the Shop checkout flow). Not automatic on every Draft,
  so manual back-office drafts are not force-held.
- **`SalesOrderConfirmed` → promote/create `hard`**: automatic for every sales order (a listener on the
  existing event), **best-effort** — reserve up to the available quantity, never blocking the confirm;
  the unreserved remainder is backorder / make-to-order. The listener pre-clamps to availability so it
  never triggers `reserve()`'s insufficient-stock throw.
- **Evasion (delivery note) → `consume`**: the evasion service consumes the hard reservation for the
  shipped quantity (one `StockMovement`), leaving any remainder `hard` on a partial evasion.
- **`Cancelled` / `Amended` → `release`/adjust**: releasing the hard reservation (or reducing it on an
  amend-down; a new reservation on an amend-up).

## 6. MES integration points (materials)

- **Production order committed → `hard`** reservation of BOM components (source `mes.production_order_material`),
  via the ERP service; a shortage surfaces here (planning time) through MES's existing shortage event.
- **Backflush → `consume`**: `BackflushMaterialsJob` consumes the reservation and records the outbound
  `StockMovement`, preserving its current partial-consume + variance + `MaterialShortageDetected` path
  for any physical shortfall.
- **PO cancel → `release`**.

## 7. Consumer boundary (Shop and others)

Shop reserves `soft` at Draft placement and relies on the promotion to `hard` at payment capture
(E27/E28/E29); the not-fulfillable-remainder policy (backorder vs refund) is Shop's, configurable, and
out of this spec. Any future consumer uses the same `StockReservationService` contract.

## 8. Ready criteria (before implementation)

- The `StockReservationService` contract (`reserve`/`release`/`consume`/`available`) is agreed and its
  advisory-lock key confirmed against Core's locking policy.
- The exact sales-order and production-order statuses that trigger create/promote/release/consume are
  pinned to the real enums and events (`SalesOrderConfirmed` exists; the evasion and cancel/amend hooks
  are identified; the MES production-order committed status is identified).
- The TTL default is a config value; its value is above the payment-window.
- The availability read is routed through the service for MES (`StockReader`) and documented for Shop.

## 9. Open questions (settle at plan time)

- **MES trigger status**: the exact production-order status at which components reserve (`released` vs an
  equivalent), and how it interacts with the existing backflush partial-consume/variance path.
- **Amend semantics**: amend-up as a new reservation vs growing the existing one; amend-down partial
  release ordering relative to any partial evasion already consumed.
- **Soft reservation garbage collection**: the expiry job cadence and whether expiry is lazy (filtered
  out of the availability sum by `expires_at`) in addition to the sweep.
- **Incoming-stock ATP**: confirm it stays out of v1.
