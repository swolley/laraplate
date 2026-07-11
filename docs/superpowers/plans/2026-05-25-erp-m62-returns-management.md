# ERP M6.2 - Returns Management Implementation Plan

> **Navigation:** M6.2 v1 is **implemented** (manual NC/ND). Open follow-ups (auto NC/ND) →
> Spec 2 Phase 2B in
> [`specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md`](../specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md).

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> or `superpowers:executing-plans`.

**Goal:** Add customer and supplier return records with explicit inventory effects, and keep
delivery-note inbound/outbound behavior explicit when DDTs are used for stock movement.

**Architecture:** Return orders are the canonical workflow documents. Delivery notes are used only
when a return has a physical stock movement: customer returns generate/link an inbound DDT, supplier
returns generate/link an outbound DDT. Delivery-note lines must not carry prices or costs; costing
stays in stock movements, stock cost layers, and return lines where a manual/customer-return cost is
needed.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest, Eloquent.

---

## Current Truth

- `DeliveryNote` has a `direction` column backed by `DeliveryNoteDirection`.
- `DeliveryNoteInventoryService::postInventory()` posts outbound stock with SO/COGS effects and
  inbound stock without SO/COGS effects.
- Inbound DDT posting derives inventory cost from the original outbound stock movement linked to
  the same sales order line; delivery note lines do not carry commercial prices or inventory costs.
- `ReturnOrderLine` may carry the inventory unit cost needed for a customer-return receipt when the
  original outbound movement cannot be resolved; DDT lines still remain quantity-only.
- `CreditNoteService` exposes `createFromInvoice()`, not `createFrom()`.
- `Invoice` has a nullable `party_id`; return credit/debit note automation still needs explicit
  source-document and line override contracts before it becomes automatic.

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

Return headers must remain the source of truth for approval, completion, cancellation, reason, and
source-document links. When DDT integration is implemented, add or keep an explicit relation between
the return header and the generated DDT, instead of hiding the physical movement behind anonymous
stock movements.

## Task 2: Customer Return Service

**Files:**
- Create: `Modules/ERP/app/Services/Returns/ReturnOrderService.php`
- Create: `Modules/ERP/tests/Feature/Services/ReturnOrderServiceTest.php`

**Behavior:**
- `approve(ReturnOrder $order): void`
  - valid only from `Draft`;
  - validates that the party is a customer;
  - sets status to `Approved`.
- `complete(ReturnOrder $order): ReturnOrder`
  - valid only from `Approved`;
  - generates or links an inbound DDT when the returned goods physically enter stock;
  - records inbound stock through the DDT inventory posting path or, if the DDT slice is not present
    yet, through an explicitly documented return-service fallback;
  - derives the returned inventory cost from the original outbound stock movement when the return is
    linked to a source sales order line;
  - requires an explicit manual cost on the return line when no original outbound movement can be
    resolved and the business user chooses to receive the item anyway;
  - sets status to `Processed`;
  - leaves credit-note creation as a manual follow-up action in v1, with optional automation only
    after source invoice and line override contracts are explicit.

**Important:** A return service may orchestrate the flow, but it must not silently post stock in a
way that bypasses the DDT document when the physical movement is meant to be represented by a DDT.

## Task 3: Supplier Return Service

**Files:**
- Create: `Modules/ERP/app/Services/Returns/SupplierReturnService.php`
- Create: `Modules/ERP/tests/Feature/Services/SupplierReturnServiceTest.php`

**Behavior:**
- `approve(SupplierReturn $return): void`
  - valid only from `Draft`;
  - validates that the party is a supplier;
  - sets status to `Approved`.
- `complete(SupplierReturn $return): SupplierReturn`
  - valid only from `Approved`;
  - generates or links an outbound DDT when goods leave stock toward the supplier;
  - records outbound stock through the DDT inventory posting path or, if the DDT slice is not present
    yet, through an explicitly documented return-service fallback;
  - sets status to `Processed`;
  - leaves debit-note creation as a manual follow-up action in v1, with optional automation only
    after source invoice and line override contracts are explicit.

## Task 4: Delivery Note Integration For Physical Returns

This task is required for a professional physical-return workflow, but it is separate from the return
header workflow:

- Keep `erp_delivery_notes.direction` with the `DeliveryNoteDirection` cast enum.
- Customer returns create/link an inbound DDT for the physical receipt.
- Supplier returns create/link an outbound DDT for the physical shipment.
- DDT lines contain item, warehouse, quantity, and source-line links only; never commercial prices or
  inventory costs.
- Inbound DDTs restore stock from the original outbound movement cost when possible and can be
  unposted.
- Return headers still own approval, reason, cancellation, and credit/debit follow-up decisions.

If the code does not yet link returns to DDTs, keep that as the next required M6.2 development slice
instead of treating M6.2 as complete.

## Task 5: Filament Resources

**Files:**
- Create resources under `Modules/ERP/app/Filament/Resources/ReturnOrders/`
- Create resources under `Modules/ERP/app/Filament/Resources/SupplierReturns/`

**UI:**
- Header forms include company, party, reason, status, and source document links.
- Line repeaters include item, quantity, warehouse, and optional source line links.
- Customer-return lines may expose inventory unit cost only when no source outbound movement can be
  resolved; supplier-return lines and all DDT lines do not expose prices or costs.
- Actions: `Approve`, `Complete`, `Cancel`.
- Actions must authorize through M4 custom permissions if those are available; otherwise use
  existing table permissions as a temporary guard.

## Test Plan

Run:

```bash
php artisan test --compact Modules/ERP/tests/Feature/Services/ReturnOrderServiceTest.php
php artisan test --compact Modules/ERP/tests/Feature/Services/SupplierReturnServiceTest.php
php artisan test --compact Modules/ERP/tests/Feature/DeliveryNoteInventoryServiceTest.php
vendor/bin/pint --dirty
```

Test scenarios:
- Schema uses `ERPTables` and `erp_*`.
- Customer return approval and completion state transitions.
- Customer return party must be a customer.
- Inbound stock movement is recorded for customer returns.
- Customer return completion generates or links an inbound DDT when physical stock is received.
- Customer return quantities are tracked against the original sales order or invoice lines when a
  source line is present.
- Supplier return approval and completion state transitions.
- Supplier return party must be a supplier.
- Outbound stock movement is recorded for supplier returns.
- Supplier return completion generates or links an outbound DDT when physical stock leaves.
- Supplier return quantities are tracked against the original purchase order or goods receipt lines
  when a source line is present.
- Inbound DDT posting records inbound stock and DDT unposting records the compensating outbound
  movement.
- Credit/debit note actions remain manual in v1 unless an explicit automation setting and line
  override contract are implemented.

## Assumptions

- Dedicated return services remain safer as the canonical return workflow.
- DDT direction is the right physical-document primitive for return stock movement.
- Credit/debit-note automation can be added after invoice source-document and line override modeling
  is clearer.
- Cost must stay out of delivery notes; it belongs to stock movements, cost layers, or explicit
  return-line inventory-cost handling when no source movement exists.

## Implementation Status / Verification

- Implemented first development slice: customer return and supplier return tables, line models,
  shared `ReturnStatus`, inbound customer-return receipt service, and outbound supplier-return
  shipment service.
- `ReturnOrderService` and `SupplierReturnService` now orchestrate approve, complete, and cancel
  transitions, validate customer/supplier party roles, and delegate inventory effects to the stock
  services.
- Filament resources and approve/complete/cancel actions are implemented for return orders and
  supplier returns; edit forms keep status read-only and filter parties by role.
- DDT direction support is implemented with `DeliveryNoteDirection`, cost resolution from
  original outbound stock movements, Filament DDT direction fields, and focused inventory tests.
- Implemented on 2026-06-10: customer and supplier return completion now generates/links posted
  delivery notes for the physical stock movement. Customer returns create inbound DDTs and supplier
  returns create outbound DDTs; DDT lines keep quantity/source links only and stock movements are
  posted through `DeliveryNoteInventoryService`.
- Implemented on 2026-06-18: returned-quantity tracking against invoice/sales-order and
  purchase-order/goods-receipt source lines is covered by return completion tests. Return edit pages
  now expose manual credit/debit-note follow-up actions; generated notes are linked back to the
  return headers through `credit_note_invoice_id` and `debit_note_invoice_id`.
- Verified on 2026-05-29:
  - `php artisan test --compact Modules/ERP/tests/Feature/Services/ReturnOrderServiceTest.php Modules/ERP/tests/Feature/Services/SupplierReturnServiceTest.php`
  - included in the combined ERP focused command documented in the final verification note
  - `php artisan migrate --pretend --no-interaction` -> `Nothing to migrate`
  - `vendor/bin/pint --dirty`
- Verified on 2026-05-30:
  - `php artisan test --compact Modules/ERP/tests/Feature/DeliveryNoteInventoryServiceTest.php Modules/ERP/tests/Feature/Filament/ERPFilamentCommercialResourcesTest.php Modules/ERP/tests/Feature/Filament/ERPFilamentRouteSmokeTest.php`
  - `php artisan test --compact Modules/ERP/tests/Feature/Services/ReturnOrderServiceTest.php Modules/ERP/tests/Feature/Services/SupplierReturnServiceTest.php Modules/ERP/tests/Feature/Filament/ERPFilamentCommercialResourcesTest.php Modules/ERP/tests/Feature/Filament/ERPFilamentRouteSmokeTest.php`
- Follow-up: optional automatic credit/debit-note creation remains deferred; v1 keeps the action
  manual and requires explicit source invoice or purchase-order line contracts before creating
  fiscal document drafts.
