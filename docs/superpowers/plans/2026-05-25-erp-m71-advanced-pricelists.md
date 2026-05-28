# ERP M7.1 - Advanced Pricelists Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> or `superpowers:executing-plans`.

**Goal:** Add temporal validity and party/global discount rules on top of the existing taxonomy
price-list model.

**Architecture:** Keep `PriceListItem` as the base price source. It currently prices by taxonomy,
not by direct item. `PriceResolverService` resolves an item's taxonomy, finds the active base price,
then applies the highest-priority valid discount rule.

**Tech Stack:** PHP 8.5, Laravel 12, Filament 5, Pest, Eloquent.

---

## Current Truth

- `PriceListItem` fields: `price_list_id`, `taxonomy_id`, `name`, `uom`, `unit_price`.
- `PriceListItem` does not currently have `item_id`.
- `Item` has nullable `taxonomy_id`.
- `PriceListItem` table name is `ERPTables::PriceListItems->value` (`erp_price_list_items`).
- New models should use existing module namespaces and `Modules\ERP\Concerns\BelongsToCompany`
  where company-scoped.

## Task 1: Schema

**Files:**
- Modify: `Modules/ERP/app/Enums/ERPTables.php`
- Modify: `Modules/ERP/database/migrations/2026_04_08_191729_create_price_list_items_table.php`
- Create: migration for `erp_party_price_rules`
- Create: `Modules/ERP/app/Casts/DiscountType.php`
- Create: `Modules/ERP/app/Models/PartyPriceRule.php`

**Changes:**
- Add `valid_from` and `valid_to` to `erp_price_list_items`.
- Add `ERPTables::PartyPriceRules = 'erp_party_price_rules'`.
- Create `erp_party_price_rules` with:
  - `company_id`
  - `party_id` nullable
  - `item_id` nullable
  - `taxonomy_id` nullable
  - `discount_type`
  - `discount_value` JSON
  - `priority`
  - `valid_from`
  - `valid_to`
  - timestamps

**Rules:**
- `item_id` and `taxonomy_id` cannot both be non-null.
- At least one of `item_id` or `taxonomy_id` should normally be set; allow both null only if a
  deliberate global-all-items discount is implemented and tested.
- Migrations and rules use `ERPTables::*->value` and `CoreTables::Taxonomies->value`.

## Task 2: Data Object

**Files:**
- Create: `Modules/ERP/app/Data/Pricing/PriceResolutionResult.php`

**Shape:**

```php
final readonly class PriceResolutionResult
{
    public function __construct(
        public string $unitPrice,
        public string $discountPercentEffective,
        public string $source,
        public bool $noPriceFound,
        public ?string $ruleDescription = null,
    ) {}
}
```

Use strings for money/decimal values to match the surrounding accounting style and avoid float
surprises in service internals.

## Task 3: PriceResolverService

**Files:**
- Create: `Modules/ERP/app/Services/Pricing/PriceResolverService.php`
- Create: `Modules/ERP/tests/Feature/Services/PriceResolverServiceTest.php`

**Method:**

```php
public function resolve(int $itemId, int $partyId, string $quantity = '1.0000', ?CarbonImmutable $date = null): PriceResolutionResult;
```

**Resolution order:**
- Load `Item` and its `taxonomy_id`.
- Find an active base price from `PriceListItem` through taxonomy and active date window.
- If no base price exists, return `noPriceFound = true`.
- Apply the first valid rule by priority in this order:
  - party + item;
  - party + taxonomy;
  - global + item;
  - global + taxonomy;
  - optional global all-items rule only if explicitly implemented.
- Discount types:
  - `percentage`: base price multiplied by `(1 - value / 100)`;
  - `fixed`: `max(0, base - value)`;
  - `cascade`: apply each percentage sequentially.

**Constraints:**
- Do not assume `PriceListItem::item_id`.
- Do not assume ERP factories; tests create `Company`, `Party`, `Item`, `PriceList`, and
  `PriceListItem` manually.
- Query `PriceListItem` through `price_list` to scope by company.

## Task 4: Party Filament Integration

**Files:**
- Modify: `Modules/ERP/app/Models/Party.php`
- Modify existing Party resource files under `Modules/ERP/app/Filament/Resources/Parties/`

**Behavior:**
- Add `Party::price_rules()` relation.
- Add a "Price Rules" section or tab using the existing resource schema style.
- Rule form fields:
  - optional item;
  - optional taxonomy;
  - discount type;
  - discount value;
  - priority;
  - valid from/to.
- Validate item/taxonomy exclusivity at model and form level.

## Task 5: Sales Form Defaults

**Files:**
- Modify existing schema classes, not root resource classes, where line repeaters are defined:
  - `Modules/ERP/app/Filament/Resources/Quotations/Schemas/QuotationForm.php`
  - `Modules/ERP/app/Filament/Resources/SalesOrders/Schemas/SalesOrderForm.php`
  - `Modules/ERP/app/Filament/Resources/Invoices/Schemas/InvoiceForm.php`

**Behavior:**
- When an item/taxonomy selection changes and party context is available, call
  `PriceResolverService`.
- Set `unit_price` only when the user has not already entered a value.
- If party context is unavailable, leave price unchanged.
- Use existing Filament v5 schema utilities already imported in sibling files.
- Avoid brittle `$get('../../party_id')` assumptions unless confirmed against the actual form
  nesting; prefer helper methods if needed.

## Test Plan

Run:

```bash
php artisan test --compact Modules/ERP/tests/Feature/Services/PriceResolverServiceTest.php
vendor/bin/pint --dirty
```

Test scenarios:
- Active price-list item by taxonomy returns base price.
- Expired price-list item is ignored.
- Party taxonomy rule overrides base price.
- Party item rule overrides taxonomy rule when item rules are enabled.
- Global taxonomy rule applies when no party rule exists.
- Cascade discount math is deterministic.
- Invalid rule with both item and taxonomy fails validation.

## Assumptions

- Taxonomy-level pricing remains the base model for M7.1.
- Direct item-specific rules are allowed in `party_price_rules`, but direct item-specific
  `price_list_items` are not added in this milestone.
- Quantity tiers are out of scope.

## Implementation Status / Verification

- Implemented first development slice: validity columns for price-list items, `party_price_rules`,
  `DiscountType`, `PartyPriceRule`, `PriceResolutionResult`, and `PriceResolverService`.
- Sales order line form integration is implemented through `PriceResolverService`.
- Quotation line form integration is implemented through the existing `price_list_item_id` source.
- Invoice form integration remains deferred because the current invoice line schema does not expose
  a direct item or price-list source.
- Verified on 2026-05-29:
  - `php artisan test --compact Modules/ERP/tests/Feature/Services/PriceResolverServiceTest.php`
  - included in the combined ERP focused command documented in the final verification note
  - `php artisan migrate --pretend --no-interaction` -> `Nothing to migrate`
  - `vendor/bin/pint --dirty`
