# CRUD facet counters (funnel utility on Crud)

Status: **Implemented (tier 1)** — extracts the one valuable idea from the parked Grid subsystem (the funnel double counter) and re-homes it as a thin utility on `CrudService`. Grid routes/subsystem are NOT revived. Shipped as `CrudService::facetCounts(ListRequestData)` (with the `excludeFacetField` self-exclusion transform), the standalone `POST|GET /crud/facets/{module}/{entity}` action (`CrudController::facets` + `FacetsRequest` reusing the list vocabulary — every requested column is a facet dimension), covered by `CrudServiceFacetCountsTest` + `CrudFacetsTest`. Tier 2 (open/high-cardinality facets with their own pagination/search/sort and the key≠label two-step) remains the documented next slice.

## Problem

- The "funnel" UX — click a facet value, see the filtered result plus a live count next to every option — is genuinely valued by clients ("click, don't type"). It rides on top of the frontend query builder, which already composes arbitrarily complex Crud calls.
- Today that UX only exists inside the **Grid subsystem**: a parallel CRUD surface (~6.7k LOC) whose read pipeline is broken (Concurrency misuse runs `processData/processOptions/processFunnels` in forked children and discards the results, see `Grid::callbackToReadAction`) and whose write routes duplicate Crud. Heavy, half-finished, unverified.
- `CrudService` already does the hard part: dynamic nested filters, sorts, relations, `group_by`, `count`, ACL. The **only** capability it lacks is per-facet **cross-filtered** counts.

## Goals

- Deliver the funnel UX as a **thin utility on `CrudService`**: given the current filter state and a list of facet fields, return, per facet value, a `total` and a `count`.
- Reuse the frontend query builder's existing Crud `filters` payload — **no new request layer**.
- One code path, cacheable, secured by the existing Crud ACL and model connection affinity.

## Non-Goals

- Not reviving Grid routes, the Grid request layer, or duplicated write verbs.
- Not `options` / `layout` / `export` (separate concerns, later or never).
- Not deep multi-hop relation facets in v1 (the Grid `Funnel` `FIXME` path). Direct columns and single-hop relations first.

## Semantics — the double counter

For each facet field `F` and each observed value `v`:

- `total(v)` = count of records with `F = v` applying **no facet filters** (the universe for that facet; base scope + ACL still apply).
- `count(v)` = count of records with `F = v` applying the **current filter state, excluding `F`'s own selection**.

Excluding `F`'s own selection is what keeps the facet "live": as the user picks other facets, each option still shows how many results it *would* add. This is exactly the cross-filter rule already present in `Modules/Core/app/Grids/Components/Funnel.php:100-121`, extracted and finished.

## Feasibility — what Crud already gives vs what is new

The current `Funnel` class cannot be lifted verbatim: it `extends ListEntity` and is welded to the Grid `Entity`/`Field`/`Relation` graph and to `GridRequestData`. We re-home the **idea**, not the class, on `CrudService` primitives.

Already reusable (the load-bearing part):
- Nested filter + relation-filter + ACL application: `QueryBuilder::prepareQuery`.
- A SQL-efficient single `COUNT(*)` on the model's own connection: `$query->count()`.
- So "count of records under filter set X" is already a first-class Crud operation.

Genuinely new, and small:
- A pure transform "`FiltersGroup` minus nodes targeting field `F`" (the facet self-exclusion).
- Per-value counting. **Note:** Crud's `group_by` is an *in-memory* `Collection::groupBy` after fetch (`HasCrudOperations::applyGroupBy`), NOT a SQL `GROUP BY`, so it is unusable for counts on large tables. Two tiers:
  - **Enumerable facets** (status/type/enum/boolean/small FK): the option list is known, so counts = one `count()` per option with `filters = (base − F node) AND F = option`. **No new query machinery** — pure composition. Covers the classic "click a value from a list" UX.
  - **Open / high-cardinality facets**: add one small QueryBuilder primitive `SELECT F, COUNT(*) … GROUP BY F` (with a join for single-hop relation facets).

Ship tier 1 first; tier 2 only if an open facet is actually needed.

## A facet is a paginated sub-list, not a flat aggregate

High-cardinality facets (e.g. `author.name`, `city`, `tag`) can have thousands of distinct values, so the **facet's own value list needs pagination, search and ordering** — it is a mini list-of-values-with-counts, not a flat aggregate. This is precisely why the parked Grid built **one `ListRequest` per funnel** (`GridRequest::realFunnelRequests`, `Funnel extends ListEntity`): the complexity was real, not accidental. We keep that insight while dropping the parallel apparatus.

Each facet is therefore a small request, not just a field name:

```
facets: [
  { field: 'author.name', page: 1, perPage: 20, search: 'ro', sort: 'count_desc' },  // high cardinality
  { field: 'status' }                                                                 // enumerable → all values
]
```

Response, per facet: the **paginated slice** of `{value, total, count}` plus the distinct-value count (to paginate the facet itself). `total(v)` is only needed for the values on the current page, so it stays bounded.

## Group key ≠ label ≠ display fields

The value a facet is grouped/counted by is not necessarily what is shown. A facet may group by a key (e.g. `author_id`) but display a different field, or a set of fields the frontend composes into a string (e.g. `first_name` + `last_name`). The parked `Funnel` already separated these (`getValueField()` vs `getLabelField()` — possibly an array — vs `columns`, `Funnel.php:29,45-51`); we keep that separation, finished.

A facet spec therefore carries:
- `groupBy` — the **key** to aggregate and count on (e.g. `author_id`).
- `fields` (a.k.a. `label`) — the **display fields** returned per grouped value; the frontend builds the shown string.

Per-value result is structured, not a pre-baked label:

```
{ key: 42, count: 12, total: 130, attributes: { first_name: 'Anna', last_name: 'Rossi' } }
```

**Label resolution in two steps** (portable across MySQL/MariaDB/PostgreSQL/Oracle/SQLite):
1. grouped-count on the **key only** → a page of `[key, count, total]`;
2. a bounded `whereIn(key, [page keys])` select to resolve the requested `fields` — this is a **normal Crud read**, bounded to the page.

This keeps `GROUP BY` on a single column (portable) and moves labelling into a cheap second round.

**Deferred (later tier):** `search`/`sort` **by label** while grouping by key (type "ross" to find author Rossi, or order by surname). That forces the label into the aggregated query (join + GROUP BY functional-dependency care across DBs). If `search`/`sort` are by key or by count instead, the two-step stays clean. Not in v1.

## API shape (backend)

A single `CrudService` method, reused by both HTTP entry points below:

```php
/**
 * @param  list<FacetSpec>  $facets  each: groupBy (key column or single-hop relation path),
 *                                    optional fields (display fields resolved per key),
 *                                    optional page/perPage/search/sort
 * @return array<string, FacetResult>  per facet: paginated [{key,total,count,attributes}] + distinctValues
 */
public function facetCounts(ListRequestData $base, array $facets): array;
```

- `$base->filters` is the `FiltersGroup` the frontend query builder already produced (identical to a normal list call).
- For each facet field `F`: `count` applies `$base->filters` **minus any node targeting `F`**; `total` applies base scope + ACL only.
- Enumerable facets → one `count()` per option (no new query machinery). Open facets → `SELECT F, COUNT(*) … WHERE (filters − F) [AND F LIKE search] GROUP BY F [ORDER BY count|F] LIMIT/OFFSET` (single-hop relation → join). Always on the model's own connection. No per-request process fork.

## HTTP surface — a standalone facets endpoint (decided)

**Decision:** the primary entry point is a **standalone** `/crud/facets/{module}/{entity}` read action, not a piggyback on the list. The frontend must be able to **reload only the facets** — paginate/search within a facet, or refresh counts — **without re-fetching the table data**. That independence requires a call of its own; the standalone route re-runs its full bootstrap (entity resolution, filter parsing, permission check) on purpose, and that recompute is the accepted cost of decoupling. It is also simpler: no entanglement with the list response envelope.

A later **piggyback** on the list (`facets: [...]` returning `data` + first facet page in one round-trip) is an optional optimization for the initial screen load, not required for v1.

Both would be thin wrappers over the same `facetCounts()` service, inside the Crud controller/permission model — no `/grid` prefix, no parallel request layer. **This is the whole difference from Grid:** Grid rebuilt an entire CRUD around funnels; here it is one service method plus lean routes on top of `CrudService`.

## Testing

- **Hand-computed oracle:** seed a small table (+ one relation for the single-hop case); assert `total`/`count` manually. No filter → `count == total`; filter on another facet → `total` unchanged, `count` drops to the expected value.
- **Differential vs Crud (ties the utility back to its base):** `count` for value `v` must equal a Crud list `count=true` run with `(F = v) AND (current other filters)`. Crud is the trusted oracle.

## Performance

- Cacheable by `(entity, normalized filter-state)` with a short TTL; facet counts are cross-dependent so they cannot be cached naively, but a short TTL per filter-combination is safe.
- Cap the number/cardinality of active facets; approximate very large buckets if it ever matters. No fork/process concurrency — a handful of indexed grouped counts is cheaper than the process overhead it would replace.
