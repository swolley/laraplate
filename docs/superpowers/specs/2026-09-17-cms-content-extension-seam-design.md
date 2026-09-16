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
| C2 | The column stores a **morph alias, never an FQCN**, resolved through Laravel `Relation::enforceMorphMap`. The alias→class map is registered by the extending module; CMS only reads/writes the alias string. | Keeps CMS free of any extender class name; the dependency direction stays extender → CMS. Aliases also survive class renames. |
| C3 | A **global scope on `Content`** hides `extended_type IS NOT NULL` by default on every read path. | Registering an extender must not leak its content into generic CMS routes, admin lists or search rehydration. Existing call sites change nowhere. |
| C4 | Extended contents are returned only through an **explicit opt-in** (`withExtended()` scope and/or an ACL-gated flag). Opt-in alone yields plain `Content`; the **upcast** step yields the extender. | "Sometimes I want them, sometimes not" is an explicit choice, not a side effect of registration. |
| C5 | The upcast is a **named, batched projection**: it groups the page by alias, resolves each extender class from the morph map, loads all extenders for the page in one query per alias (`whereIn('content_id', $ids)` with the extender's own eager-loads), swaps items, and sets the inverse relation `setRelation('content', $content)`. It never mutates the base `Content` query and never lazy-loads per row. | Preserves pagination/eager-loading; guarantees O(aliases-on-page) queries, not O(rows). See §4. |
| C6 | CMS owns the seam (column, scope, opt-in, morph-map contract, upcast pipeline). The **extender** owns its table, its `content(): BelongsTo` back-relation, its alias registration, and setting `extended_type` on the content it creates. | Clean split: CMS is the extension point, the module is the plug. |
| C7 | The extender declares an interface/contract (working name `ExtendsContent`) exposing `content(): BelongsTo` and its alias. CMS resolves extenders only through the morph map + this contract. | A typed seam CMS can rely on without importing any concrete class. |

---

## 3. Mechanism

**Column.** `contents.extended_type` nullable string, indexed. Migration adds it to the existing
`contents` table; partitioned-table migration variant updated in the same block if present.

**Registration.** The extending module registers the alias in a service provider:
`Relation::enforceMorphMap(['ecommerce.product' => Product::class])` (merged, not overriding the
app-wide map). CMS exposes a small `ContentExtenderRegistry` keyed by alias so the upcast can find the
class and (optionally) the entity it applies to; the registry is populated from the morph map plus the
`ExtendsContent` contract.

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

## 5. Non-goals

- No revival of STI child classes on the `contents` table.
- No CMS knowledge of any extender class; only aliases and the `ExtendsContent` contract.
- No create-path in this seam. How an extender creates its backing content (setting `extended_type`,
  preset and entity) is the extender module's concern; a dedicated factory can be designed there. If a
  generic CMS helper proves necessary later it is a separate decision, not this seam.
- No change to how contents are exposed per entity beyond the default-hide; entity route whitelisting
  stays as it is, now safe because extended rows are hidden unless opted in.

---

## 6. Testing

- A plain `Content::all()` / any default query never returns a row with `extended_type` set.
- `withExtended()` returns extended rows as `Content`.
- The upcast returns the extender for extended rows and plain `Content` for the rest, in original
  order, with `content` already set (assert no additional query via a query count).
- Query count for an entity-scoped extended page is exactly two.
- A row whose alias is not in the morph map fails loudly (or is skipped by an explicit policy —
  decided at implementation), never silently returns a half-resolved object.

---

## 7. Risks

- **Forgetting the global scope on a raw query builder path.** The column is the guard, but a
  `DB::table('contents')` bypass would skip it. Audit raw content reads; prefer the model.
- **Alias drift.** An alias renamed without a data migration orphans rows. Treat aliases as stable
  identifiers, like any morph map value.
- **Search index rehydration.** The search path must apply the same default-hide (or opt-in) as HTTP,
  or extended content reappears through search. Covered by the global scope if rehydration goes
  through the model; verified by test.
