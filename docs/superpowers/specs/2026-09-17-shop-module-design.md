# Shop module — design

**Date:** 2026-09-17
**Module:** `Modules/Shop` (not yet created)
**Supersedes:** `.cursor/plans/ecommerce_module_embryo_5a2b64dd.plan.md` (the embryo whose decisions this spec re-analyses and locks)
**Status:** Design agreed. No implementation started.

---

## 1. Purpose

Add a seventh native module, `Modules/Shop`, that sells online. It is an **orchestrator, not a
rebuild**: the online-sales axis is the only thing it owns. Editorial presentation stays in CMS,
economic truth (SKU, stock, pricing, orders, invoices, fulfilment) stays in ERP, customer support
reuses SAO, content moderation reuses Core. Shop adds only what is specific to selling on a web
channel: the product anchor that ties a `Content` to an `Item`, the storefront and cart/checkout,
web-only merchandising and promotions, payment reconciliation against external PSPs, reviews, and the
customer-facing read models for orders and delivery.

The guiding rule is "reuse, don't reinvent". Every place where Shop would duplicate a CMS, ERP,
SAO or Core capability is a defect, not a shortcut.

---

## 1a. Build native, do not integrate Magento

The foundational choice. **Build the commerce layer natively in this stack; do not integrate an
external commerce engine (Magento or similar).** The reason is single-source-of-truth: ERP already is
the system of record for catalogue (`Item`), pricing, stock, orders, invoices and e-invoicing. An
external engine keeps its own catalogue, customers and orders, so integration means permanent
bidirectional sync — catalogue, stock, prices, orders, customers — between two diverging sources: the
exact "double source of truth" this design exists to avoid. Building native is cheap **here
specifically** because the hard parts already exist as stack primitives (attribute sets =
Entity+Preset, layered navigation = Core facets, search, comments+ratings+moderation, media, i18n, ACL,
versioning, ERP pricing/stock/orders): the module is mostly wiring, and the genuinely missing part
(cart/checkout, PSP orchestration, storefront UI, verified-purchase) is a fraction of a commerce
engine and already fits the roadmap (`laraplate-ui` for the storefront over `/app`). Drawing design
ideas from Magento (attribute sets, layered navigation, price rules, configurable/bundle product types)
is encouraged; taking on Magento as an operational dependency is not.

**Re-evaluate only if** the shop becomes decoupled from ERP (commodity B2C with light integration),
time-to-market with a mature storefront outweighs everything, or the needed merchandising/B2B surface
grows to where a large share of Magento's features would actually be used.

---

## 2. Locked decisions

| # | Decision | Rationale |
|---|----------|-----------|
| E1 | Shop is a **native module** (`Modules/Shop`) depending on **Core, CMS, ERP, SAO** via explicit `module.json` dependencies. | Reuse is the whole point; the dependencies are the reuse. No premature inversion of control. |
| E2 | `Product` is an **anchor in its own table** (`shop_products`), not a subtype of `Content` and not a subtype of `Item`. It links to a `Content` (`content_id`) and, through its variants, to one or more ERP `Item`s via the pivot of E7b. | Product is a commercial object with its own columns and lifecycle. It is neither editorial content nor an accounting record; it references them. |
| E2a | **`Product` transparently merges its `Content` onto its own root via magic getters/setters, following the `Contributor`↔`User` pattern.** As `Contributor` does (`Modules/CMS/app/Models/Contributor.php`), the pieces are: overridden `getAttribute`/`setAttribute` (the "magic" — cf. `Core\Models\Concerns\HasTranslatedDynamicContents`) that route content-owned keys to the merged `Content` so they read and write as if native on the product; an always-loaded relation (`protected $with = ['content']`, opt out with `->without('content')`); a temp holder + `setTempContent()`; and a `save()` override that persists the content if dirty and sets `content_id` under the hood. The ERP **`Item` link is NOT merged**: it stays an explicit relation via the variant pivot. | A mandatory 1:1 editorial body should read and write as one object, exactly as `Contributor` surfaces `User` fields on itself; merging the `Item` would be ambiguous because it is many (variants/bundle) and volatile (stock/price) — which SKU, which price? |
| E3 | A `Content` becomes aware it is extended through a **nullable `extended_type` column** on `contents` holding a **morph alias** (e.g. `shop.product`), never an FQCN. `null` means a normal content. | Reading a content in isolation must reveal whether it is extended without every query consulting an external registry; a self-describing column makes the default-hide a trivial, reliable global scope, and covers the partial case (a content of an extended entity that has no extender). Storing an alias (in a dedicated `ContentExtenderRegistry`, not Laravel's global morph map) keeps CMS generic and free of any Shop class name. |
| E4 | CMS provides the **generic extension seam** (the column, a global scope hiding `extended_type IS NOT NULL`, an opt-in `withExtended()` scope, an alias→resolver registry, and the batch upcast pipeline). Shop **registers** `shop.product => Product` and sets `extended_type` when it creates the product's content. | CMS learns the *concept* "a content may be extended", never the concrete `Product`. Dependency direction stays Shop → CMS. Any future module can extend content the same way. |
| E5 | Extended contents are **hidden by default** on every read path (global scope). They are returned only through the explicit opt-in path, which **upcasts** each content row to its extender and attaches the content back via `setRelation('content', $content)`. | Registering the product entity must not leak product-content into generic CMS surfaces. "A volte lo voglio, a volte no" is an explicit opt-in, not a side effect of registration. |
| E6 | The upcast is a **named, batched projection**, never a hidden mutation of the base `Content` query and never a per-row lazy load. It groups the current page by `extended_type`, resolves the extender class from the alias, loads all extenders in one query per alias (`whereIn('content_id', $ids)`), swaps the items, and sets the inverse relation. | Preserves pagination and eager-loading semantics; guarantees O(number of aliases on the page) queries, not O(rows). See §7. |
| E7 | Pricing, stock, orders, invoices, accounting, fulfilment and shipment tracking are **authoritative in ERP**. Shop reads them through ERP services (`PriceResolverService`, an availability service over `Item`), never by duplicating listino or stock. | ERP already owns this and is the system of record. A price or stock copy in Shop is decision E7's forbidden case. |
| E7a | **ERP has no `Product`; its noun is `Item`** (the accounting/inventory article: SKU, UoM, costing, `tracing_type`, stock via `StockLevel`/`StockMovement`). Shop `Product` is a **web-sales projection over one or more `Item`s**, not a duplicate. Stock is read from ERP; a bundle's availability is derived (min over components), never stored. | The word "product" was overloaded. Naming this explicitly stops Shop from re-modelling what ERP already owns. |
| E7b | **The sellable unit ↔ `Item` link is a pivot with quantity and role from v1.** The sellable unit is a `ProductVariant` (a simple product has one default variant); a pivot (`shop_variant_items`: `variant_id`, `item_id`, `quantity`, `role`) composes each variant from its `Item`(s). One pivot row = simple; a variant per SKU = a range; several rows = a bundle; **zero rows = an item-less variant** — either a digital/service product or a display-only card, told apart by the product `kind` (E26), not by the row count. | One structure expresses simple, variants, bundles and item-less products uniformly, and avoids a painful migration later. The pivot is cheap and load-bearing once bundles exist in v1. |
| E7c | **Virtual commercial bundles ship in v1** through that pivot: price = sum of component prices or a bundle override, availability = min over components (quantity-weighted), one ERP order line per component `Item`. A **physically assembled kit** is not a Shop concern: it is a single ERP `Item` produced via MES `Bom`, sold as a one-row variant. Shop never re-implements a BOM. | Separates manufacturing (MES/ERP) from sales grouping (Shop); the sales bundle is a first-class v1 capability, the manufactured kit stays where it belongs. |
| E8 | Web-only price adjustments (promotions, campaigns) are an **override layer above** ERP's `PriceResolverService`, computed at read time, never a second price source of truth. | Shop promos are a channel concern; the catalogue price stays in ERP `PriceList`/`PriceListItem`. |
| E9 | Customer support / RMA / refund-request ticketing **reuses SAO**, it is not rebuilt in Shop. Shop tickets are a SAO project/ticket-type; Shop carries only the order references and raises events toward ERP for actions that need an ERP document. The exact mapping is **borderline and deferred** (see Open questions). | SAO already models projects, workflow schemes, enforced transitions, comments and a timeline. Rebuilding that in Shop violates E-reuse. The embryo's "keep it in Shop for now" predates SAO maturity. |
| E10 | **Reviews reuse the shipped CMS comment+rating system, not a new entity.** CMS `Comment` already carries a per-comment `rating_score`; `cms_content_ratings` (`ContentRating`) stores one moderated `score` (1-5) per `(content_id, user_id)` and `ContentRatingService::syncFromApprovedComment` aggregates only approved comments. Since the product's body **is** a `Content`, product comments **and** ratings and their moderation and aggregate are inherited **for free**. The **only** Shop addition is an optional **verified-purchase gate** (who may rate, with an optional order/order-line link). No standalone `shop_reviews` table. | The comment+rating+moderation+aggregate stack (plan `2026-05-15-cms-comments-moderation`, shipped) is exactly a review system minus verified-purchase; rebuilding it in Shop would duplicate shipped code. |
| E10a | **Review photos/videos are the generic CMS "media attachments on comments" capability**, owned by the comments spec (`2026-05-15-cms-comments-moderation-design.md`, addendum 2026-09-18: `HasMultimedia` on `Comment`, moderation-gated, form-context exposure). Shop **cites** it and adds only its consumer rule (which forms show the upload, and any verified-purchase gate on attaching). | The capability benefits any commenter, so the decision lives in CMS; Shop is just its first consumer. |
| E11 | The **seller is an ERP `Company`** (the ERP tenant root). Shop does not invent a "vendor" concept; `company_id` on products/variants/carts reuses ERP's `BelongsToCompany`. The buyer is an ERP customer (business partner), not a `Company`. | ERP `Company` is documented as "tenant root for the Business/ERP domain"; it already is the seller with its own catalogue, pricelists and books. Marketplace "operator ≠ seller" is then just another `Company` whose product is the selling service, invoiced through ERP. |
| E12 | The **data model is multi-tenant from day one** (`company_id` propagated via `BelongsToCompany`), but the **v1 storefront is single-vendor**: one active company per storefront, one cart, one ERP order. Multi-vendor cart, per-vendor order split, payouts and commission accounting are an explicit **phase 2 (marketplace)**. | The multi-tenant schema is near-free (the global scope exists) and keeps the marketplace door open; the marketplace's real cost (cart fan-out and per-vendor settlement) is deferred, not designed away. |
| E13 | Payments use a **multi-PSP driver abstraction**. v1 reference drivers: **Stripe** and **PayPal**, both hosted checkout. An Italian provider (Nexi/Satispay) is an optional later driver. | Hosted checkout keeps card data and strong authentication in the provider's perimeter. The abstraction lets a new PSP be a driver, not a rewrite. |
| E14 | The application **never persists cardholder data** (PAN, CVV/CVC, track data, PIN) **nor PSP account secrets** (secret API keys, webhook signing secrets, merchant private keys). Secrets live in config/secret-manager/env, outside business tables. Shop stores only **reconciliation records**: opaque provider ids (payment intent / charge / session), normalised status, amount, currency, timestamp, outcome, non-sensitive error reason, and the `order_id`/checkout-session correlation for idempotent webhooks and audit. | Zero card-derived PCI scope in the application. Logging and exception dumps must redact known PSP webhook payloads. |
| E15 | Delivery tracking is a **read model**: carrier, tracking numbers and shipment events are authoritative in ERP (or an ERP-side logistics integration); Shop's "my order / parcel status" screens consume an ERP read API and never become a second source of truth for logistics events. | Same principle as E7 applied to fulfilment. |
| E16 | `content_id` is **mandatory (NOT NULL)**: a product always has a descriptive body, so it is always a content-extender. Item links are **optional** and live on the variant pivot (E7b): a product with zero pivot rows is either a digital/service product or a display-only card, distinguished by `kind` (E26) — not purchasable-vs-not by row count. No automatic sync between `Item.name` and `Content.title` — different management vs marketing names are a **feature**. A draft/expired/scheduled content shows the storefront a "card unavailable" state via content validity, not via a missing link. | The body is essential to a sellable web product; the purchasable SKU is not (virtual products exist). Editorial and management identities stay distinct. |
| E17 | Because the content is mandatory, `Product` and its `Content` are **one unit with symmetric lifecycle**: product soft-delete/force-delete/restore cascade to the content, and deleting the content cascades to the product (there is no bodiless-orphan state — `content_id` is NOT NULL). ERP order and stock links live on the variant/`Item` side and are unaffected; a soft-deleted product preserves history. Detailed in the extension-seam spec (C9, C10). | With a mandatory body the orphan-without-content state is impossible, so the earlier orphan handling collapses into a simpler symmetric cascade. |
| E18 | **Storefront visibility = editorial public-visibility AND channel intent.** A product is shown on the storefront only when its content is **approved and currently valid** (`HasApprovals` + the `HasValidity` window) **and** `is_published_in_shop` is true; it is **purchasable** only if it additionally has at least one variant whose `Item`(s) are available. A draft/unapproved/expired content, or one scheduled with a future `valid_from`, hides or degrades the card ("card unavailable") regardless of `is_published_in_shop`; `is_published_in_shop = false` hides it regardless of content state. | Editorial readiness and channel intent are orthogonal axes and both must gate the shop; neither alone is sufficient, which is why the two flags cannot be collapsed into one. |
| E19 | **A shop customer is a Core `User` + an ERP customer (business partner)**, the same "identity extends user" pattern CMS uses for `Contributor`↔`User` (optionally with the same transparent merge). Orders, invoices and pricelists key off the **ERP customer**; SAO enters only when that user opens a support ticket. An anonymous visitor has no ERP customer yet: public pricelist, guest cart; the ERP customer is created/linked at checkout or registration. | The buyer that appears on ERP documents and drives pricing is an ERP concern, not a bare auth user and not a SAO entity. Keeps the chain Core (auth) → ERP (customer/orders/prices) → SAO (support) one-way. |
| E20 | **Storefront browsing reuses existing primitives, Magento-style, not new ones.** `Entity` is a **Core abstraction** (`abstract Modules\Core\Models\Entity`, shared `entities` table) that each module specializes; the product content-**entity** is the module's to seed, not a hard-coded CMS `EntityType::PRODUCTS`. **Per-category attribute sets = a `Preset` per category** under that product entity (`HasTranslatedDynamicContents` dynamic fields); a product content attaches to (product entity, category preset), and the filter set shown is derived from that preset. Layered navigation = Core's facet feature (`FacetQuery`/`FacetSort`, `crud-facet-counters`) over the indexed dynamic fields. Variant attributes (size/colour) are indexed as facetable and drive both SKU selection and filtering. A "configurable" product = choosing among predefined variants (v1); a component configurator with dependent options is later, over the `variant_items` pivot. | Core `Entity` + `Preset` already are Magento's entity + attribute sets, and Core already has layered navigation; the module seeds product entities/presets and facet config rather than building an EAV. |
| E21 | **A browse-only price/stock snapshot is indexed — the one bounded exception to "no ERP data in the index".** For list sort and price-range / in-stock facets, a **non-authoritative** price snapshot + in-stock flag is indexed and refreshed on price/stock change; it is browse-only and never used for a transaction. v1 indexes the **public pricelist** price only; per-customer-group browse prices are phase 2 (Magento indexes per group). The **exact** price and stock always resolve **live from ERP** at product detail, cart and checkout. Net: browse/sort/list = public snapshot; detail/cart = the customer's real price. | Layered navigation needs price sort and price/in-stock facets, impossible efficiently without an indexed value; a bounded, clearly non-authoritative snapshot enables browsing without making the index a price source of truth. |
| E22 | **Attributes have a single writer per concern; storefront classification is CMS, ERP taxonomy stays independent.** The platform has one attribute mechanism (Core `Entity` → `Preset` → `components`/`shared_components`) and one classification mechanism (Core `Taxonomy`, abstract, with per-module subclasses on the shared `core_taxonomies` table). A product spans `Content` + `ProductVariant` + ERP `Item`, so each concern gets exactly one writer: **(a) physical / transactional / producible truth → ERP `Item`** (SKU, UoM, costing, `tracing_type`, stock, and any item-level structured attribute ERP/MES need, via the Item's own taxonomy/preset). Shared with MES/ERP; Shop never writes it. **(b) purchase-selection axis (the variant picker: size/colour) → `ProductVariant.attributes` JSON**, held as **label/projection only**; the identity of "which SKU I sell" is the `variant_items` composition (E7b), and if ERP ever models that axis structurally on the `Item`, the variant mirrors it read-only. **(c) editorial / merchandising → the `Content` preset `components` + `Product` `metadata`.** Storefront-only. **Classification:** storefront categories are CMS `Category` (a `Taxonomy` subclass) under the product content-**entity** (Categories are entity-scoped, so they do not mix with editorial-article categories); the E20 per-category `Preset` lives on the `Category` itself. ERP keeps its own item taxonomy (today `Item.taxonomy_id` is a latent generic `exists:core_taxonomies` pointer with no wired relation and no product tree); **Shop ignores it — no mapping table.** The only ERP link is `variant_items` → `Item`; classification never crosses modules. | Assigning each concern a single writer and keeping the customer-facing category on CMS `Category` while ERP's accounting/production taxonomy stays independent prevents modelling the same attribute or category twice and avoids coupling Shop to ERP's internal taxonomy. The category a customer browses and the classification ERP/MES use answer different questions and have no reason to coincide; forcing them equal would couple the storefront to ERP internals. |
| E23 | **`company_id` placement: products and carts carry it via ERP `BelongsToCompany`; variants derive it.** `shop_products` and `shop_carts` use ERP's `BelongsToCompany` trait (global company scope + `creating` auto-fill from `current_company_id()`). `ProductVariant` carries **no** `company_id` column — it derives it from its `product`. `shop_payment_reconciliations` carries none either: it correlates to the ERP order, which already holds the company. This **supersedes E11's looser "products/variants/carts" phrasing** — E11 stated the tenancy intent, E23 fixes the placement. | A variant's company is functionally dependent on its product; a `company_id` column there would be a denormalised duplicate that `BelongsToCompany` would redundantly auto-fill and scope. Reconciliation keys on the order, so it needs no own company column. |
| E24 | **Active-vendor resolution reuses ERP tenancy through one storefront resolver; v1 = config, v2 = domain.** The storefront's active vendor `Company` is fed into ERP's existing resolver chain, not re-invented: a single Shop storefront middleware, via one `StorefrontVendorResolver`, sets the container binding `erp.current_company_id` for the request lifecycle; everything downstream reads only `current_company_id()` / `BelongsToCompany` and is unaware of how the company was resolved. The binding is **required** because a public/anonymous visitor has no authenticated user carrying `company_id` (ERP resolver step 2 fails on the public storefront). **v1 = a single configured company** (`config('shop.company_id')`), read **once** in that resolver. **v2 = host→company resolution** for multi-store, which replaces only the resolver's body — the binding seam and all downstream scoping are identical. Resolution MUST be centralised in the one resolver (no scattered `config('shop.company_id')` reads), so the v1→v2 swap stays a one-file change. | Reuse of ERP's `current_company_id()` binding avoids a parallel tenancy mechanism; centralising the strategy behind one resolver keeps the config→domain evolution (E12 phase 2) localised and safe rather than impossible. |
| E25 | **Price display is a presentation setting over the net ERP price; the browse index stores net.** Displayed prices are **configurable per storefront audience, default B2C gross** (VAT-inclusive); a B2B storefront shows net (VAT-exclusive). The stored and indexed value (the E21 snapshot) is always the **net** price from `PriceResolverService`; the gross figure is computed **at render time** by applying the resolved VAT rate — never a second stored price. The display VAT rate is read from ERP's **standard** VAT `TaxCode` for the vendor company's `fiscal_country` (v1: one standard rate, no per-product tax class). The **authoritative** tax on the order/invoice is always ERP's, computed at document-build time (as today via `InvoiceLine.tax_code`), not the browse-time display rate. **Prerequisite: ERP-2** (a deterministic way to designate the standard VAT `TaxCode`, §8a). Reduced/zero per-product rates and destination-country VAT are **deferred** — they need an Item tax class plus a resolver, both ERP-side. | Keeping net as the single stored truth honours E7/E21 (no second price source); display inclusive-vs-exclusive is an audience presentation concern, not a new price; reusing ERP's `TaxCode` data avoids a parallel tax engine in Shop. |
| E26 | **A product `kind` replaces the "zero-pivot = display-only" rule; digital/downloadable products ship in v1.** A `kind` on the product distinguishes three cases the `variant_items` row count cannot: **`physical`** (Item-backed — one or more pivot rows, stock-gated availability, one ERP order line per component `Item`), **`digital`** (item-less — zero pivot rows, always available with no stock gate, sold as a free-text ERP sales-order/invoice line and fulfilled by Shop), and **`display-only`** (item-less and **not** purchasable — a showcase card). Digital **fulfilment is Shop-owned**: the downloadable asset reuses CMS Media Library (`HasMultimedia`) on the product, and a post-payment **entitlement** (`shop_download_grants`: buyer + ERP order-line ↔ product/asset, optional expiry and max-download count) gates the actual download — issued on payment reconciliation (E14), checked at download time. ERP stays the system of record for the sale (the item-less line); Shop owns only the asset and the access grant. | ERP already supports item-less sales/invoice lines (`SalesOrderLine.item_id` nullable, `InvoiceLine` has no `item_id`), so a digital good needs no ERP `Item`; but "no Item" must not silently mean "not sellable", so an explicit `kind` separates a sellable digital from a display-only card. A digital's availability is not a stock question, so it never routes through the ERP availability read. Backing a digital with a non-stock ERP `Item` is avoided because ERP has no item-type to mark one non-stock (would need ERP-3, §8a); item-less is the clean path. |
| E27 | **Cart → ERP order handoff: the cart is Shop-owned until "place order", then becomes a `SalesOrder` — never a `Quotation`, never a Draft-as-cart.** ERP never holds the cart; `shop_carts`/the checkout session carries the whole shopping and checkout-data-entry phase and is converted to an ERP `SalesOrder` only at order placement. `SalesOrderLine`s are built from the cart lines: item-backed lines carry `item_id`, bundles explode one line per component `Item` (E7c), digital/item-less lines are free-text (E26, `SalesOrderLine.item_id` null). The buyer is linked to (or created as) an ERP `Party` (`is_customer`) at placement (E19): browsing and cart use the public pricelist + a guest/session cart with no ERP customer; a guest supplies identity (email + billing) at checkout, then find-or-create the `Party`. **Cart merge is a Shop-only concern**: on login/registration mid-session the session cart merges into the user cart (union lines, sum quantities per variant, re-resolve price/availability live), never touching ERP. The order's `status` timing relative to payment (Draft-then-Confirm vs create-Confirmed-after-pay) is decided separately (§12, cart→order timing). | ERP's `Draft` `SalesOrder` is mutable and side-effect-free, but mapping the cart onto a Draft would flood ERP with abandoned, ephemeral orders to garbage-collect; keeping the cart entirely in Shop keeps ERP clean and makes the handoff one deliberate conversion. A `Quotation` is the B2B quote flow, unneeded for a storefront purchase (a "request a quote" feature could reuse it later). |
| E28 | **Order timing vs payment: the `SalesOrder` is created `Draft` at placement (before payment) and transitioned Draft → `Confirmed` on captured payment.** At order placement (E27) the `SalesOrder` is created in `Draft` — verified side-effect-free (`saving` is validation-only, `saved` acts only when the header is locked, `SalesOrderConfirmed` fires only on `Confirmed`, and a Draft consumes no document sequence number). The `shop_payment_reconciliations` record (E14) keys on `order_id` from creation, plus the provider ids. On the PSP success webhook an **idempotent** Draft → `Confirmed` transition fires `SalesOrderConfirmed` (MES production orders, inventory, invoice posting); for digital products (E26) the download grant is issued on that reconciliation. On payment failure / abandonment / expiry the Draft is set `Cancelled` by a TTL job. `Confirmed` is gated on **captured** payment (hosted checkout captures; an auth-only flow would confirm on capture). | A pre-existing Draft gives reconciliation a stable `order_id` and makes payment success a single idempotent status flip, eliminating the "money captured but order-creation failed" window of creating the order only after payment. ERP's `Draft` is designed for exactly this side-effect-free pending state and burns no document number, so abandoned Drafts are cheap to expire. |
| E29 | **Stock availability & reservation: Shop reads ERP availability and reserves through the ERP reservation when it lands; interim v1 is check-at-checkout.** Availability is a live read of ERP's availability service; digital/item-less products (E26) are always available (no stock gate). A **native ERP reservation/ATP is being designed** (`2026-10-06-erp-stock-reservation-design.md`, §8a ERP-4): once shipped, Shop reserves `soft` at Draft placement and relies on the promotion to `hard` at payment capture (E28), released on cancel/expiry — eliminating the payment-window race. **Until then, v1 is check-at-checkout**: availability is re-checked at the Draft → `Confirmed` transition; a paid-but-unfulfillable order triggers refund/cancel (the race is accepted). Shop **never builds a parallel reservation** (E7). | Overselling is an ERP-native concern (it exists for back-office sales too), so the reservation lives in ERP and Shop consumes it; the interim check-at-checkout keeps Shop shippable before the reservation lands, and keeping availability a single ERP read (never a Shop copy) honours E7. |
| E30 | **PSP driver contract: a per-provider `PaymentDriver` behind a registry; the webhook is the source of truth; reconciliation is idempotent.** Each PSP (Stripe, PayPal — E13) implements a Shop `PaymentDriver`: `createCheckoutSession(order)` → a hosted-checkout URL + an opaque provider session id (no card data touches Shop, E14); `verifyAndParseWebhook(request)` → verifies the provider signature (secret from config/secret-manager) and returns a **normalized event** (provider ids, status, amount, currency); `reconcile(event)` → updates `shop_payment_reconciliations` with the **normalized status** and, on captured payment, drives the Draft → `Confirmed` transition (E28), **idempotent on `(order_id, provider_event_id)`** so webhook replays and out-of-order delivery are no-ops; `refund(reconciliation, amount?)` → full/partial refund (used by the paid-but-unfulfillable case E29 and by returns; the policy of when to call it is decided later). A `PaymentDriverRegistry` (alias → driver, active drivers from config) makes a new PSP a driver, not a rewrite (E13). Shop logic keys only on the **normalized status** enum (`pending`/`authorized`/`captured`/`failed`/`refunded`/…), never on raw provider statuses. The **webhook is the source of truth** for `Confirmed`; the post-checkout redirect is UX only (it can be abandoned). Secrets live only in config/secret-manager, webhook payloads are redacted in logs, and only opaque provider ids + normalized data are persisted (E14). | A thin per-provider driver behind a registry keeps hosted-checkout card handling inside the PSP's perimeter and makes multi-PSP an additive change; webhook-as-truth avoids confirming an order abandoned at the redirect; idempotency on the provider event id is mandatory because PSPs deliver webhooks at-least-once and out of order. |
| E31 | **v1 commercial parameters: single-currency, and the `variant_items.role` value set.** v1 is **single-currency**: storefront prices and the resulting ERP `SalesOrder.currency` are the vendor `Company`'s `default_currency`; multi-currency display and settlement are deferred. The `variant_items.role` (E7b) value set is a closed two-value set — **`main`** (the sole sellable item of a simple product, or the anchor item of a range) and **`component`** (a constituent of a virtual bundle, E7c): a simple product's single pivot row is `main`, a bundle's rows are `component`. | Single-currency matches the single-vendor v1 (E12) and ERP's per-company `default_currency`, avoiding an FX layer before it is needed; a closed two-value role set expresses simple, range and bundle composition without inventing roles the v1 flows never use. |

---

## 3. Phasing and non-goals

**v1 (this design):** product anchor + variants + virtual bundles (sellable unit ↔ `Item` pivot);
**physical and digital/downloadable products** (product `kind`, E26) with Shop-owned digital
fulfilment (asset + post-payment download grant); content extension seam in CMS; single-vendor
storefront, cart, checkout; ERP-backed price/availability read paths; multi-PSP hosted payments with
reconciliation only; reviews with Core moderation; customer support as a SAO project; order and
delivery read models.

**Phase 2 (marketplace):** multi-vendor cart, per-vendor order split, payouts, commission accounting
(platform-as-service `Company`), per-vendor merchandising.

**Explicitly not in v1:** wishlist and product comparison (candidate backlog); a separate Support
module (SAO reuse covers v1); any second price or stock source; any card data or PSP secret in the
database; PHP/migration/test work in CMS/ERP/SAO beyond the extension seam named here.

---

## 4. Module and dependencies

- `module.json` declares dependencies on `Core`, `CMS`, `ERP`, `SAO`.
- Table prefix `shop_` via a `ShopTables` enum using `Core\Enums\Concerns\HasModuleTablesUtils`, following CMS/ERP (not MES).
- Models extend `Core\Overrides\Model`; every table `hasSoftDelete: true`; migrations use `MigrateUtils`; validations on the model in `getRules()`; permission names only from `Core\Support\PermissionName`. Follows the standard in the SAO 1a spec's global constraints.

---

## 5. Perimeter — what Shop owns

| Area | Shop role |
|------|----------------|
| Anchor + variants | `Product` links a storefront card to one `Content` and one/more `Item`s; web-only attributes (variant attributes JSON). |
| Web merchandising | `is_published_in_shop`, `featured`, `release_date`, channel badges/metadata. Never duplicates listino or stock. |
| Pre-order | Cart, checkout, guest/logged session, cart merge — until it becomes an ERP document. |
| Web-only promotions | Override layer over `PriceResolverService` (E8). |
| Payments (PSP) | Flow orchestration (hosted checkout), webhook/callback, reconciliation toward the ERP order (E13, E14). |
| Reviews / UGC | Ratings, textual reviews, moderation via Core (E10). |
| Support | A SAO project/ticket-type (E9); Shop carries order references only. |
| Delivery tracking (UX) | Read-model screens over ERP (E15). |

---

## 6. Data model (draft)

`shop_products` (catalog anchor):
- `id`, `company_id` (`BelongsToCompany`, ERP), `content_id` **NOT NULL, UNIQUE** FK → `contents` (one extender per content, seam C15), `kind` (`physical` / `digital` / `display-only`, E26), `is_published_in_shop` bool, `featured` bool, `release_date` nullable, `metadata` JSON (marketing badges / promo flags)
- no `item_id`: composition lives on the variant pivot below

`shop_product_variants` (the sellable unit; a simple product has one `is_default` variant):
- `id`, `product_id`, `is_default` bool, `attributes` JSON (size, colour, ...)

`shop_variant_items` (pivot — composes a variant from ERP `Item`s):
- `id`, `variant_id`, `item_id` FK → `erp_items`, `quantity`, `role`
- one row = simple; several rows = virtual bundle; zero rows = item-less variant (a `digital` sellable or a `display-only` card per the product `kind`, E26)

`shop_download_grants` (digital fulfilment, E26 — only for `kind = digital`):
- `id`, buyer (ERP customer / `user_id`), ERP `order_line` correlation, `product_id` (or asset ref), `expires_at` nullable, `max_downloads` nullable, `downloads_count`, timestamps
- issued on payment reconciliation (E14), checked at download time; the asset itself is a CMS Media Library file on the product

Reviews: **no Shop table.** A review is a CMS `Comment` (with `rating_score`) on the product's
content, moderated, aggregated into `cms_content_ratings` (`ContentRating`) — all shipped. The only
Shop-specific piece is an optional **verified-purchase gate**: a thin policy (and, if a hard link
to the order is wanted, a small `product_id`/`order_line_id` ↔ `comment_id` association) deciding who
may rate. Shape decided when the review slice is planned.

`shop_payment_reconciliations` (E14):
- `id`, `order_id` (ERP correlation), PSP driver key, opaque provider ids, normalised status, amount, currency, outcome, non-sensitive error reason, timestamps. No card data, no PSP secrets.

Carts and checkout sessions: Shop-owned until they become an ERP order.

The **`contents` table gains** `extended_type` (nullable, morph alias) — the only change to a table
CMS owns, justified by E3.

---

## 7. Content extension mechanism (the CMS seam)

This is the one novel piece and the only intrusion into CMS. It is deliberately generic. Its
authoritative design is `docs/superpowers/specs/2026-09-17-cms-content-extension-seam-design.md`; the
summary below is Shop's use of it.

**Why a column and not a registry-only approach.** A `Content` read in isolation must know whether it
is extended. Deriving that from `entity_id` plus an external registry works only if *every* read path
remembers to consult the registry; miss one and product-content leaks. A self-describing column makes
the default-hide a single global scope that always applies. (Note: the old `tightenco/parental`
subclass mechanism — `Content::$childTypes` / `makeFromEntity` — was removed in CMS commit `5176695`;
this seam does not revive it. A dedicated create-path that sets `extended_type`/preset can be
reintroduced when the module is built.)

**The column.** `contents.extended_type` nullable string. It holds a **stable alias** registered in a
dedicated `ContentExtenderRegistry` (not Laravel's global morph map), e.g.
`ContentExtenderRegistry::register('shop.product', Product::class)`. CMS stores and reads the
alias; it never references `Product`.

**Default hide.** A global scope on `Content` adds `whereNull('extended_type')`. Every existing CMS
read path (routes, admin lists, search rehydration) excludes extended contents automatically, with no
change at the call sites.

**Opt-in and upcast.** An explicit scope `withExtended()` (and/or an ACL-gated flag) removes the
global scope. When a caller wants extended results *as their extender*, it runs the batch upcast:

1. Fetch the page of `Content` as usual (`withExtended()` so extended rows are included).
2. Group the page rows by `extended_type` alias.
3. For each alias, resolve the extender class from the `ContentExtenderRegistry`, and load **all**
   extenders for that page in one query: `Product::whereIn('content_id', $contentIdsForThatAlias)->get()`
   (with the extender's own eager-loads, e.g. `item`).
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
The alias registration and the `content()`/`extended` wiring on the extender side are **Shop**
code. `Product` declares the inverse `content(): BelongsTo` to `contents`.

---

## 8. Reuse contracts

- **ERP:** `Item` (SKU/UoM/costing/stock), `PriceListItem` + `PriceResolverService` (pricing, M7.1 GA),
  `BelongsToCompany` (tenancy). Shop adds `ProductAvailabilityService` (reads ERP stock) and the
  web promotion override over `PriceResolverService`.
- **CMS:** `Content` (i18n, gallery, SEO, `HasApprovals`, `HasValidity`, `HasLocks`, `Searchable`,
  `HasPath`) plus the extension seam of §7. `Comment` + `ContentRating` + `ContentRatingService` give
  product comments and moderated 1-5 reviews for free (E10). A product content-`Entity` (specializing
  Core's abstract `Entity`) plus one `Preset` per category (the attribute sets, E20), seeded by the
  module.
- **SAO:** the ticketing engine for customer support (E9, boundary deferred).
- **Core:** `ModerationAdapterRegistry` (behind CMS comments); model concerns; permissions/ACL.

---

## 8a. ERP prerequisites and findings

Surfaced while designing Shop against ERP. Each is **ERP-owned** work, recorded here so this
design does not silently depend on a gap; each needs its own ERP task before the Shop slice that
relies on it ships. These are findings about the current ERP code, not Shop decisions.

- **ERP-1 — the Item taxonomy is a half-built, unconstrained classification (correctness).**
  `Item.taxonomy_id` and `PriceListItem.taxonomy_id` are raw FKs to the shared `core_taxonomies` table,
  consumed by `PriceResolverService` for the taxonomy-level price fallback and by `PartyPriceRule`
  matching (tested: `PartyPriceRuleTest`, "requires exactly one of item_id or taxonomy_id") — yet there
  is **no item taxonomy behind them**: no `EntityType` case for items/products (only `Activities`,
  `OpportunityStages`), no `Taxonomy` subclass for items, no `taxonomy()` relation on `Item`, and
  neither `ItemFactory` nor `ItemImporter` populates the column. An item can therefore point at an
  `OpportunityStage`, `Activity` or CMS `Category` row and silently mis-resolve its price. Recommended
  ERP fix: add `EntityType::Items`, an `ItemCategory extends Taxonomy` subclass, a `taxonomy()` relation
  on `Item`, and constrain both `taxonomy_id` FKs to that subclass. **Not a Shop blocker** — an
  unclassified item simply skips the fallback and uses its direct price. Independent of the storefront
  category (E22), which is a CMS `Category` Shop owns and ERP ignores.
- **ERP-2 — no deterministic standard VAT rate (blocks E25).** `TaxKind` has only `Vat`/`Withholding`;
  a company may hold several `kind=vat` `TaxCode`s for one country (e.g. 22/10/4%) with **nothing marking
  which is standard**, and there is no rate resolver (`InvoiceLine.tax_code_id` is chosen manually at
  document-build time). E25's browse-time display rate cannot pick one deterministically. Recommended
  ERP fix: an `is_standard` marker on `TaxCode` with a single active standard per `(company, country,
  kind)`, plus a small `standardVat(company, country)` resolver. **Prerequisite for the E25 tax-display
  slice.**
- **ERP-3 — no item type to mark a non-stock (service/digital) `Item` (optional).** `Item` has no
  type/kind field (only `tracing_type` none/lot/serial and `costing_method`), so an `Item` cannot be
  flagged as non-stock/always-available, and availability cannot tell a service/digital item from an
  out-of-stock one. **Not needed for v1**: digital products are sold item-less (E26), and ERP already
  accepts item-less sales/invoice lines. Only required if a future business goal wants digital/service
  goods to be first-class ERP `Item`s (e.g. per-item revenue reporting); then ERP would add an item
  type plus an availability rule that skips the stock gate for non-stock items.
- **ERP-4 — native stock reservation / ATP (in design, ERP-owned).** ERP has no reservation concept, so
  a `Confirmed`-but-unevaded sales order does not reduce availability (overselling, even in back-office),
  and MES cannot commit components. This is an ERP-native gap with its own design record,
  `2026-10-06-erp-stock-reservation-design.md` (scope v1 = sales + MES), not a Shop concern. Shop
  consumes it per E29: reads `available = on_hand − active reservations`, reserves `soft` at Draft and
  `hard` at capture once it ships; interim Shop v1 is check-at-checkout. **Sequencing**: the Shop stock
  slice depends on this ERP plan; Shop's other slices (catalog, cart, checkout skeleton) do not.

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
- A product content-`Entity` (specializing Core's abstract `Entity`) and its per-category `Preset`s
  seeded by the module (E20); the `extended_type` column, global scope, morph-alias registry and upcast
  pipeline agreed as a CMS change owned by this work.
- A short ADR confirming multi-tenant schema + single-vendor v1 (E11, E12).
- Reviews: moderation policy and optional verified-purchase rule aligned to ERP orders.
- SAO: the support ticket types and the map of "which transitions always require an ERP command".
- PSP: Stripe and PayPal hosted-checkout drivers chosen (E13) and the security checklist (secrets out
  of DB, log redaction) written.

---

## 11. Proposed file structure (indicative)

All paths relative to `Modules/Shop/`.

| File | Responsibility |
|------|----------------|
| `app/Enums/ShopTables.php` | Table-name registry |
| `app/Models/Product.php` | Catalog anchor; `content()`, variants |
| `app/Models/ProductVariant.php` | Sellable unit; `items()` pivot |
| `app/Models/VariantItem.php` (pivot) | Variant ↔ ERP `Item` with quantity/role |
| `app/Policies/VerifiedPurchasePolicy.php` | Gates who may rate a product (reviews reuse CMS comments+`ContentRating`) |
| `app/Models/PaymentReconciliation.php` | PSP reconciliation record (E14) |
| `app/Services/ProductAvailabilityService.php` | Reads ERP stock; min-over-components for bundles |
| `app/Services/ShopPriceService.php` | Web override over ERP `PriceResolverService`; sum/override for bundles |
| `app/Payments/PaymentDriver.php` (contract) + `Stripe`/`PayPal` drivers | Multi-PSP abstraction |
| `app/Support/ContentExtenderRegistration.php` | Registers `shop.product` alias + extender |
| `database/migrations/*` | Product/variant/review/reconciliation tables + the `contents.extended_type` column |
| `docs/rag/MODULE.md` | Module RAG doc (anchor, extension seam, price/stock, payments, support, tracking) |

CMS-side additions for the seam (owned by CMS, delivered in the same work block): the `extended_type`
column and migration, the global scope, the alias→resolver registry, and the batch upcast pipeline.

---

## 12. Open questions

To settle when the relevant slice is planned; none blocks the module's shape.

- **SAO support boundary (borderline).** SAO's native role is code/ops orchestration ticketing, not
  shop customer care, yet the mechanics fit: a customer is a user opening tickets on a project
  that could map to a seller or to an order. Define the exact mapping — project = seller? per-order
  tickets? customer identity vs internal user, and which SAO permissions/ACL apply — before building
  support. Deferred.
- ~~Cart → ERP order handoff~~ **decided (E27)**: the cart is Shop-owned until "place order", then
  becomes a `SalesOrder` (never a Quotation, never a Draft-as-cart); the buyer is linked to/created as an
  ERP `Party` at placement; cart merge is Shop-only. Draft-vs-Confirmed timing tracked separately below.
- ~~Price/stock browse snapshot~~ **decided (E21)**: browse-only public-list snapshot + in-stock flag
  indexed and refreshed on change; per-customer-group browse prices deferred to phase 2; exact
  price/stock always live from ERP at detail/cart/checkout.
- **Component configurator.** Dependent-option "build your own" products over the `variant_items`
  pivot (distinct from predefined variants), if/when needed.
- **Verified-purchase gate.** Reviews already exist as rated CMS comments + `ContentRating` (E10); the
  only open piece is the verified-purchase policy — whether to enforce it, and whether to hard-link a
  rating/comment to an `order_line_id` or just check purchase history at write time.
- ~~PSP driver contract~~ **decided (E30)**: a per-provider `PaymentDriver`
  (`createCheckoutSession`/`verifyAndParseWebhook`/`reconcile`/`refund`) behind a `PaymentDriverRegistry`;
  the webhook is the source of truth for `Confirmed`; reconciliation is idempotent on
  `(order_id, provider_event_id)`; Shop keys only on a normalized status; secrets never in DB (E14).
_Raised in the 2026-09-19 spec review:_

- ~~Tax/VAT display~~ **decided (E25)**: display is a presentation setting over the **net** ERP price —
  configurable per audience, default B2C gross; the E21 snapshot stores net, gross is a render-time
  multiply by the resolved standard VAT rate; authoritative tax stays ERP's at document build. Blocked
  on **ERP-2** (§8a). Reduced/per-product rates and destination-country VAT deferred.
- ~~Attribute taxonomy~~ **decided (E22)**: single writer per concern — ERP `Item` owns physical /
  transactional / producible truth (shared with MES/ERP, never written by Shop); `ProductVariant.attributes`
  JSON holds the purchase-selection axis as label/projection only (identity is the `variant_items`
  composition); `Content` preset `components` + `Product` `metadata` own editorial/merchandising.
  Classification is CMS `Category` under the product content-entity (per-category `Preset` on the
  `Category`); ERP taxonomy stays independent and ignored, the only ERP link is `variant_items` → `Item`.
- ~~Stock reservation / overselling~~ **decided (E29)**: Shop reads ERP availability; the native ERP
  reservation/ATP is being designed (`2026-10-06-erp-stock-reservation-design.md`, ERP-4) and Shop will
  reserve `soft` at Draft, `hard` at capture (E28); interim v1 is check-at-checkout with the race
  accepted. Shop never builds a parallel reservation. The in-stock browse snapshot (E21) is not a reservation.
- ~~Active-vendor resolution~~ **decided (E24)**: reuse ERP's `current_company_id()` binding through
  one Shop `StorefrontVendorResolver` (middleware sets `erp.current_company_id` per request); v1 reads a
  single `config('shop.company_id')`, v2 swaps only the resolver body for host→company multi-store.
- ~~Cart→order vs payment timing~~ **decided (E28)**: the `SalesOrder` is created `Draft` at placement
  (before payment, so `shop_payment_reconciliations.order_id` is present) and transitioned Draft →
  `Confirmed` on captured payment; unpaid Drafts are cancelled by a TTL job.
- ~~`company_id` placement~~ **decided (E23)**: `shop_products` and `shop_carts` carry it via
  ERP `BelongsToCompany`; `ProductVariant` derives it from the product (no column);
  `shop_payment_reconciliations` keys on the ERP order (no own column). Supersedes E11's looser wording.
- ~~Currency~~ **decided (E31)**: v1 is single-currency — the vendor `Company`'s `default_currency`;
  multi-currency display/settlement deferred.
- ~~`variant_items.role` value set~~ **decided (E31)**: closed set `main` / `component`.
- **Minor (verified-purchase):** the verified-purchase association need not store `product_id`
  (derivable comment→content→product) — settled with the verified-purchase gate when the review slice is planned.
- Plus the content-extension seam's own remaining open questions (import create-path, permissions on a
  mixed upcast list) tracked in that spec.
