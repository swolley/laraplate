# CMS content extension seam — design

**Date:** 2026-09-17
**Module:** `Modules/CMS` (with Core morph-map registration)
**First consumer:** `docs/superpowers/specs/2026-09-17-ecommerce-module-design.md` (`Product` extends a `Content`)
**Status:** Design agreed. No implementation started.

---

## 1. Purpose

Let another module attach a richer domain model to a `Content` without CMS knowing that model, and
without that model's rows leaking into generic CMS content surfaces. A CMS `Content` carries editorial
value (i18n, gallery, SEO, approvals, validity, locks, search, path); some modules need an object that
*owns* a content and adds its own columns and lifecycle (the first case is Ecommerce `Product`, which
also links an ERP `Item`). That owner is not a `Content` and not a subtype of one: it is a separate
table with a foreign key to `contents`.

The seam is generic. CMS gains the *concept* "a content may be extended", exposed as a reusable
extension point. It never references the extending class. Any module can extend content the same way.

This does **not** revive the removed `tightenco/parental` subclass mechanism (`Content::$childTypes`
/ `makeFromEntity`, deleted in CMS commit `5176695`). Those were single-table-inheritance on the
`contents` table; this seam is the opposite — the extender is a separate table that references a
content.

---

## 2. Locked decisions

| # | Decision | Rationale |
|---|----------|-----------|
| C1 | A `Content` becomes self-describing about extension through a **nullable `contents.extended_type`** column holding a **morph alias** (e.g. `ecommerce.product`). `null` means a normal content. | Reading a content in isolation must reveal whether it is extended without every query consulting an external registry. A column makes the default-hide a single, reliable global scope, and covers the partial case (a content of an extended entity that has no extender). |
| C2 | The column stores a **stable alias string, never an FQCN** (convention `module.model`, e.g. `ecommerce.product`), resolved through a **dedicated `ContentExtenderRegistry`** — **not** Laravel's global Eloquent morph map. The alias→class map is registered by the extending module; CMS only reads/writes the alias string. | Keeps CMS free of any extender class name and the dependency direction extender → CMS; aliases survive class renames. A dedicated registry holds only content extenders, so the list never fills with unrelated morph aliases (taggable, mediable, and the like) the way the shared morph map would. |
| C3 | A **global scope on `Content`** hides `extended_type IS NOT NULL` by default on every read path. | Registering an extender must not leak its content into generic CMS routes, admin lists or search rehydration. Existing call sites change nowhere. |
| C4 | Extended contents are returned only through an **explicit opt-in** (`withExtended()` scope and/or an ACL-gated flag). Opt-in alone yields plain `Content`; the **upcast** step yields the extender. | "Sometimes I want them, sometimes not" is an explicit choice, not a side effect of registration. |
| C5 | The upcast is a **named, batched projection**: it groups the page by alias, resolves each extender class from the morph map, loads all extenders for the page in one query per alias (`whereIn('content_id', $ids)` with the extender's own eager-loads), swaps items, and sets the inverse relation `setRelation('content', $content)`. It never mutates the base `Content` query and never lazy-loads per row. | Preserves pagination/eager-loading; guarantees O(aliases-on-page) queries, not O(rows). See §4. |
| C6 | CMS owns the seam (column, scope, opt-in, morph-map contract, upcast pipeline). The **extender** owns its table, its `content(): BelongsTo` back-relation, its alias registration, and setting `extended_type` on the content it creates. | Clean split: CMS is the extension point, the module is the plug. |
| C7 | The extender declares an interface/contract (working name `ExtendsContent`) exposing `content(): BelongsTo` and its alias. CMS resolves extenders only through the morph map + this contract. | A typed seam CMS can rely on without importing any concrete class. |
| C8 | The extender's back-relation **removes the hide scope**: `content(): BelongsTo` is defined `->withoutGlobalScope(HidesExtendedContent::class)`. | The owner points to a content whose `extended_type` is set; under the default scope the relation would resolve to `null`. This is the seam's sharpest silent-failure trap. |
| C9 | **The extender owns the content's lifecycle and declares whether the content is mandatory.** Extender soft-delete/force-delete/restore always cascade to the content, in a transaction. The reverse (a content deleted directly) depends on that declaration: **mandatory content → symmetric cascade** (deleting the content deletes the extender; there is no bodiless-orphan state, because the FK is NOT NULL); **optional content → orphan** (`content_id` set null, extender removed from its channel, a signal raised, order/stock links untouched). Deletion is reacted to, never prevented (no `valid_to = now()` interception). Ecommerce `Product` uses the **mandatory/symmetric** policy. | An extender that cannot exist without a body has no orphan state, so it collapses to a symmetric cascade; one whose body is optional degrades to an orphan instead. The seam supports both; the extender picks. |
| C10 | **Cascade guard.** When a content is deleted as part of its extender's cascade, the content observer must **not** run the orphan handler. A context flag distinguishes "deleting via extender cascade" from "content deleted directly". | Without the guard the two directions collide: the cascade would orphan an extender that is itself already being deleted. |
| C11 | **Search is governed by the same two levers.** `extended_type` is indexed as a **filterable attribute**; the generic content search filters `extended_type = null` at the index (no missing-hit holes); surfaces that want extenders remove that filter and apply the same upcast to the rehydrated hits (search returns `Content`, then swaps to the extender). | Hiding only at DB rehydration while indexing everything yields short pages (index returns N, model hides some). Filtering at the index keeps counts honest and gives search the same opt-in as the routes. |
| C12 | An `extended_type` whose alias is **not in the morph map fails loud** during upcast (explicit exception), never a silent skip. | A missing registration must surface, not return a half-resolved object. |
| C13 | **The `ContentExtenderRegistry` is code, keyed by the stable alias, populated at boot** by each extender's service provider — never keyed by `entity_id` and never read from a CMS table. On an install with no extender it is empty and every extension path is a no-op. The extender **seeds its own `Entity`** and resolves its id at runtime. | CMS does not and must not know extenders; the numeric `entity_id` differs per install and does not exist on a fresh app. Only a stable, code-level alias (like a Laravel morph type) survives both facts. |
| C15 | **One extender per content.** A content has at most one extender: a single `extended_type` value (one column) and a `unique` constraint on the extender's `content_id`. A registered alias with no owner row is a data-integrity error surfaced by the upcast's fail-loud (C12) or a reconciliation check, never a silently half-resolved row. | The upcast keys extenders by `content_id`; a second extender or a duplicate link would make the swap ambiguous. |
| C14 | **CMS owns the physical `contents` search index; extenders contribute, they do not manage it.** Only CMS index-lifecycle commands create/delete/recreate it. At (re)creation CMS **composes the mapping** from each registered extender's `searchableExtensionMapping()`. A module "reindex" is **document-scoped** (`Content::withExtended()->where('extended_type', $alias)->searchable()`), never `deleteIndex`/`createIndex`. A module "unindex" clears the extender's `extension` section (or deletes only its documents), never drops the shared index. | A shared index has one owner. Letting a module drop or recreate it would wipe editorial content; letting mapping be implicit reintroduces the dynamic-mapping bug. A full CMS content reindex already restores the product data, because `toSearchableArray()` pulls the extender's projection at import. |

---

## 3. Mechanism

**Column.** `contents.extended_type` nullable string, indexed. Migration adds it to the existing
`contents` table; partitioned-table migration variant updated in the same block if present.

**Registration is code, not data.** The extending module registers itself in its service provider **at
boot**, into a **dedicated `ContentExtenderRegistry`** (a CMS singleton), keyed by the stable alias:
`ContentExtenderRegistry::register('ecommerce.product', Product::class)`. This registry is **not**
Laravel's global `Relation::enforceMorphMap`: the global morph map is shared by every polymorphic
relation in the app (taggable, mediable, commentable, module morphs), so an alias placed there would
sit among unrelated entries. The content-extension registry holds **only** content extenders. (The
extender may still use the global morph map for its *own* polymorphic relations; that is unrelated to
this seam.) The pattern is the same as a morph map — a stable alias string mapped to a class in code —
but the storage is dedicated.

The registry is keyed by the alias, **never by the numeric `entity_id`** (which differs per install
and does not exist on a fresh app). Like a morph map, the alias→class map lives in code and is present
whenever the module is installed and booted, independent of any table contents. On an install with no
extender registered the registry is empty and every extension code path is a no-op; CMS never needs a
row to know an extender exists. Seeding the `Entity` row (e.g. PRODUCTS) is the **extender's**
responsibility, and its id is resolved at runtime (by slug/type) when a product's content is created —
never hard-coded.

**Default hide.** A `Content` global scope adds `whereNull('extended_type')`. Anonymous/admin/search
paths exclude extended rows automatically.

**Opt-in.** `Content::withExtended()` removes the global scope for that query. On its own it returns
`Content` rows (including extended ones) unchanged.

**Upcast.** A named pipeline (a query macro or a dedicated resolver, e.g. `->asExtended()` over the
result, or a `ContentExtensionResolver::resolve($page)`), applied by the caller that wants extenders:

1. Take the fetched page (with `withExtended()`).
2. Group rows by `extended_type` (rows with `null` stay as `Content`).
3. For each alias: resolve the class from the morph map; load all its extenders for the page in one
   query, `whereIn('content_id', $idsForThatAlias)`, eager-loading the extender's own relations.
4. Map `content_id => extender`; replace each content with its extender; `setRelation('content', $content)`.
5. Return the mixed collection (extenders where present, plain `Content` otherwise), preserving order
   and the paginator.

---

## 4. N+1 guarantee

The cost is **one query for the page of contents plus one query per distinct alias on the page** —
never one per row. CMS content lists are already **entity-scoped** (routes are `/{...}/{entity}`), so a
listing of one extended entity carries a single alias and resolves in exactly two queries.
`setRelation('content', ...)` closes the loop: a later `$extender->content` is already loaded and
triggers no query. The extender's own relations are eager-loaded inside step 3's single query.

Worked example:

```php
// Query 1: the page of contents, extended rows included.
$contents = Content::withExtended()
    ->where('entity_id', $productsEntityId)
    ->paginate(20);

// Query 2: every extender for the page in one shot, with its own relations eager-loaded.
$products = Product::whereIn('content_id', $contents->pluck('id'))
    ->with('item')
    ->get()
    ->keyBy('content_id');

// In-memory swap: replace each Content with its extender, carry the content back.
$items = $contents->getCollection()->map(function (Content $content) use ($products) {
    $product = $products->get($content->id);

    if ($product === null) {
        return $content;                        // no extender: stays a Content
    }

    $product->setRelation('content', $content); // content travels with the product, no re-query

    return $product;                            // list now yields Product
});
```

Two queries for the page, not 20 + 1.

---

## 5. Lifecycle, back-relation and search

**Back-relation (C8).** The extender declares:

```php
public function content(): BelongsTo
{
    return $this->belongsTo(Content::class, 'content_id')
        ->withoutGlobalScope(HidesExtendedContent::class);
}
```

Without the scope removal `$extender->content` resolves to `null`, because the target content has
`extended_type` set and the default scope hides it. In the list/search upcast this is moot (the
content is attached with `setRelation`), but any ad-hoc access needs it.

**Lifecycle (C9, C10).** The extender owns the content:

- Extender `deleting` (soft) → soft-delete the content; `forceDeleting` → force-delete it;
  `restoring` → restore it. All in one transaction.
- A content deleted directly follows the extender's declared policy: **mandatory content → symmetric
  cascade** (the extender is deleted with it — the case for Ecommerce `Product`, whose `content_id` is
  NOT NULL); **optional content → orphan** (`content_id` null, channel visibility off, a signal to the
  owner, order/stock links intact). Deletion is reacted to, never intercepted/prevented.
- **Cascade guard:** the content observer skips its reverse handler (cascade-delete or orphan) when the
  content is being deleted as part of its extender's own cascade (a context flag set by the extender
  before it cascades), so the two directions do not collide.

**Search (C11).** One physical index, `contents`. The extender does **not** get its own index; it
enriches the content's document. The `ExtendsContent` contract exposes two projection methods, called
by CMS through the registry:

- `searchableExtension(): array` — the extender's data, merged by `Content::toSearchableArray()` into a
  **nested, typed** object (`extension: { type: 'ecommerce.product', brand, shop_category, ... }`),
  never flat-merged (avoids field collisions and lets several extender types coexist).
- `searchableExtensionMapping(): array` — the mapping fragment CMS composes into the index at creation
  (C14), so the fields are mapped explicitly rather than falling to dynamic mapping.

`extended_type` is itself an indexed, filterable attribute:

- Generic content search filters `extended_type = null` at the index, so extended rows never appear
  and no result holes form (the index count matches the rehydrated rows).
- A surface that wants extenders (shop search, unified search) drops that filter and applies the same
  upcast to the rehydrated hits: search returns `Content`, then swaps to the extender exactly as the
  lists do (§3-§4). The DB rehydration on that path uses `withExtended()`.

Two rules this imposes:

- **Reindex trigger from the extender.** The `extension` fields change on the extender, not the
  content, so an extender change must re-push its content (`$product->content->searchable()` via an
  observer) — otherwise the section goes stale.
- **No ERP-owned or ERP-derived data in the index.** Price, stock, availability and any `Item` field
  are never indexed — they are authoritative and volatile in ERP and take no part in the indexes. The
  `extension` section holds only the extender's **own** stable attributes (for Ecommerce: variant
  attributes, `is_published_in_shop`, merchandising metadata). Price, stock and availability resolve at
  read time through ERP services, never from the index. A consumer may index a clearly
  **non-authoritative, browse-only snapshot** of such data as a deliberate, bounded exception it owns
  and refreshes on change (e.g. Ecommerce's price / in-stock browse snapshot for sort and facets,
  spec `2026-09-17-ecommerce-module-design.md` E21) — never used for a transaction.

This gives search the same opt-in "sometimes yes, sometimes no" as the routes, symmetric with the DB
global scope.

---

## 6. Non-goals

- No revival of STI child classes on the `contents` table.
- No CMS knowledge of any extender class; only aliases and the `ExtendsContent` contract.
- No create-path in this seam. How an extender creates its backing content (setting `extended_type`,
  preset and entity) is the extender module's concern; a dedicated factory can be designed there. If a
  generic CMS helper proves necessary later it is a separate decision, not this seam.
- No change to how contents are exposed per entity beyond the default-hide; entity route whitelisting
  stays as it is, now safe because extended rows are hidden unless opted in.

---

## 7. Testing

- A plain `Content::all()` / any default query never returns a row with `extended_type` set.
- `withExtended()` returns extended rows as `Content`.
- The upcast returns the extender for extended rows and plain `Content` for the rest, in original
  order, with `content` already set (assert no additional query via a query count).
- Query count for an entity-scoped extended page is exactly two.
- The extender's `content()` relation resolves the content despite the hide scope (asserts C8).
- A row whose alias is not in the morph map **fails loud** during upcast (C12).
- Extender soft-delete/force-delete/restore cascade to the content; a direct content deletion orphans
  the extender (`content_id` null, channel off, signal raised) without touching order/stock links; the
  cascade guard prevents the orphan handler firing during the extender's own cascade (C9, C10).
- Generic content search excludes extended rows with no result holes; an opt-in search returns the
  extender for extended hits (C11).

---

## 8. Risks

- **Back-relation returns null (C8).** The single sharpest trap; the extender's `content()` must drop
  the hide scope. Covered by test.
- **Cascade collision (C10).** Extender-driven content deletion re-triggering the orphan handler.
  Guarded by a context flag; covered by test.
- **Raw query builder paths.** The column guards the model, not a `DB::table('contents')` bypass. No
  blanket rule: raw reads stay free, and replicate the hide only where the context is a generic
  listing that needs it — a per-call judgement, not an obligation.
- **Alias drift.** An alias renamed without a data migration orphans rows. Treat aliases as stable
  identifiers, like any morph map value.
- **Search holes.** Indexing extended content but hiding only at DB rehydration yields short pages;
  avoided by filtering `extended_type` at the index (C11), not after.

---

## 9. Open questions

To close when the implementation plan is written; none blocks the seam's shape.

- **Extender without a content** (`content_id` null — a draft with no body): allowed for an extender
  that declares content optional, and how it shows (not indexed, not content-searchable). Ecommerce
  `Product` forbids it (content mandatory), so this is a generic-seam question only.
- **Content-state precedence** (per extender). How the content's approval / validity / scheduling axes
  gate the extender's own channel; the seam leaves the rule to the extender. Ecommerce settles it in
  its spec (E18): storefront visibility = editorial public-visibility AND `is_published_in_shop`.
- **Scope composition.** `withExtended()` must remove only `HidesExtendedContent`, leaving the
  company, soft-delete and validity global scopes intact; verify they compose.
- **Extension i18n and embeddings.** Which `extension` fields are per-locale vs flat, and whether the
  extension text contributes to the content's agnostic embeddings (`collectEmbedText`) or only to
  keyword/facets.
- **Import.** `cms:import` creating a content of an extended entity must create the extender too (via a
  service) or be forbidden from setting `extended_type`, so imports cannot mint orphans.
- **Versioning.** How `Content` `HasVersions` restore interacts with `extended_type` and the orphan
  logic (a restored old version could resurrect or clear the marker).
- **Permissions on a mixed upcast list.** Whether the governing policy is the `Content`'s (editorial)
  or the extender's (channel); tentatively the surface decides.
