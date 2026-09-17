# Ecommerce module — design

**Date:** 2026-09-17
**Module:** `Modules/Ecommerce` (not yet created)
**Supersedes:** `.cursor/plans/ecommerce_module_embryo_5a2b64dd.plan.md` (the embryo whose decisions this spec re-analyses and locks)
**Status:** Design agreed. No implementation started.

---

## 1. Purpose

Add a seventh native module, `Modules/Ecommerce`, that sells online. It is an **orchestrator, not a
rebuild**: the online-sales axis is the only thing it owns. Editorial presentation stays in CMS,
economic truth (SKU, stock, pricing, orders, invoices, fulfilment) stays in ERP, customer support
reuses SAO, content moderation reuses Core. Ecommerce adds only what is specific to selling on a web
channel: the product anchor that ties a `Content` to an `Item`, the storefront and cart/checkout,
web-only merchandising and promotions, payment reconciliation against external PSPs, reviews, and the
customer-facing read models for orders and delivery.

The guiding rule is "reuse, don't reinvent". Every place where Ecommerce would duplicate a CMS, ERP,
SAO or Core capability is a defect, not a shortcut.

---

## 2. Locked decisions

| # | Decision | Rationale |
|---|----------|-----------|
| E1 | Ecommerce is a **native module** (`Modules/Ecommerce`) depending on **Core, CMS, ERP, SAO** via explicit `module.json` dependencies. | Reuse is the whole point; the dependencies are the reuse. No premature inversion of control. |
| E2 | `Product` is an **anchor in its own table** (`ecommerce_products`), not a subtype of `Content` and not a subtype of `Item`. It links to a `Content` (`content_id`) and, through its variants, to one or more ERP `Item`s via the pivot of E7b. | Product is a commercial object with its own columns and lifecycle. It is neither editorial content nor an accounting record; it references them. |
| E3 | A `Content` becomes aware it is extended through a **nullable `extended_type` column** on `contents` holding a **morph alias** (e.g. `ecommerce.product`), never an FQCN. `null` means a normal content. | Reading a content in isolation must reveal whether it is extended without every query consulting an external registry; a self-describing column makes the default-hide a trivial, reliable global scope, and covers the partial case (a content of an extended entity that has no extender). Storing an alias (via Laravel `Relation::enforceMorphMap`) keeps CMS generic and free of any Ecommerce class name. |
| E4 | CMS provides the **generic extension seam** (the column, a global scope hiding `extended_type IS NOT NULL`, an opt-in `withExtended()` scope, an alias→resolver registry, and the batch upcast pipeline). Ecommerce **registers** `ecommerce.product => Product` and sets `extended_type` when it creates the product's content. | CMS learns the *concept* "a content may be extended", never the concrete `Product`. Dependency direction stays Ecommerce → CMS. Any future module can extend content the same way. |
| E5 | Extended contents are **hidden by default** on every read path (global scope). They are returned only through the explicit opt-in path, which **upcasts** each content row to its extender and attaches the content back via `setRelation('content', $content)`. | Registering the product entity must not leak product-content into generic CMS surfaces. "A volte lo voglio, a volte no" is an explicit opt-in, not a side effect of registration. |
| E6 | The upcast is a **named, batched projection**, never a hidden mutation of the base `Content` query and never a per-row lazy load. It groups the current page by `extended_type`, resolves the extender class from the alias, loads all extenders in one query per alias (`whereIn('content_id', $ids)`), swaps the items, and sets the inverse relation. | Preserves pagination and eager-loading semantics; guarantees O(number of aliases on the page) queries, not O(rows). See §7. |
| E7 | Pricing, stock, orders, invoices, accounting, fulfilment and shipment tracking are **authoritative in ERP**. Ecommerce reads them through ERP services (`PriceResolverService`, an availability service over `Item`), never by duplicating listino or stock. | ERP already owns this and is the system of record. A price or stock copy in Ecommerce is decision E7's forbidden case. |
| E7a | **ERP has no `Product`; its noun is `Item`** (the accounting/inventory article: SKU, UoM, costing, `tracing_type`, stock via `StockLevel`/`StockMovement`). Ecommerce `Product` is a **web-sales projection over one or more `Item`s**, not a duplicate. Stock is read from ERP; a bundle's availability is derived (min over components), never stored. | The word "product" was overloaded. Naming this explicitly stops Ecommerce from re-modelling what ERP already owns. |
| E7b | **The sellable unit ↔ `Item` link is a pivot with quantity and role from v1.** The sellable unit is a `ProductVariant` (a simple product has one default variant); a pivot (`ecommerce_variant_items`: `variant_id`, `item_id`, `quantity`, `role`) composes each variant from its `Item`(s). One pivot row = simple; a variant per SKU = a range; several rows = a bundle; **zero rows = a virtual/display-only product** not purchasable through the ERP flow. | One structure expresses simple, variants, bundles and item-less products uniformly, and avoids a painful migration later. The pivot is cheap and load-bearing once bundles exist in v1. |
| E7c | **Virtual commercial bundles ship in v1** through that pivot: price = sum of component prices or a bundle override, availability = min over components (quantity-weighted), one ERP order line per component `Item`. A **physically assembled kit** is not an Ecommerce concern: it is a single ERP `Item` produced via MES `Bom`, sold as a one-row variant. Ecommerce never re-implements a BOM. | Separates manufacturing (MES/ERP) from sales grouping (Ecommerce); the sales bundle is a first-class v1 capability, the manufactured kit stays where it belongs. |
| E8 | Web-only price adjustments (promotions, campaigns) are an **override layer above** ERP's `PriceResolverService`, computed at read time, never a second price source of truth. | Shop promos are a channel concern; the catalogue price stays in ERP `PriceList`/`PriceListItem`. |
| E9 | Customer support / RMA / refund-request ticketing **reuses SAO**, it is not rebuilt in Ecommerce. Ecommerce tickets are a SAO project/ticket-type; Ecommerce carries only the order references and raises events toward ERP for actions that need an ERP document. The exact mapping is **borderline and deferred** (see Open questions). | SAO already models projects, workflow schemes, enforced transitions, comments and a timeline. Rebuilding that in Ecommerce violates E-reuse. The embryo's "keep it in Ecommerce for now" predates SAO maturity. |
| E10a | **Review photos/videos are the generic CMS "media attachments on comments" capability**, owned by the comments spec (`2026-05-15-cms-comments-moderation-design.md`, addendum 2026-09-18: `HasMultimedia` on `Comment`, moderation-gated, form-context exposure). Ecommerce **cites** it and adds only its consumer rule (which forms show the upload, and any verified-purchase gate on attaching). | The capability benefits any commenter, so the decision lives in CMS; Ecommerce is just its first consumer. |
| E10 | **Reviews reuse the shipped CMS comment+rating system, not a new entity.** CMS `Comment` already carries a per-comment `rating_score`; `cms_content_ratings` (`ContentRating`) stores one moderated `score` (1-5) per `(content_id, user_id)` and `ContentRatingService::syncFromApprovedComment` aggregates only approved comments. Since the product's body **is** a `Content`, product comments **and** ratings and their moderation and aggregate are inherited **for free**. The **only** Ecommerce addition is an optional **verified-purchase gate** (who may rate, with an optional order/order-line link). No standalone `ecommerce_reviews` table. | The comment+rating+moderation+aggregate stack (plan `2026-05-15-cms-comments-moderation`, shipped) is exactly a review system minus verified-purchase; rebuilding it in Ecommerce would duplicate shipped code. |
| E11 | The **seller is an ERP `Company`** (the ERP tenant root). Ecommerce does not invent a "vendor" concept; `company_id` on products/variants/carts reuses ERP's `BelongsToCompany`. The buyer is an ERP customer (business partner), not a `Company`. | ERP `Company` is documented as "tenant root for the Business/ERP domain"; it already is the seller with its own catalogue, pricelists and books. Marketplace "operator ≠ seller" is then just another `Company` whose product is the selling service, invoiced through ERP. |
| E12 | The **data model is multi-tenant from day one** (`company_id` propagated via `BelongsToCompany`), but the **v1 storefront is single-vendor**: one active company per storefront, one cart, one ERP order. Multi-vendor cart, per-vendor order split, payouts and commission accounting are an explicit **phase 2 (marketplace)**. | The multi-tenant schema is near-free (the global scope exists) and keeps the marketplace door open; the marketplace's real cost (cart fan-out and per-vendor settlement) is deferred, not designed away. |
| E13 | Payments use a **multi-PSP driver abstraction**. v1 reference drivers: **Stripe** and **PayPal**, both hosted checkout. An Italian provider (Nexi/Satispay) is an optional later driver. | Hosted checkout keeps card data and strong authentication in the provider's perimeter. The abstraction lets a new PSP be a driver, not a rewrite. |
| E14 | The application **never persists cardholder data** (PAN, CVV/CVC, track data, PIN) **nor PSP account secrets** (secret API keys, webhook signing secrets, merchant private keys). Secrets live in config/secret-manager/env, outside business tables. Ecommerce stores only **reconciliation records**: opaque provider ids (payment intent / charge / session), normalised status, amount, currency, timestamp, outcome, non-sensitive error reason, and the `order_id`/checkout-session correlation for idempotent webhooks and audit. | Zero card-derived PCI scope in the application. Logging and exception dumps must redact known PSP webhook payloads. |
| E15 | Delivery tracking is a **read model**: carrier, tracking numbers and shipment events are authoritative in ERP (or an ERP-side logistics integration); Ecommerce's "my order / parcel status" screens consume an ERP read API and never become a second source of truth for logistics events. | Same principle as E7 applied to fulfilment. |
| E16 | `content_id` is **mandatory (NOT NULL)**: a product always has a descriptive body, so it is always a content-extender. Item links are **optional** and live on the variant pivot (E7b): a product with zero pivot rows is virtual/display-only. No automatic sync between `Item.name` and `Content.title` — different management vs marketing names are a **feature**. A draft/expired/scheduled content shows the storefront a "card unavailable" state via content validity, not via a missing link. | The body is essential to a sellable web product; the purchasable SKU is not (virtual products exist). Editorial and management identities stay distinct. |
| E17 | Because the content is mandatory, `Product` and its `Content` are **one unit with symmetric lifecycle**: product soft-delete/force-delete/restore cascade to the content, and deleting the content cascades to the product (there is no bodiless-orphan state — `content_id` is NOT NULL). ERP order and stock links live on the variant/`Item` side and are unaffected; a soft-deleted product preserves history. Detailed in the extension-seam spec (C9, C10). | With a mandatory body the orphan-without-content state is impossible, so the earlier orphan handling collapses into a simpler symmetric cascade. |

---

## 3. Phasing and non-goals

**v1 (this design):** product anchor + variants + virtual bundles (sellable unit ↔ `Item` pivot);
content extension seam in CMS; single-vendor storefront, cart, checkout; ERP-backed price/availability
read paths; multi-PSP hosted payments with reconciliation only; reviews with Core moderation; customer
support as a SAO project; order and delivery read models.

**Phase 2 (marketplace):** multi-vendor cart, per-vendor order split, payouts, commission accounting
(platform-as-service `Company`), per-vendor merchandising.

**Explicitly not in v1:** wishlist and product comparison (candidate backlog); a separate Support
module (SAO reuse covers v1); any second price or stock source; any card data or PSP secret in the
database; PHP/migration/test work in CMS/ERP/SAO beyond the extension seam named here.

---

## 4. Module and dependencies

- `module.json` declares dependencies on `Core`, `CMS`, `ERP`, `SAO`.
- Table prefix `ecommerce_` via an `EcommerceTables` enum using `Core\Enums\Concerns\HasModuleTablesUtils`, following CMS/ERP (not MES).
- Models extend `Core\Overrides\Model`; every table `hasSoftDelete: true`; migrations use `MigrateUtils`; validations on the model in `getRules()`; permission names only from `Core\Support\PermissionName`. Follows the standard in the SAO 1a spec's global constraints.

---

## 5. Perimeter — what Ecommerce owns

| Area | Ecommerce role |
|------|----------------|
| Anchor + variants | `Product` links a storefront card to one `Content` and one/more `Item`s; web-only attributes (variant attributes JSON). |
| Web merchandising | `is_published_in_shop`, `featured`, `release_date`, channel badges/metadata. Never duplicates listino or stock. |
| Pre-order | Cart, checkout, guest/logged session, cart merge — until it becomes an ERP document. |
| Web-only promotions | Override layer over `PriceResolverService` (E8). |
| Payments (PSP) | Flow orchestration (hosted checkout), webhook/callback, reconciliation toward the ERP order (E13, E14). |
| Reviews / UGC | Ratings, textual reviews, moderation via Core (E10). |
| Support | A SAO project/ticket-type (E9); Ecommerce carries order references only. |
| Delivery tracking (UX) | Read-model screens over ERP (E15). |

---

## 6. Data model (draft)

`ecommerce_products` (catalog anchor):
- `id`, `company_id` (`BelongsToCompany`, ERP), `content_id` **NOT NULL** FK → `contents`, `is_published_in_shop` bool, `featured` bool, `release_date` nullable, `metadata` JSON (marketing badges / promo flags)
- no `item_id`: composition lives on the variant pivot below

`ecommerce_product_variants` (the sellable unit; a simple product has one `is_default` variant):
- `id`, `product_id`, `is_default` bool, `attributes` JSON (size, colour, ...)

`ecommerce_variant_items` (pivot — composes a variant from ERP `Item`s):
- `id`, `variant_id`, `item_id` FK → `erp_items`, `quantity`, `role`
- one row = simple; several rows = virtual bundle; zero rows = virtual/display-only (not purchasable via ERP)

Reviews: **no Ecommerce table.** A review is a CMS `Comment` (with `rating_score`) on the product's
content, moderated, aggregated into `cms_content_ratings` (`ContentRating`) — all shipped. The only
Ecommerce-specific piece is an optional **verified-purchase gate**: a thin policy (and, if a hard link
to the order is wanted, a small `product_id`/`order_line_id` ↔ `comment_id` association) deciding who
may rate. Shape decided when the review slice is planned.

`ecommerce_payment_reconciliations` (E14):
- `id`, `order_id` (ERP correlation), PSP driver key, opaque provider ids, normalised status, amount, currency, outcome, non-sensitive error reason, timestamps. No card data, no PSP secrets.

Carts and checkout sessions: Ecommerce-owned until they become an ERP order.

The **`contents` table gains** `extended_type` (nullable, morph alias) — the only change to a table
CMS owns, justified by E3.

---

## 7. Content extension mechanism (the CMS seam)

This is the one novel piece and the only intrusion into CMS. It is deliberately generic. Its
authoritative design is `docs/superpowers/specs/2026-09-17-cms-content-extension-seam-design.md`; the
summary below is Ecommerce's use of it.

**Why a column and not a registry-only approach.** A `Content` read in isolation must know whether it
is extended. Deriving that from `entity_id` plus an external registry works only if *every* read path
remembers to consult the registry; miss one and product-content leaks. A self-describing column makes
the default-hide a single global scope that always applies. (Note: the old `tightenco/parental`
subclass mechanism — `Content::$childTypes` / `makeFromEntity` — was removed in CMS commit `5176695`;
this seam does not revive it. A dedicated create-path that sets `extended_type`/preset can be
reintroduced when the module is built.)

**The column.** `contents.extended_type` nullable string. It holds a **morph alias** registered via
Laravel `Relation::enforceMorphMap`, e.g. `'ecommerce.product' => Modules\Ecommerce\Models\Product::class`.
CMS stores and reads the alias; it never references `Product`.

**Default hide.** A global scope on `Content` adds `whereNull('extended_type')`. Every existing CMS
read path (routes, admin lists, search rehydration) excludes extended contents automatically, with no
change at the call sites.

**Opt-in and upcast.** An explicit scope `withExtended()` (and/or an ACL-gated flag) removes the
global scope. When a caller wants extended results *as their extender*, it runs the batch upcast:

1. Fetch the page of `Content` as usual (`withExtended()` so extended rows are included).
2. Group the page rows by `extended_type` alias.
3. For each alias, resolve the extender class from the morph map, and load **all** extenders for that
   page in one query: `Product::whereIn('content_id', $contentIdsForThatAlias)->get()` (with the
   extender's own eager-loads, e.g. `item`).
4. Build a `content_id => extender` map, replace each `Content` item with its extender, and on each
   extender call `setRelation('content', $originalContent)` so the content travels with it and is
   never re-queried.
5. Return the collection of extenders (mixed with plain `Content` for rows that have no extender).

**N+1 avoidance — the core of the answer.** The cost is **one query for the page of contents, plus
one query per distinct `extended_type` alias present on the page** — not one per row. Because content
lists are already **entity-scoped** in CMS (routes are `/{...}/{entity}`), a product listing carries a
single alias, so it is exactly two queries total: the contents page, then one `whereIn` for the
products. `setRelation('content', ...)` closes the loop so accessing `$product->content` triggers no
further query. Nothing is loaded lazily; the extender's relations (`item`, availability projections)
are eager-loaded inside step 3's single query.

**Worked example.** An entity-scoped product listing is exactly two queries. `setRelation('content')`
is the key: it attaches the already-loaded content to the extender, so a later `$product->content`
triggers no query.

```php
// Query 1: the page of contents, extended rows included (global scope lifted).
$contents = Content::withExtended()
    ->where('entity_id', $productsEntityId)
    ->paginate(20);

// Query 2: every extender for the page in one shot, with its own ERP relations eager-loaded.
$products = Product::whereIn('content_id', $contents->pluck('id'))
    ->with('item')
    ->get()
    ->keyBy('content_id');

// In-memory swap: replace each Content with its extender, carry the content back.
$items = $contents->getCollection()->map(function (Content $content) use ($products) {
    $product = $products->get($content->id);

    if ($product === null) {
        return $content;                        // a row with no extender stays a Content
    }

    $product->setRelation('content', $content); // content travels with the product, no re-query

    return $product;                            // the list now yields Product
});
```

Two queries total for the page, not 20 + 1. If a page ever mixed several extender aliases (not the
entity-scoped case), it is one query for the page plus one `whereIn` per distinct alias present —
proportional to the number of types, never to the number of rows. Nothing is loaded lazily.

**Where this lives.** The scope, the registry, and the upcast pipeline are **CMS** code (generic).
The alias registration and the `content()`/`extended` wiring on the extender side are **Ecommerce**
code. `Product` declares the inverse `content(): BelongsTo` to `contents`.

---

## 8. Reuse contracts

- **ERP:** `Item` (SKU/UoM/costing/stock), `PriceListItem` + `PriceResolverService` (pricing, M7.1 GA),
  `BelongsToCompany` (tenancy). Ecommerce adds `ProductAvailabilityService` (reads ERP stock) and the
  web promotion override over `PriceResolverService`.
- **CMS:** `Content` (i18n, gallery, SEO, `HasApprovals`, `HasValidity`, `HasLocks`, `Searchable`,
  `HasPath`) plus the extension seam of §7. `Comment` + `ContentRating` + `ContentRatingService` give
  product comments and moderated 1-5 reviews for free (E10). `EntityType::PRODUCTS` and a seeded
  `Entity` for products.
- **SAO:** the ticketing engine for customer support (E9, boundary deferred).
- **Core:** `ModerationAdapterRegistry` (behind CMS comments); model concerns; permissions/ACL.

---

## 9. Constraints and traps

- No price or stock copy in `Product`/variants (E7, E8). Frontend never queries stock directly; only
  through the ERP availability service.
- `company_id` coherent across `Product`, `Item`, and (if used) `Content`.
- Payments: no column or JSON may hold PAN/CVV or merchant secrets (E14); redact known PSP webhook
  payloads in logs and exception dumps.
- Reviews: spam, SEO duplication vs `Content`, verified-purchase policy, GDPR/consent — settle before
  the UGC MVP.
- Ticket ↔ ERP: explicit correlation ids and events toward ERP; never two parallel workflows nor a
  double accounting write.
- Tracking: rate limit and cache carrier reads; UI fallback when ERP has not updated yet.
- The extension global scope must be provably applied: a test asserts a plain `Content::all()` never
  returns an extended row, and that `withExtended()` + upcast returns the extender with `content`
  already set (no extra query).

---

## 10. Ready criteria (before implementation starts)

- ERP M7.1 (advanced pricelists) GA — **met**.
- CMS: `EntityType::PRODUCTS` added and a seeded product `Entity`; the `extended_type` column, global
  scope, morph-alias registry and upcast pipeline agreed as a CMS change owned by this work.
- A short ADR confirming multi-tenant schema + single-vendor v1 (E11, E12).
- Reviews: moderation policy and optional verified-purchase rule aligned to ERP orders.
- SAO: the support ticket types and the map of "which transitions always require an ERP command".
- PSP: Stripe and PayPal hosted-checkout drivers chosen (E13) and the security checklist (secrets out
  of DB, log redaction) written.

---

## 11. Proposed file structure (indicative)

All paths relative to `Modules/Ecommerce/`.

| File | Responsibility |
|------|----------------|
| `app/Enums/EcommerceTables.php` | Table-name registry |
| `app/Models/Product.php` | Catalog anchor; `content()`, variants |
| `app/Models/ProductVariant.php` | Sellable unit; `items()` pivot |
| `app/Models/VariantItem.php` (pivot) | Variant ↔ ERP `Item` with quantity/role |
| `app/Policies/VerifiedPurchasePolicy.php` | Gates who may rate a product (reviews reuse CMS comments+`ContentRating`) |
| `app/Models/PaymentReconciliation.php` | PSP reconciliation record (E14) |
| `app/Services/ProductAvailabilityService.php` | Reads ERP stock; min-over-components for bundles |
| `app/Services/ShopPriceService.php` | Web override over ERP `PriceResolverService`; sum/override for bundles |
| `app/Payments/PaymentDriver.php` (contract) + `Stripe`/`PayPal` drivers | Multi-PSP abstraction |
| `app/Support/ContentExtenderRegistration.php` | Registers `ecommerce.product` alias + extender |
| `database/migrations/*` | Product/variant/review/reconciliation tables + the `contents.extended_type` column |
| `docs/rag/MODULE.md` | Module RAG doc (anchor, extension seam, price/stock, payments, support, tracking) |

CMS-side additions for the seam (owned by CMS, delivered in the same work block): the `extended_type`
column and migration, the global scope, the alias→resolver registry, and the batch upcast pipeline.

---

## 12. Open questions

To settle when the relevant slice is planned; none blocks the module's shape.

- **SAO support boundary (borderline).** SAO's native role is code/ops orchestration ticketing, not
  ecommerce customer care, yet the mechanics fit: a customer is a user opening tickets on a project
  that could map to a seller or to an order. Define the exact mapping — project = seller? per-order
  tickets? customer identity vs internal user, and which SAO permissions/ACL apply — before building
  support. Deferred.
- **Cart → ERP order handoff.** The precise point where an Ecommerce cart becomes an ERP document,
  guest vs logged sessions, and cart merge.
- **Verified-purchase gate.** Reviews already exist as rated CMS comments + `ContentRating` (E10); the
  only open piece is the verified-purchase policy — whether to enforce it, and whether to hard-link a
  rating/comment to an `order_line_id` or just check purchase history at write time.
- **PSP driver contract.** The minimal driver surface (create session, handle webhook, reconcile) and
  idempotency, before choosing beyond the Stripe/PayPal references.
- Plus the content-extension seam's own open questions (single extender + unique `content_id`, state
  vs sale precedence, extension i18n/embeddings, import) tracked in that spec.
