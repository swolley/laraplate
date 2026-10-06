# Shop — Catalog Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Shop catalog core — the `Product` anchor that extends a CMS `Content` through the shipped content-extension seam, plus variants and the item composition pivot — so a product is creatable, hidden from generic CMS reads by default, and upcastable from its content.

**Architecture:** `Product` is an anchor table (`shop_products`) that **consumes the CMS seam** (`ExtendsContent` + `ExtendsContentTrait`) with alias `shop.product`, and **transparently merges its `Content`** onto itself following the `Contributor`↔`User` pattern (spec E2a). Variants (`shop_product_variants`) are the sellable unit; a pivot (`shop_variant_items`) composes each variant from ERP `Item`s. No stock, cart, payment, or storefront code here.

**Tech Stack:** PHP 8.5, Laravel 12, `nwidart/laravel-modules`, Pest, the CMS content-extension seam (shipped), ERP `Item`.

**Spec:** `docs/superpowers/specs/2026-09-17-shop-module-design.md`

## Shop plan decomposition (roadmap)

The Shop spec is a whole module; it is built as a sequence of independently shippable plans. This plan is **#1**.

1. **Catalog foundation** (this plan): `Product` anchor + seam consumption + variants + `variant_items` + `kind` + product content-Entity seed.
2. **Storefront browsing**: CMS `Category` under the product entity, per-category `Preset` (E20), facets/layered navigation, the browse price/stock snapshot (E21), `/app` read endpoints.
3. **Cart & checkout → ERP order**: `shop_carts`, checkout session, cart merge, `Party` find-or-create, Draft `SalesOrder` (E27/E28), check-at-checkout availability (E29 interim); reserves via ERP reservation once it ships (ERP-4).
4. **Payments (PSP)**: `PaymentDriver` + `PaymentDriverRegistry`, Stripe/PayPal drivers, webhook reconciliation (E30), `shop_payment_reconciliations`, Draft→Confirmed.
5. **Digital fulfilment**: `shop_download_grants`, entitlement on reconciliation, gated download (E26).
6. **Reviews**: verified-purchase gate over CMS comments/ratings (E10).
7. **Order & delivery read models** (E15); 8. **Support via SAO** (E9).

## Global Constraints

- Every PHP file `declare(strict_types=1);`; braces everywhere; explicit types; `#[Override]` on overrides; `final` where siblings are final.
- Shop models extend `Modules\Core\Overrides\Model`; table names from a new `Modules\Shop\Enums\ShopTables` (using `Core\Enums\Concerns\HasModuleTablesUtils`, prefix `shop_`); every table `hasSoftDelete: true`; migrations via `MigrateUtils`; validations in `getRules()`; permissions only from `Core\Support\PermissionName`. Table-prefix/model conventions per spec §4.
- `Product` consumes the shipped seam: implements `Modules\CMS\Contracts\ExtendsContent`, uses `Modules\CMS\Models\Concerns\ExtendsContentTrait`; the alias `shop.product` is registered in the `ContentExtenderRegistry` from `ShopServiceProvider::register()`. ERP `Item` is **not** merged (E2a) — it stays an explicit relation via the variant pivot.
- `content_id` is **NOT NULL and UNIQUE** (one extender per content, seam C15 / E16); product↔content lifecycle is symmetric (E17) — provided by the seam, not re-implemented here.
- Shop `module.json` already declares `requires: Core, CMS, ERP, SAO`; `ShopServiceProvider` extends `Core\Overrides\ModuleServiceProvider` (dependency check at boot). Do not re-scaffold these.
- Tests are Pest feature tests under `Modules/Shop/tests/`, using factories and a `setupShopEntities()` helper (Task 1); no classes declared inside test files (stubs under `Modules/Shop/tests/Stubs/`, PSR-4 in `composer.json`). Run `vendor/bin/pint --dirty` from the laraplate root before each commit.

## Review Focus

- **Product-content leaking into generic CMS reads** — a `Content` extended by a `Product` must be absent from every default CMS content query (the seam's global scope); regression if a new read path forgets it (Task 2 test `an extended product content is hidden from a plain Content query`).
- **N+1 on a product listing** — upcasting a page of product contents must be two queries, not one-per-row (Task 2 test `a product listing upcasts in a constant number of queries`).
- **Two products on one content** — the `content_id` unique constraint must reject a second `Product` for the same `Content` (Task 2 test `a content cannot be extended by two products`).
- **Symmetric delete** — deleting a `Product` cascades to its `Content` and deleting the `Content` cascades to the `Product` (no orphan; E17) (Task 2 test `deleting a product deletes its content and vice versa`).
- **Item-less vs display-only vs physical** — a variant with zero pivot rows is digital-or-display-only by `kind`, not "broken"; a physical product resolves its item composition (Task 4 tests `a simple variant has one main item`, `a bundle variant has component items`, `a digital product has no variant items`).

---

### Task 1: `ShopTables` + `ProductKind` enums, product content-Entity seed, test helper

**Files:**
- Create: `Modules/Shop/app/Enums/ShopTables.php` (cases `Products='shop_products'`, `ProductVariants='shop_product_variants'`, `VariantItems='shop_variant_items'`)
- Create: `Modules/Shop/app/Enums/ProductKind.php` (`Physical='physical'`, `Digital='digital'`, `DisplayOnly='display_only'`, `validationRule()`)
- Modify: `Modules/Shop/database/seeders/ShopDatabaseSeeder.php` (seed a `shop products` content `Entity` + a default `Preset`, via Core's `model:create-entity` / the Entity mechanism CMS uses)
- Create: `Modules/Shop/tests/Pest.php` with `setupShopEntities()` (mirror `Modules/CMS/tests/Pest.php::setupCMSEntities`, seeding the product entity + preset so a product `Content` is creatable)
- Test: `Modules/Shop/tests/Feature/CatalogSetupTest.php`

**Interfaces:**
- Produces: `ShopTables`, `ProductKind`, and `setupShopEntities()` — later tasks call it before creating a `Product`.

- [ ] **Step 1: Write the failing test** — `CatalogSetupTest`: after `setupShopEntities()`, a product content `Entity` and a default `Preset` exist and a `Content` can be created against them; `ShopTables::Products->value === 'shop_products'`; `ProductKind` has the three cases.
- [ ] **Step 2: Run it, verify it fails.**
- [ ] **Step 3: Implement** the two enums, the seeder entry (follow how CMS seeds its entities; a product entity is Shop-owned, not a CMS `EntityType` case — E20), and `setupShopEntities()`.
- [ ] **Step 4: Run it, verify it passes.**
- [ ] **Step 5: Commit** — `feat(shop): shop table + product-kind enums and product content entity seed`.

---

### Task 2: `shop_products` + `Product` (seam consumer, transparent content merge)

**Files:**
- Create: `Modules/Shop/database/migrations/2026_10_06_000100_create_shop_products_table.php`
- Create: `Modules/Shop/app/Models/Product.php`
- Create: `Modules/Shop/database/factories/ProductFactory.php`
- Modify: `Modules/Shop/app/Providers/ShopServiceProvider.php` (register `shop.product => Product` in `ContentExtenderRegistry` inside `register()`)
- Test: `Modules/Shop/tests/Feature/ProductExtensionTest.php`

**Interfaces:**
- Consumes: `ExtendsContent`, `ExtendsContentTrait`, `ContentExtenderRegistry`, CMS `Content`, `setupShopEntities()` (Task 1).
- Produces: `Product` — `#[Override] contentAlias(): string` returns `'shop.product'`; `searchableExtension()`/`searchableExtensionMapping()` expose `kind`, `is_published_in_shop`, `featured`; columns `company_id` (`BelongsToCompany`, E23), `content_id` (NOT NULL, UNIQUE), `kind` (`ProductKind`), `is_published_in_shop` bool, `featured` bool, `release_date` nullable, `metadata` json; transparent `Content` merge per E2a (`$with = ['content']`, `setTempContent()`, `save()` override — from `ExtendsContentTrait`, mirroring `Contributor`). Relation `content(): BelongsTo` comes from the trait.

- [ ] **Step 1: Write the failing tests** — `ProductExtensionTest` (call `setupShopEntities()` first):
  - `an extended product content is hidden from a plain Content query` (create a `Product`; `Content::query()->get()` excludes its content).
  - `a product listing upcasts in a constant number of queries` (create N products; assert the entity-scoped upcast is 2 queries regardless of N).
  - `a content cannot be extended by two products` (second `Product` on the same `content_id` → QueryException/unique violation).
  - `deleting a product deletes its content and vice versa` (E17 symmetric cascade).
  - `product reads and writes merged content fields` (set a content-owned attribute on the `Product`, `save()`, reload → persisted on the `Content`).
- [ ] **Step 2: Run them, verify they fail.**
- [ ] **Step 3: Implement** the migration (`MigrateUtils`, `company_id` FK, `content_id` FK to `cms_contents` NOT NULL + unique index, `kind` string, flags, `release_date` date nullable, `metadata` json nullable, soft deletes), the `Product` model (seam trait + `BelongsToCompany` + transparent merge following `Modules/CMS/app/Models/Contributor.php` and E2a), the factory (creates the backing `Content` via `setTempContent`), and the alias registration in `ShopServiceProvider::register()`.
- [ ] **Step 4: Run them, verify they pass.**
- [ ] **Step 5: Commit** — `feat(shop): product anchor extending a CMS content via the seam`.

---

### Task 3: `shop_product_variants` + `ProductVariant`

**Files:**
- Create: `Modules/Shop/database/migrations/2026_10_06_000200_create_shop_product_variants_table.php`
- Create: `Modules/Shop/app/Models/ProductVariant.php`
- Create: `Modules/Shop/database/factories/ProductVariantFactory.php`
- Modify: `Modules/Shop/app/Models/Product.php` (add `variants(): HasMany`, `defaultVariant(): HasOne`)
- Test: `Modules/Shop/tests/Feature/ProductVariantTest.php`

**Interfaces:**
- Consumes: `Product` (Task 2).
- Produces: `ProductVariant` — columns `product_id` FK, `is_default` bool, `attributes` json (size/colour, label-only per E22), soft deletes; no `company_id` (derived from `product`, E23). `Product::variants()`/`defaultVariant()`.

- [ ] **Step 1: Write the failing tests** — a product has one or more variants; exactly one `is_default`; `attributes` round-trips as an array cast; a variant has no `company_id` column.
- [ ] **Step 2: Run them, verify they fail.**
- [ ] **Step 3: Implement** migration, model (casts `attributes` array), factory, and the `Product` relations.
- [ ] **Step 4: Run them, verify they pass.**
- [ ] **Step 5: Commit** — `feat(shop): product variants`.

---

### Task 4: `shop_variant_items` + `VariantItem` composition

**Files:**
- Create: `Modules/Shop/database/migrations/2026_10_06_000300_create_shop_variant_items_table.php`
- Create: `Modules/Shop/app/Models/VariantItem.php`
- Create: `Modules/Shop/database/factories/VariantItemFactory.php`
- Modify: `Modules/Shop/app/Models/ProductVariant.php` (add `items(): HasMany` to `VariantItem`, and `erpItems()` through the pivot)
- Test: `Modules/Shop/tests/Feature/VariantCompositionTest.php`

**Interfaces:**
- Consumes: `ProductVariant` (Task 3), ERP `Item`.
- Produces: `VariantItem` — columns `variant_id` FK, `item_id` FK → `erp_items`, `quantity` decimal(15,4), `role` string (closed set `main`/`component`, E31), soft deletes. Composition rules (E7b): one row = simple, several = bundle, zero = item-less (digital/display-only by product `kind`).

- [ ] **Step 1: Write the failing tests** — `a simple variant has one main item`; `a bundle variant has component items`; `a digital product has no variant items` (product `kind = digital`, variant with zero rows is valid); `role` rejects a value outside `main`/`component`.
- [ ] **Step 2: Run them, verify they fail.**
- [ ] **Step 3: Implement** migration (FK to `erp_items`; index on `variant_id`), model (`quantity` decimal cast, `role` validated in `getRules()`), factory, and the `ProductVariant` relations.
- [ ] **Step 4: Run them, verify they pass.**
- [ ] **Step 5: Commit** — `feat(shop): variant-to-item composition pivot`.

---

### Task 5: Documentation and foundation gate

**Files:**
- Modify: `Modules/Shop/docs/rag/MODULE.md` (catalog model: product anchor over the CMS seam, transparent content merge, variants, item composition, `kind`; what is NOT here yet — stock, cart, payments)
- Modify: `Modules/Shop/README.md` if any env/config was introduced
- Test: run the module suite once green.

- [ ] **Step 1: Write the `MODULE.md` catalog section** (present behaviour, not the plan).
- [ ] **Step 2: Run `php artisan test --compact Modules/Shop/tests`** and confirm green; then `vendor/bin/pint --dirty` from the root.
- [ ] **Step 3: Add this plan's `## Delivery status` + a `**Documented in:** Modules/Shop/docs/rag/MODULE.md` line** (required to close; enforced by `tests/Unit/ClosedPlansPointToDocumentationTest.php`).
- [ ] **Step 4: Commit** — `docs(shop): document the catalog foundation`.

---

## Notes / out of scope (later Shop plans)

- Stock/availability, cart, checkout, payments, digital fulfilment (`shop_download_grants`), reviews, read models, support — see the decomposition roadmap above. The Shop stock slice depends on the ERP reservation plan (`2026-10-06-erp-stock-reservation.md`, ERP-4).
- Storefront categories and per-category presets (E20) are plan #2, not here; this plan seeds only the minimal product content-Entity + a default Preset needed to create products.
