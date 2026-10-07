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

- [x] **Step 1: Write the failing test** — `CatalogSetupTest`: after `setupShopEntities()`, a product content `Entity` and a default `Preset` exist and a `Content` can be created against them; `ShopTables::Products->value === 'shop_products'`; `ProductKind` has the three cases.
- [x] **Step 2: Run it, verify it fails.**
- [x] **Step 3: Implement** the two enums, the seeder entry (follow how CMS seeds its entities; a product entity is Shop-owned, not a CMS `EntityType` case — E20), and `setupShopEntities()`.
- [x] **Step 4: Run it, verify it passes.**
- [x] **Step 5: Commit** — `feat(shop): shop table + product-kind enums and product content entity seed`.

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

- [x] **Step 1: Write the failing tests** — `ProductExtensionTest` (call `setupShopEntities()` first):
  - `an extended product content is hidden from a plain Content query` (create a `Product`; `Content::query()->get()` excludes its content).
  - `a product listing upcasts in a constant number of queries` (create N products; assert the entity-scoped upcast is 2 queries regardless of N).
  - `a content cannot be extended by two products` (second `Product` on the same `content_id` → QueryException/unique violation).
  - `deleting a product deletes its content and vice versa` (E17 symmetric cascade).
  - `product reads and writes merged content fields` (set a content-owned attribute on the `Product`, `save()`, reload → persisted on the `Content`).
- [x] **Step 2: Run them, verify they fail.**
- [x] **Step 3: Implement** the migration (`MigrateUtils`, `company_id` FK, `content_id` FK to `cms_contents` NOT NULL + unique index, `kind` string, flags, `release_date` date nullable, `metadata` json nullable, soft deletes), the `Product` model (seam trait + `BelongsToCompany` + transparent merge following `Modules/CMS/app/Models/Contributor.php` and E2a), the factory (creates the backing `Content` via `setTempContent`), and the alias registration in `ShopServiceProvider::register()`.
- [x] **Step 4: Run them, verify they pass.**
- [x] **Step 5: Commit** — `feat(shop): product anchor extending a CMS content via the seam`.

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

- [x] **Step 1: Write the failing tests** — a product has one or more variants; exactly one `is_default`; `attributes` round-trips as an array cast; a variant has no `company_id` column.
- [x] **Step 2: Run them, verify they fail.**
- [x] **Step 3: Implement** migration, model (casts `attributes` array), factory, and the `Product` relations.
- [x] **Step 4: Run them, verify they pass.**
- [x] **Step 5: Commit** — `feat(shop): product variants`.

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

- [x] **Step 1: Write the failing tests** — `a simple variant has one main item`; `a bundle variant has component items`; `a digital product has no variant items` (product `kind = digital`, variant with zero rows is valid); `role` rejects a value outside `main`/`component`.
- [x] **Step 2: Run them, verify they fail.**
- [x] **Step 3: Implement** migration (FK to `erp_items`; index on `variant_id`), model (`quantity` decimal cast, `role` validated in `getRules()`), factory, and the `ProductVariant` relations.
- [x] **Step 4: Run them, verify they pass.**
- [x] **Step 5: Commit** — `feat(shop): variant-to-item composition pivot`.

---

### Task 5: Documentation and foundation gate

**Files:**
- Modify: `Modules/Shop/docs/rag/MODULE.md` (catalog model: product anchor over the CMS seam, transparent content merge, variants, item composition, `kind`; what is NOT here yet — stock, cart, payments)
- Modify: `Modules/Shop/README.md` if any env/config was introduced
- Test: run the module suite once green.

- [x] **Step 1: Write the `MODULE.md` catalog section** (present behaviour, not the plan).
- [x] **Step 2: Run `php artisan test --compact Modules/Shop/tests`** and confirm green; then `vendor/bin/pint --dirty` from the root.
- [x] **Step 3: Add this plan's `## Delivery status` + a `**Documented in:** Modules/Shop/docs/rag/MODULE.md` line** (required to close; enforced by `tests/Unit/ClosedPlansPointToDocumentationTest.php`).
- [x] **Step 4: Commit** — `docs(shop): document the catalog foundation`.

---

## Notes / out of scope (later Shop plans)

- Stock/availability, cart, checkout, payments, digital fulfilment (`shop_download_grants`), reviews, read models, support — see the decomposition roadmap above. The Shop stock slice depends on the ERP reservation plan (`2026-10-06-erp-stock-reservation.md`, ERP-4).
- Storefront categories and per-category presets (E20) are plan #2, not here; this plan seeds only the minimal product content-Entity + a default Preset needed to create products.

## Execution notes (2026-10-07)

- **Task 1 shipped** on the Shop submodule: `d421352` (enums + entity seed), `f0e03e0` (phpstan typing), `e017d5e` (field-reuse/class-resolution tests + seeder hardening). 11 Pest tests green. Reviewed (spec ✅, quality ✅ "approved with restore caveat").
- **Entity layer is deliberate, per E20.** Shop owns `Casts/EntityType` (`Products='products'`), `Models/Entity`, `Models/Preset`, `Models/Pivot/Presettable` — a specialization of Core's abstract Entity/Preset/Presettable, required because `DynamicContentsService`/`PresetVersioningService` resolve a module's concrete classes by its `EntityType` namespace (ERP does the same). This is not a CMS `EntityType` case and not a parallel attribute engine: the product body stays a CMS `Content`, classification stays CMS `Category`.
- **Constraint carried into later plans (seam):** a CMS `Content` extended by a `Product` does NOT resolve its Shop entity/preset through the CMS `Content` model (`$content->entity` is null; `Content::getEntityType()` returns `Contents`). And `Core\Preset::migrateRelatedModelsToLastVersion()` (final, queries `Content::query()`) skips extended product contents because of the seam's `HidesExtendedContent` global scope. The `Product`→entity/preset resolution accessor is **deferred to plan #2** (facets/presets are its first consumer; the Task 2 quality review confirmed nothing in the catalog foundation needs it). Any future preset-version migration for products must go through `Content::withExtended()` (or a Core overridable query hook). Dynamic fields themselves still work (they read the presettable snapshot).
- **Deferred (not Task 1 defects):** (a) phpstan hygiene for Shop — one baselined-pattern `nullsafe.neverNull` in the seeder's `report()` plus the pre-existing 11 unbaselined `ShopController` scaffold errors; handle together in a small Shop phpstan pass, not here. (b) Two accepted negligible seeder nits: `restore()` return value unchecked (only reachable if soft-deletes were disabled after the delete), and the `withTrashed()->first()` lookup has no live-first ordering (only ambiguous if validation were bypassed).
- **Task 2 shipped** on the Shop submodule: `cda9415` (`shop_products` migration + `Product` seam consumer + factory + provider registration + `ProductExtensionTest`), `074c094` (fix: persist merged translatable content fields through the product root). 6 Task-2 Pest tests green (17 total). Reviewed (spec ✅ after the translatable write-through fix; quality ✅ "ready to merge, foundation scope"). `Product` is the first production consumer of the CMS content-extension seam; E2a transparent merge via `getAttribute`/`setAttribute` routing + a `contentDirtyViaMerge` flag honored in a `saving` hook (not a `save()` override — the trait's `save()` is `#[Override]`).
- **MODULE ENABLEMENT BLOCKED (action needed from the user).** Enabling Shop requires `modules_statuses.json` `"Shop": true` in the laraplate parent repo (committed file currently has no Shop entry → module inert in CI). Enabling it makes `shop.product` the first registered production extender, which breaks exactly two CMS tests that assume an empty registry: `Modules/CMS/tests/Unit/ContentExtension/ContentExtenderRegistryTest.php` ("is a container singleton and starts empty") and `Modules/CMS/tests/Feature/ContentExtension/ContentExtensionMappingTest.php` ("…no extension object when no extender is registered"). The two-line CMS fix (assert against a fresh `new ContentExtenderRegistry()` / rebind an empty registry before the emptiness assertion) could not be applied: editing the CMS submodule was permission-denied ("Modify Shared Resources"). Until the user unblocks the CMS edit (or makes it), the committed state keeps Shop **disabled** (so CI stays green and no regression ships); the local working tree keeps `"Shop": true` so development/tests run. When unblocked: fix the two CMS tests, commit+push CMS, then commit `modules_statuses.json` enablement + bump the CMS and Shop submodule pointers together.
- **Tracked follow-ups from the Task 2 quality review (latent — no surface exists yet, address before the surface activates):** (1) **Mass-assignment of merged content fields** — `Product::create(['title'=>…])` / `->update([...])` is NOT routed to the Content (`$fillable` excludes content keys; only direct `$product->title = …` and `forceFill` route), so under `preventSilentlyDiscardingAttributes` it throws in dev/test and silently discards in prod. Route content-owned keys in `fill()`/`isFillable` (or loudly document) before any Product write surface (Filament/API) lands — plan #2+. (2) **Tenancy × resolver** — `Product` is company-scoped (`BelongsToCompany`), `Content` is not; `ContentExtensionResolver::resolve()` runs `$class::query()` with the company scope, so a cross-company product content won't resolve once a company context is active. No-op today. Resolve extenders `withoutGlobalScope(BelongsToCompanyScope)` in the seam when tenancy is wired (needs a CMS edit — currently permission-blocked).
- **Task 3 shipped** on the Shop submodule: `5f82389` (`shop_product_variants` + `ProductVariant` + factory + `Product::variants()`/`defaultVariant()`), `cbe6e78` (hardening: reparent guard, trashed-product exists rule, FK name, attribute default), `996af87` (fix: demote sibling defaults in a `saved` hook guarded on `isDirty(['is_default','product_id'])`, not the sticky `wasRecentlyCreated`). 11 variant tests (28 total). Reviewed (spec ✅, quality ✅ after two fix rounds). `ProductVariant` has NO `company_id` (E23, derived from product); `attributes` is the size/colour label-only axis (E22). Single-default is enforced at the model level only (no portable partial-unique index); a product may have zero defaults until one is set (consistent with `Company`/`WorkflowScheme`).
- **Task 4 shipped** on the Shop submodule: `3b1d450` (`shop_variant_items` + `VariantItem` + factory + `ProductVariant::items()`/`erpItems()`), `487442a` (resolve soft-delete semantics + cover composition integrity + harden quantity/uniqueness). 15 composition tests (43 total). Reviewed (spec ✅, quality ✅ after one fix round). `VariantItem` composes a variant from ERP `Item`s (E7b: one row simple, several bundle, zero item-less); `role` closed set `main`/`component` (E31) as a validated string with constants; `quantity` `decimal:4`; no `company_id` (E23). **Soft-delete contract:** `items()`/`VariantItem::item()` resolve the FULL composition — `item()` uses `->withTrashed()` so a row always resolves its ERP Item even when soft-deleted (check `->trashed()`); `erpItems()` is the live-only purchasable view (excludes soft-deleted items/rows). `item_id` FK is `restrictOnDelete` (guards hard deletes only). Uniqueness of `(variant_id, item_id)` among live rows is validation-enforced on create and on item change.
- **Task 4 tracked minors (non-blocking, for plan #3 / a polish pass):** (a) restoring a soft-deleted `VariantItem` can recreate a duplicate `(variant_id, item_id)` among live rows, because the uniqueness rule runs only on create and on an `item_id` change, not on restore — soften the "at most once among live rows" claim or guard `restoring` when it matters; (b) the "a same-item quantity update does not self-clash" behavior (the dropped `->ignore(self)`) is correct but not pinned by a test — add a one-line test that re-sends the same `item_id` with a quantity change.

## Delivery status (2026-10-07)

**Documented in:** `Modules/Shop/docs/rag/MODULE.md` (catalog-foundation section — the present catalog model, the seam merge, variants, composition, the soft-delete contract, and the developer caveats).

All five tasks shipped on the Shop submodule and reviewed (per-task spec + quality review, then a final holistic review that approved merge with an empty must-fix list). Shop suite: 44 Pest tests green. Each task was executed with subagent-driven development on `master` (user's explicit isolation choice), committed and pushed per task; the laraplate submodule pointer and the stack-root pointer were bumped per task.

Shop submodule commits (`0b2f373..2625b87`): `d421352`/`f0e03e0`/`e017d5e` (Task 1 — enums + Shop-owned product content-Entity/Preset + helper), `cda9415`/`074c094` (Task 2 — `Product` seam consumer + translatable-merge fix), `5f82389`/`cbe6e78`/`996af87` (Task 3 — `ProductVariant` + single-default in a dirty-guarded `saved` hook), `3b1d450`/`487442a` (Task 4 — `VariantItem` composition + soft-delete contract), `128dcf7`/`cdbaca4` (Task 5 — MODULE.md + accuracy corrections), `2625b87` (final polish — `#[Override]`, `content_id` soft-delete-aware rule, end-to-end cascade test, doc caveats).

**Divergences from the plan (recorded here as the single source of truth):**
- **Task 1 added a Shop Entity layer not in the plan's file list** (`app/Casts/EntityType.php`, `app/Models/Entity.php`, `app/Models/Preset.php`, `app/Models/Pivot/Presettable.php`). This is necessary, not scope creep: Core's `DynamicContentsService`/`PresetVersioningService` resolve a module's concrete Entity/Preset/Presettable by its `EntityType` namespace, so a Shop-owned product entity (E20) requires these classes. ERP follows the identical layout. Verified by review.
- **The module is deliberately NOT enabled in the committed `modules_statuses.json`** (no `"Shop"` entry). The catalog code is shipped but inert in the committed/CI state. Enabling Shop registers `shop.product` as the first production content extender, which breaks two CMS tests that assume an empty registry (`ContentExtenderRegistryTest`, `ContentExtensionMappingTest`); the ~2-line CMS test fix (assert against a fresh/empty registry) could not be applied because editing the CMS submodule was permission-denied ("Modify Shared Resources"). **Action needed from the user:** unblock the CMS edit (or make it), then commit `modules_statuses.json` `"Shop": true` together with the bumped CMS pointer. Until then the local working tree keeps `"Shop": true` so development and the Shop suite run.

**Deliberately deferred (not built here):** everything in the decomposition roadmap beyond the catalog core (storefront browsing/categories/facets, cart, checkout, payments, digital fulfilment, reviews, read models, support); a Shop phpstan hygiene pass (the factory `generics.notGeneric`/`class.missingExtends`, the seeder `nullsafe`, and the pre-existing 11 `ShopController` scaffold errors); refreshing the stale `Modules/Shop/README.md` and `.cursor/rules/module-context.mdc`.

**Forward constraints for plan #2+ (all recorded in MODULE.md and above):** content-key routing through `fill()`/mass-assignment before a Product write surface (Filament/API); the `content` body-field vs `content()` relation collision (body is `$product->content->content`; a `body` accessor or key special-case is needed for a write surface); a `Product`→entity/preset resolution accessor for facets; the company-scoped-`Product` vs unscoped-`Content` resolver interaction once tenancy is active; soft-deleting a product leaves variants live/orphaned so availability/cart queries must constrain on a non-trashed product; the `VariantItem` restore-duplicate edge.
