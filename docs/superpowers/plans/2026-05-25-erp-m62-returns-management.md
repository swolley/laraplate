# ERP M6.2 - Returns Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> or `superpowers:executing-plans`.

**Goal:** Add customer and supplier return records with explicit inventory effects, without
assuming delivery notes already support inbound/outbound direction.

**Architecture:** Return orders are new ERP entities. The first implementation uses dedicated
return services for orchestration. Delivery-note reuse is allowed only after adding explicit schema
support and tests for inbound returns.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest, Eloquent.

---

## Current Truth

- `DeliveryNote` has no `direction` column.
- `DeliveryNoteInventoryService::postInventory()` posts outbound stock.
- There is no delivery-note direction enum.
- `CreditNoteService` exposes `createFromInvoice()`, not `createFrom()`.
- `Invoice` currently has no `party_id`; return credit/debit note creation must work with the
  existing invoice schema or add the missing commercial link in a separate approved step.

## Task 1: Choose And Implement Return Schema

**Files:**
- Modify: `Modules/ERP/app/Enums/ERPTables.php`
- Create migrations under `Modules/ERP/database/migrations/`
- Create models under `Modules/ERP/app/Models/`
- Create `Modules/ERP/app/Casts/ReturnStatus.php`

**Tables:**
- `erp_return_orders`
- `erp_return_order_lines`
- `erp_supplier_returns`
- `erp_supplier_return_lines`

**Rules:**
- Add all new tables to `ERPTables`.
- Use `ERPMigrateUtils::companyForeign()` for company-scoped tables.
- Use `ERPTables::*->value` and `CoreTables::*->value` in all migrations and model rules.
- Add `BelongsToCompany` to header models.
- Use `VersionStrategy::DIFF` on new ERP models if sibling fiscal/operational models do.

**Do not add `return_order_id` or `supplier_return_id` to `erp_delivery_notes` yet** unless the
same task also adds a tested DDT direction/inbound model. Otherwise returns should keep their own
header-to-line records and inventory posting should be direct.

## Task 2: Customer Return Service

**Files:**
- Create: `Modules/ERP/app/Services/Returns/ReturnOrderService.php`
- Create: `Modules/ERP/tests/Feature/Services/ReturnOrderServiceTest.php`

**Behavior:**
- `approve(ReturnOrder $order): void`
  - valid only from `Draft`;
  - validates that the party is a customer;
  - sets status to `Approved`.
- `complete(ReturnOrder $order): ?Invoice`
  - valid only from `Approved`;
  - records inbound stock through `StockMovementService::recordInbound()` for each line;
  - sets status to `Completed`;
  - optionally creates a credit note only when an original posted invoice is linked and the
    existing `CreditNoteService::createFromInvoice()` contract can support the line overrides.

**Important:** Do not call `DeliveryNoteInventoryService::post()` or assume inbound DDT behavior.

## Task 3: Supplier Return Service

**Files:**
- Create: `Modules/ERP/app/Services/Returns/SupplierReturnService.php`
- Create: `Modules/ERP/tests/Feature/Services/SupplierReturnServiceTest.php`

**Behavior:**
- `approve(SupplierReturn $return): void`
  - valid only from `Draft`;
  - validates that the party is a supplier;
  - sets status to `Approved`.
- `complete(SupplierReturn $return): ?Invoice`
  - valid only from `Approved`;
  - records outbound stock through `StockMovementService::recordOutbound()`;
  - sets status to `Completed`;
  - debit-note creation is optional and should be skipped unless existing invoice/debit-note
    contracts support it without inventing fields.

## Task 4: Optional Delivery Note Integration

This task is optional and must be implemented only if the team chooses DDT-based returns.

If enabled:
- Add a `direction` column to `erp_delivery_notes` with a new cast enum.
- Extend `DeliveryNoteInventoryService` to support inbound and outbound paths.
- Add tests proving customer returns restore stock and supplier returns deduct stock through DDT.
- Update Filament forms to expose direction only where appropriate.

If not enabled:
- Return resources should link to return headers and stock movements, not DDTs.

Default for this plan: **skip DDT direction in M6.2 v1**.

## Task 5: Filament Resources

**Files:**
- Create resources under `Modules/ERP/app/Filament/Resources/ReturnOrders/`
- Create resources under `Modules/ERP/app/Filament/Resources/SupplierReturns/`

**UI:**
- Header forms include company, party, reason, status, and source document links.
- Line repeaters include item, quantity, unit price, and optional source line links.
- Actions: `Approve`, `Complete`, `Cancel`.
- Actions must authorize through M4 custom permissions if those are available; otherwise use
  existing table permissions as a temporary guard.

## Test Plan

Run:

```bash
php artisan test --compact Modules/ERP/tests/Feature/Services/ReturnOrderServiceTest.php
php artisan test --compact Modules/ERP/tests/Feature/Services/SupplierReturnServiceTest.php
vendor/bin/pint --dirty
```

Test scenarios:
- Schema uses `ERPTables` and `erp_*`.
- Customer return approval and completion state transitions.
- Inbound stock movement is recorded for customer returns.
- Supplier return approval and completion state transitions.
- Outbound stock movement is recorded for supplier returns.
- Optional credit note is created only for supported posted source invoices.

## Assumptions

- Dedicated return services are safer than retrofitting DDT direction in v1.
- Debit-note automation can be added after invoice-party/source-document modeling is clearer.
- DDT-based returns are a follow-up option, not a hidden requirement.

## Implementation Status / Verification

- Implemented first development slice: customer return and supplier return tables, line models,
  shared `ReturnStatus`, inbound customer-return receipt service, and outbound supplier-return
  shipment service.
- Filament resources and complete actions are implemented for return orders and supplier returns.
- Verified on 2026-05-29:
  - `php artisan test --compact Modules/ERP/tests/Feature/Services/ReturnOrderServiceTest.php Modules/ERP/tests/Feature/Services/SupplierReturnServiceTest.php`
  - included in the combined ERP focused command documented in the final verification note
  - `php artisan migrate --pretend --no-interaction` -> `Nothing to migrate`
  - `vendor/bin/pint --dirty`
- Follow-up: optional credit/debit-note automation remains deferred until invoice source-party
  modeling is explicit.
